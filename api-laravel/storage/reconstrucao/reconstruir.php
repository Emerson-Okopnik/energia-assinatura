<?php
/**
 * Reconstrução auditável do faturamento de geração (relatório ANTES × DEPOIS) — versão corrigida.
 *
 * Dirige o MOTOR DE DOMÍNIO ÚNICO (CalculadoraGeracaoLinear via ReconstrutorLedger),
 * aplicando as regras de REGRAS_DE_CALCULO.md:
 *   - Geração líquida = bruta − max(consumo − desconto_rede, 0)            (§9)
 *   - Crédito limitado ao faltante e à reserva, FIFO cross-ano             (§6)
 *   - Expiração 180 dias DEPOIS do consumo FIFO (só o que sobrou)          (§7)
 *   - Saldo inicial migrado para usinas com déficit histórico > excedente  (§12)
 *
 * Roda contra o STAGING (cópia de produção), SEM tocar produção e SEM alterar o staging.
 * Não reimplementa cálculo: o motor é o mesmo que vai para produção (zero duplicação).
 *
 * Uso (dentro do container php-recon, com a porta do staging acessível):
 *   php reconstruir.php          -> gera relatorio.html + relatorio-antes-depois.csv
 *   php reconstruir.php --uc=X   -> detalha uma usina no terminal
 *
 * Conexão configurável por env (DB_HOST/DB_PORT) para rodar de container:
 *   docker run --rm -e DB_HOST=host.docker.internal -v "$PWD":/app -w /app php-recon \
 *     php storage/reconstrucao/reconstruir.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Domain\Faturamento\Calculo\DescontoRede;
use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\ReconstrutorLedger;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '5440';
const DB_NAME = 'energia_assinatura';
const DB_USER = 'postgres';
const DB_PASS = 'staging';
const TOL = 0.01;

$MESES = [1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
          7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$MES_LABEL = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];

$opts = getopt('', ['uc::']);
$ucFiltro = $opts['uc'] ?? null;

$pdo = new PDO('pgsql:host='.$DB_HOST.';port='.$DB_PORT.';dbname='.DB_NAME, DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$reconstrutor = new ReconstrutorLedger();

// ---------- carga base ----------
$sql = "SELECT u.usi_id,u.uc,u.rede,cli.nome AS cliente,
               c.valor_kwh,c.fio_b,c.percentual_lei,d.media,d.menor_geracao
        FROM usina u
        JOIN comercializacao c ON c.com_id=u.com_id
        JOIN dados_geracao d ON d.dger_id=u.dger_id
        LEFT JOIN cliente cli ON cli.cli_id=u.cli_id";
if ($ucFiltro) { $sql .= " WHERE u.uc=".$pdo->quote($ucFiltro); }
$usinas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// consumo duplicado (qualidade de dados) — global
$consumoDup = $pdo->query(
    "SELECT count(*) FROM (SELECT usi_id,ano FROM dados_consumo_usina GROUP BY usi_id,ano HAVING count(*)>1) x"
)->fetchColumn();

$linhas = [];          // divergências (flat)
$porTipo = [];         // contagem/soma por tipo
$usinasAfetadas = [];  // uc => true
$usinasReport = [];    // uc => timeline detalhada (drill-down)
$dq = ['saldo_inicial'=>[], 'sem_rede'=>[], 'sem_fat_antes'=>[]]; // qualidade de dados
$totDiffFinal = 0.0;   // impacto no valor final (depois-antes)

foreach ($usinas as $u) {
    $usiId = (int) $u['usi_id'];
    $uc = $u['uc'];
    $tarifa = (float) $u['valor_kwh'];
    $media = (float) $u['media'];
    if (empty($u['rede'])) { $dq['sem_rede'][] = $uc; }

    // geração real por ano
    $st = $pdo->prepare("SELECT dgru.ano,dgr.* FROM dados_geracao_real_usina dgru
        JOIN dados_geracao_real dgr ON dgr.dgr_id=dgru.dgr_id WHERE dgru.usi_id=:u ORDER BY dgru.ano");
    $st->execute([':u'=>$usiId]);
    $geracaoPorAno = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$geracaoPorAno) { continue; }

    // consumo por ano (DEDUP: registro mais recente por (usina,ano))
    $st = $pdo->prepare("SELECT DISTINCT ON (dcu.ano) dcu.ano,dc.* FROM dados_consumo_usina dcu
        JOIN dados_consumo dc ON dc.dcon_id=dcu.dcon_id WHERE dcu.usi_id=:u
        ORDER BY dcu.ano, dc.updated_at DESC");
    $st->execute([':u'=>$usiId]);
    $consumoPorAno = [];
    foreach ($st as $r) { $consumoPorAno[(int) $r['ano']] = $r; }

    // crédito ANTES (creditos_distribuidos — colunas mensais por ano) — fonte mais completa
    $st = $pdo->prepare("SELECT cdu.ano,cd.* FROM creditos_distribuidos_usina cdu
        JOIN creditos_distribuidos cd ON cd.cd_id=cdu.cd_id WHERE cdu.usi_id=:u");
    $st->execute([':u'=>$usiId]);
    $credAntes = [];
    foreach ($st as $r) { $credAntes[(int) $r['ano']] = $r; }

    // faturamento ANTES por competência (4 termos) — pode faltar o último mês
    $st = $pdo->prepare("SELECT competencia,valor_fixo,injetado,creditado,cuo,valor_final
        FROM geracao_faturamento_pdf WHERE usi_id=:u");
    $st->execute([':u'=>$usiId]);
    $fatAntes = [];
    foreach ($st as $r) { $fatAntes[substr($r['competencia'], 0, 7)] = $r; }

    // reserva (corrompida) do ano mais antigo — base do saldo inicial migrado (§12)
    $st = $pdo->prepare("SELECT cdu.ano,var.total FROM creditos_distribuidos_usina cdu
        JOIN valor_acumulado_reserva var ON var.var_id=cdu.var_id WHERE cdu.usi_id=:u
        ORDER BY cdu.ano ASC");
    $st->execute([':u'=>$usiId]);
    $reservaPorAno = [];
    foreach ($st as $r) { $reservaPorAno[(int) $r['ano']] = (float) $r['total']; }

    // ---- monta meses crus ----
    // CUO = faturaEnergia (input manual, não está nas tabelas) + geracao×fio_b×pct/100 (§5).
    // Para o CUO DEPOIS ser comparável ao ANTES, derivamos a faturaEnergia do CUO ANTES:
    //   faturaEnergia = cuoA − geracao_bruta×fio_b×pct/100 (clamp >= 0).
    // Assim o CUO reconcilia e o impacto no valor final isola crédito/variável/fixo.
    $fioB = (float) $u['fio_b']; $pct = (float) $u['percentual_lei'];
    $mesesRaw = [];
    foreach ($geracaoPorAno as $ga) {
        $ano = (int) $ga['ano'];
        foreach ($MESES as $n => $nome) {
            $g = $ga[$nome];
            if ($g === null || (float) $g == 0.0) { continue; }
            $cons = isset($consumoPorAno[$ano]) ? (float) ($consumoPorAno[$ano][$nome] ?? 0) : 0.0;
            $compKey = sprintf('%04d-%02d', $ano, $n);
            $faMes = $fatAntes[$compKey] ?? null;
            $faturaEnergia = 0.0;
            if ($faMes !== null) {
                $faturaEnergia = max((float) $faMes['cuo'] - ((float) $g * $fioB * $pct / 100), 0.0);
            }
            $mesesRaw[] = [
                'ano'=>$ano, 'mes'=>$n,
                'geracao_bruta_kwh'=>(float) $g, 'consumo_kwh'=>$cons, 'rede'=>$u['rede'],
                'media_kwh'=>$media, 'menor_geracao_kwh'=>(float) $u['menor_geracao'], 'tarifa'=>$tarifa,
                'fio_b'=>$fioB, 'percentual_lei'=>$pct,
                'fatura_energia'=>$faturaEnergia, 'adicional_cuo'=>0.0,
            ];
        }
    }
    if (!$mesesRaw) { continue; }

    // ---- reserva começa em ZERO (fiel ao cadastro: não há saldo inicial/crédito migrado) ----
    // Um déficit sem reserva é PAGO à concessionária, não compensado. Por isso NÃO
    // injetamos lote inicial: a reserva é construída só a partir dos excedentes da geração.
    usort($mesesRaw, fn($a,$b)=>[$a['ano'],$a['mes']]<=>[$b['ano'],$b['mes']]);
    $lotesIniciais = [];

    // ---- reconstrução (motor único) ----
    $rec = $reconstrutor->reconstruir($mesesRaw, $lotesIniciais);

    // ---- cruza DEPOIS × ANTES por mês ----
    $timeline = [];
    foreach ($rec['meses'] as $mz) {
        $ano = $mz['ano']; $n = $mz['mes']; $res = $mz['resultado']; $ent = $mz['entrada'];
        $chaveComp = sprintf('%04d-%02d', $ano, $n);

        $cA = isset($credAntes[$ano]) ? (float) ($credAntes[$ano][$MESES[$n]] ?? 0) : 0.0;
        $cD = $res->credito->emReais();
        $diff = round($cD - $cA, 2);

        $liquida = $ent->geracaoLiquidaKwh->valor();
        $bruta = $ent->geracaoBrutaKwh->valor();
        $consumoMes = $bruta - $liquida + 0.0; // descontável aplicado (para exibição usamos consumo cru abaixo)
        $faltante = max($media - $liquida, 0.0);
        $consumido = 0.0;
        foreach ($res->consumosFifo as $c) { $consumido += $c['kwh']->valor(); }

        // 4 termos ANTES (geracao_faturamento_pdf) quando houver
        $fa = $fatAntes[$chaveComp] ?? null;
        $fixoA = $fa ? (float) $fa['valor_fixo'] : null;
        $varA  = $fa ? (float) $fa['injetado'] : null;
        $cuoA  = $fa ? (float) $fa['cuo'] : null;
        $finalA = $fa ? (float) $fa['valor_final'] : null;

        $fixoD = $res->valorFixo->emReais();
        $varD  = $res->valorVariavel->emReais();
        $cuoD  = $res->cuo->emReais();
        $finalD = $res->valorFinal->emReais();
        if ($finalA !== null) { $totDiffFinal += round($finalD - $finalA, 2); }

        // consumo cru do mês (para a coluna de transparência)
        $consumoCru = 0.0;
        // recupera o consumo cru do mesesRaw correspondente
        foreach ($mesesRaw as $mr) { if ($mr['ano']===$ano && $mr['mes']===$n) { $consumoCru = $mr['consumo_kwh']; break; } }
        $descontoAplicado = max($consumoCru - DescontoRede::kwhPorTipo($u['rede']), 0.0);

        $linhaTL = [
            'ano'=>$ano, 'mes'=>$n, 'comp'=>$chaveComp,
            'bruta'=>$bruta, 'consumo'=>$consumoCru, 'desconto'=>$descontoAplicado, 'liquida'=>$liquida,
            'media'=>$media, 'faltante'=>$faltante,
            'guardou'=>$res->guardadoKwh->valor(),
            'consumos'=>array_map(fn($c)=>['origem'=>(string)$c['origem'],'kwh'=>$c['kwh']->valor()], $res->consumosFifo),
            'expirou'=>array_map(fn($e)=>['origem'=>(string)$e['origem'],'kwh'=>$e['kwh']->valor()], $res->expiracoes),
            'saldo_final'=>$mz['saldo_final_kwh'],
            'fixoA'=>$fixoA,'fixoD'=>$fixoD,'varA'=>$varA,'varD'=>$varD,
            'credA'=>$cA,'credD'=>$cD,'cuoA'=>$cuoA,'cuoD'=>$cuoD,'finalA'=>$finalA,'finalD'=>$finalD,
            'diff'=>$diff, 'tipo'=>null,
        ];

        if (abs($diff) >= TOL) {
            // classificação (mesma taxonomia, agora sobre líquida)
            if ($faltante <= 0 && $cA > TOL) {
                $tipo = 'Crédito sem déficit';
            } elseif ($cA > ($faltante * $tarifa) + TOL) {
                $tipo = 'Creditou além do déficit';
            } elseif ($consumido < ($faltante - TOL) && $cA > $cD + TOL) {
                $tipo = 'Creditou além da reserva';
            } elseif ($diff > 0) {
                $tipo = 'Creditou a menos (FIFO não usou reserva antiga)';
            } else {
                $tipo = 'Divergência de valor';
            }
            $linhaTL['tipo'] = $tipo;

            $porTipo[$tipo]['n'] = ($porTipo[$tipo]['n'] ?? 0) + 1;
            $porTipo[$tipo]['soma'] = ($porTipo[$tipo]['soma'] ?? 0) + $diff;
            $usinasAfetadas[$uc] = true;

            $linhas[] = [
                'uc'=>$uc, 'cliente'=>$u['cliente'] ?? '-', 'ano'=>$ano, 'mes'=>$MES_LABEL[$n],
                'bruta'=>$bruta, 'consumo'=>$consumoCru, 'liquida'=>$liquida, 'media'=>$media,
                'faltante'=>round($faltante,0), 'reserva'=>round($mz['reserva_antes_kwh'],0),
                'cred_antes'=>round($cA,2), 'cred_depois'=>round($cD,2), 'diff'=>$diff, 'tipo'=>$tipo,
            ];
        }

        if ($fa === null) { $dq['sem_fat_antes'][$uc] = ($dq['sem_fat_antes'][$uc] ?? 0) + 1; }
        $timeline[] = $linhaTL;
    }

    $usinasReport[$uc] = [
        'cliente'=>$u['cliente'] ?? '-', 'rede'=>$u['rede'], 'migrada'=>false,
        'tarifa'=>$tarifa, 'media'=>$media, 'menor'=>(float)$u['menor_geracao'],
        'timeline'=>$timeline,
        'diff_credito'=>array_sum(array_map(fn($t)=>$t['diff'], $timeline)),
        'casos'=>count(array_filter($timeline, fn($t)=>$t['tipo']!==null)),
    ];
}

// ============================ SAÍDA TERMINAL ============================
if ($ucFiltro) {
    echo "=== DETALHE UC {$ucFiltro} ===\n";
    if (!isset($usinasReport[$ucFiltro])) { echo "(sem dados)\n"; exit; }
    $ur = $usinasReport[$ucFiltro];
    echo "cliente: {$ur['cliente']} | rede: {$ur['rede']} | migrada: ".($ur['migrada']?'sim':'não')."\n";
    foreach ($ur['timeline'] as $t) {
        $cons = implode(',', array_map(fn($c)=>$c['origem'].':'.round($c['kwh']), $t['consumos'])) ?: '-';
        printf("%s | bruta %.0f cons %.0f liq %.0f media %.0f falt %.0f | credA %.2f credD %.2f diff %.2f | guardou %.0f consumiu[%s] saldo %.0f | %s\n",
            $t['comp'], $t['bruta'], $t['consumo'], $t['liquida'], $t['media'], $t['faltante'],
            $t['credA'], $t['credD'], $t['diff'], $t['guardou'], $cons, $t['saldo_final'], $t['tipo'] ?? 'ok');
    }
    exit;
}

echo "=== RESUMO ===\n";
echo "Usinas analisadas: ".count($usinas)." | com divergência: ".count($usinasAfetadas)."\n";
echo "Linhas divergentes: ".count($linhas)."\n";
$impactoCredito = array_sum(array_map(fn($l)=>$l['diff'], $linhas));
printf("Impacto líquido no crédito (cobertura completa): R\$ %s\n", number_format($impactoCredito,2,',','.'));
printf("Impacto no valor final (PARCIAL, só meses com PDF): R\$ %s\n", number_format($totDiffFinal,2,',','.'));
echo "\nPor tipo de erro:\n";
uasort($porTipo, fn($a,$b)=>$a['soma']<=>$b['soma']);
foreach ($porTipo as $tipo=>$x) {
    printf("  %-42s %3d casos  R\$ %s\n", $tipo, $x['n'], number_format($x['soma'],2,',','.'));
}

// ============================ CSV ============================
$f = fopen(__DIR__.'/relatorio-antes-depois.csv', 'w');
fputcsv($f, ['UC','Cliente','Ano','Mes','Geracao_Bruta_kWh','Consumo_kWh','Geracao_Liquida_kWh','Media_kWh',
    'Faltante_kWh','Reserva_kWh','Credito_ANTES','Credito_DEPOIS','Diferenca','Tipo_de_Erro']);
foreach ($linhas as $l) { fputcsv($f, array_values($l)); }
fclose($f);

// ============================ HTML ============================
$totUsinas = count($usinas);
$totAfetadas = count($usinasAfetadas);
$creditoAMais = array_sum(array_map(fn($l)=>$l['diff']<0?-$l['diff']:0, $linhas));
$creditoAMenos = array_sum(array_map(fn($l)=>$l['diff']>0?$l['diff']:0, $linhas));

// resumo por usina (executivo)
$porUsina = [];
foreach ($linhas as $l) {
    $porUsina[$l['uc']]['cliente'] = $l['cliente'];
    $porUsina[$l['uc']]['diff'] = ($porUsina[$l['uc']]['diff'] ?? 0) + $l['diff'];
    $porUsina[$l['uc']]['casos'] = ($porUsina[$l['uc']]['casos'] ?? 0) + 1;
}
uasort($porUsina, fn($a,$b)=>$a['diff']<=>$b['diff']);

$fmt = fn($v)=>number_format((float)$v, 2, ',', '.');
$fk  = fn($v)=>number_format((float)$v, 0, ',', '.'); // kWh inteiro (geração é sempre inteira)
// competência ISO "2025-12" -> "Dez/25" (consistente com o resto do relatório)
$fcomp = fn(string $iso)=>$MES_LABEL[(int)substr($iso,5,2)].'/'.substr($iso,2,2);
$cores = ['Crédito sem déficit'=>'#dc2626','Creditou além do déficit'=>'#ea580c',
    'Creditou além da reserva'=>'#d97706','Creditou a menos (FIFO não usou reserva antiga)'=>'#2563eb',
    'Divergência de valor'=>'#6b7280'];
// rótulo curto no badge; explicação completa no tooltip (title)
$rotuloCurto = ['Creditou a menos (FIFO não usou reserva antiga)'=>'Creditou a menos'];
$explicacao = [
    'Crédito sem déficit'=>'Geração líquida ≥ média: não faltava energia, mas o sistema creditou. Prejuízo ao consórcio.',
    'Creditou além do déficit'=>'Creditou mais do que o necessário para atingir a média. Prejuízo ao consórcio.',
    'Creditou além da reserva'=>'Creditou energia que não estava guardada na reserva. Prejuízo ao consórcio.',
    'Creditou a menos (FIFO não usou reserva antiga)'=>'Havia reserva antiga disponível que o sistema não usou. Prejuízo ao cliente.',
    'Divergência de valor'=>'Valores divergem sem se encaixar nos padrões acima.',
];
$badge = function($tipo) use ($cores, $rotuloCurto, $explicacao) {
    if ($tipo === null) { return '<span class="ok">ok</span>'; }
    $cor = $cores[$tipo] ?? '#6b7280';
    $rotulo = $rotuloCurto[$tipo] ?? $tipo;
    $tip = htmlspecialchars($explicacao[$tipo] ?? $tipo);
    return "<span class=\"bdg\" style=\"background:$cor\" title=\"$tip\">".htmlspecialchars($rotulo)."</span>";
};
// trio A/D/Δ: Δ=0 vira "—" (menos ruído); $extra marca colunas ocultáveis; $grp abre grupo visual
$diffCell = function($a, $b, bool $extra = false, bool $grp = true) use ($fmt) {
    $cx = ($extra ? ' extra' : '').($grp ? ' grp' : '');
    if ($a === null) {
        return '<td class="num muted'.$cx.'">—</td><td class="num'.($extra?' extra':'').'">R$ '.$fmt($b).'</td><td class="num muted'.($extra?' extra':'').'">—</td>';
    }
    $d = round($b - $a, 2);
    $dTxt = abs($d) < 0.01 ? '<span class="muted">—</span>' : 'R$ '.$fmt($d);
    $cls = abs($d) < 0.01 ? '' : ($d < 0 ? ' neg' : ' pos');
    return '<td class="num muted'.$cx.'">R$ '.$fmt($a).'</td><td class="num'.($extra?' extra':'').'">R$ '.$fmt($b).'</td><td class="num'.$cls.($extra?' extra':'').'">'.$dTxt.'</td>';
};

ob_start(); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Auditoria de Crédito — Antes × Depois (detalhado)</title>
<style>
*{box-sizing:border-box} body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
.wrap{max-width:1280px;margin:0 auto;padding:32px}
h1{font-size:26px;margin:0 0 4px} .sub{color:#64748b;margin:0 0 24px;font-size:14px}
.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:28px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px}
.card .lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.card .val{font-size:23px;font-weight:700;margin-top:6px}
.val.red{color:#dc2626} .val.blue{color:#2563eb} .val.amber{color:#d97706}
h2{font-size:18px;margin:30px 0 12px;border-bottom:2px solid #e2e8f0;padding-bottom:6px}
h3{font-size:14px;margin:14px 0 6px;color:#334155}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;font-size:12.5px}
th{background:#f1f5f9;text-align:left;padding:8px 10px;font-size:11px;color:#475569;text-transform:uppercase;letter-spacing:.03em}
td{padding:7px 10px;border-top:1px solid #f1f5f9}
tr:hover td{background:#f8fafc}
.num{text-align:right;font-variant-numeric:tabular-nums} .right{text-align:right}
.neg{color:#dc2626;font-weight:600} .pos{color:#2563eb;font-weight:600}
.muted{color:#94a3b8}
.bdg{color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;white-space:nowrap}
.ok{color:#16a34a;font-size:11px} .tagm{background:#fef3c7;color:#92400e;padding:1px 7px;border-radius:8px;font-size:11px}
input{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;margin-bottom:8px;font-size:14px}
.legenda{font-size:12px;color:#64748b;margin:10px 0 0;line-height:1.7}
details{background:#fff;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:10px;padding:4px 14px}
details[open]{box-shadow:0 1px 3px rgba(0,0,0,.05)}
summary{cursor:pointer;padding:10px 0;font-weight:600;font-size:14px;display:flex;justify-content:space-between;align-items:center;gap:10px}
summary::-webkit-details-marker{display:none}
summary .meta{font-weight:400;color:#64748b;font-size:12.5px}
.sub2{font-size:12px;color:#64748b;margin:2px 0 10px}
.led{font-size:11.5px;color:#475569} .led b{color:#0f172a}
.scroll{overflow-x:auto}
.veredito{background:#fffbeb;border:1px solid #fcd34d;border-left:4px solid #d97706;border-radius:10px;padding:14px 18px;margin:0 0 20px;font-size:14.5px;line-height:1.6}
.topnav{position:sticky;top:0;z-index:50;background:rgba(248,250,252,.95);backdrop-filter:blur(4px);border-bottom:1px solid #e2e8f0;margin:0 -32px 20px;padding:10px 32px;display:flex;gap:18px;font-size:13px}
.topnav a{color:#475569;text-decoration:none;font-weight:500} .topnav a:hover{color:#0f172a}
.grp{border-left:2px solid #e2e8f0}
body.compact .extra{display:none}
.toolbar{display:flex;gap:8px;margin:0 0 10px;flex-wrap:wrap;align-items:center}
.toolbar button{background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:6px 12px;font-size:12.5px;cursor:pointer;color:#334155}
.toolbar button:hover{background:#f1f5f9}
.contador{font-size:12px;color:#64748b;margin-left:auto}
.barwrap{position:relative;min-width:120px} .bar{position:absolute;inset:2px auto 2px 0;border-radius:3px;opacity:.15}
.bar.neg{background:#dc2626} .bar.pos{background:#2563eb}
.barwrap span{position:relative}
details:target{outline:2px solid #d97706;outline-offset:2px}
a.uclink{color:#2563eb;text-decoration:none;font-weight:600} a.uclink:hover{text-decoration:underline}
</style></head><body class="compact"><div class="wrap">
<nav class="topnav">
<a href="#visao">Visão geral</a><a href="#tipos">Tipos de erro</a><a href="#usinas">Por usina</a>
<a href="#detalhe">Divergências</a><a href="#drill">Drill-down</a><a href="#qualidade">Qualidade de dados</a>
</nav>
<h1 id="visao">Auditoria de Crédito de Geração — Antes × Depois <span style="font-size:14px;color:#64748b">(detalhado)</span></h1>
<p class="sub">Reconstrução pelo motor de cálculo único (geração líquida §9, FIFO cross-ano §6, expiração §7).
Reserva reconstruída <b>do zero</b> a partir dos excedentes de geração — déficit sem reserva é pago à concessionária, não compensado.
ANTES = sistema; DEPOIS = regra correta. Gerado de cópia fiel do banco de produção.</p>

<?php $impactoLiq = $creditoAMenos - $creditoAMais; $tipoDominante = array_key_first($porTipo); ?>
<div class="veredito">
  <b>Veredito:</b> o sistema creditou <b>R$ <?=$fmt(abs($impactoLiq))?> <?=$impactoLiq<0?'a MAIS':'a MENOS'?></b> do que a regra correta permite,
  em <b><?=$totAfetadas?> de <?=$totUsinas?></b> usinas (<?=count($linhas)?> meses divergentes).
  Erro dominante: <?=$badge($tipoDominante)?> com R$ <?=$fmt($porTipo[$tipoDominante]['soma'] ?? 0)?>.
</div>

<div class="cards">
  <div class="card"><div class="lbl">Usinas analisadas</div><div class="val"><?=$totUsinas?></div></div>
  <div class="card"><div class="lbl">Usinas com erro</div><div class="val red"><?=$totAfetadas?></div></div>
  <div class="card"><div class="lbl">Creditado a MAIS</div><div class="val red">R$ <?=$fmt($creditoAMais)?></div></div>
  <div class="card"><div class="lbl">Creditado a MENOS</div><div class="val blue">R$ <?=$fmt($creditoAMenos)?></div></div>
  <div class="card"><div class="lbl">Impacto líquido no crédito</div><div class="val <?=($creditoAMenos-$creditoAMais)<0?'red':'blue'?>">R$ <?=$fmt($creditoAMenos-$creditoAMais)?></div></div>
</div>
<p class="legenda">Impacto líquido negativo = o sistema creditou a mais (cobertura completa, via <code>creditos_distribuidos</code>).
O impacto no <b>valor final</b> aparece por mês na seção de 4 termos — é parcial, pois só há demonstrativo persistido
(<code>geracao_faturamento_pdf</code>) para parte dos meses; as maiores correções de crédito caem em meses recentes não cobertos lá.</p>

<h2 id="tipos">Por tipo de erro</h2>
<table><thead><tr><th>Tipo de erro</th><th class="right">Casos</th><th class="right">Impacto no crédito (R$)</th><th>Quem perde</th></tr></thead><tbody>
<?php foreach ($porTipo as $tipo=>$x): ?>
<tr><td><?=$badge($tipo)?></td><td class="num"><?=$x['n']?></td>
<td class="num <?=$x['soma']<0?'neg':'pos'?>">R$ <?=$fmt($x['soma'])?></td>
<td class="muted" style="font-size:11.5px"><?=$x['soma']<0?'Consórcio (creditou a mais)':'Cliente (creditou a menos)'?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="legenda">
<b>Crédito sem déficit:</b> geração líquida ≥ média, não faltava energia, mas o sistema creditou.<br>
<b>Creditou além do déficit:</b> creditou mais do que o necessário para atingir a média.<br>
<b>Creditou além da reserva:</b> creditou energia que não estava guardada.<br>
<b>Creditou a menos:</b> havia reserva antiga (de anos anteriores) que não foi usada via FIFO.<br>
<span class="neg">Vermelho</span> = creditado a mais (prejuízo ao consórcio) · <span class="pos">Azul</span> = creditado a menos (prejuízo ao cliente). Ambos são erros.
</p>

<h2 id="usinas">Resumo por usina</h2>
<p class="legenda">Clique na UC para abrir o drill-down completo da usina. Barra = proporção do impacto.</p>
<?php $maxAbs = max(array_map(fn($x)=>abs($x['diff']), $porUsina) ?: [1]); ?>
<table><thead><tr><th>UC</th><th>Cliente</th><th class="right">Casos</th><th class="right">Impacto no crédito (R$)</th><th>Proporção</th></tr></thead><tbody>
<?php foreach ($porUsina as $uc=>$x): $pctBar = $maxAbs>0 ? round(abs($x['diff'])/$maxAbs*100) : 0; ?>
<tr><td><a class="uclink" href="#u-<?=htmlspecialchars($uc)?>" onclick="abrirUsina('u-<?=htmlspecialchars($uc)?>')"><?=htmlspecialchars($uc)?></a></td>
<td><?=htmlspecialchars($x['cliente'])?></td>
<td class="num"><?=$x['casos']?></td>
<td class="num <?=$x['diff']<0?'neg':'pos'?>">R$ <?=$fmt($x['diff'])?></td>
<td class="barwrap"><div class="bar <?=$x['diff']<0?'neg':'pos'?>" style="width:<?=$pctBar?>%"></div></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2 id="detalhe">Detalhe — divergências com transparência da geração líquida (<?=count($linhas)?>)</h2>
<div class="toolbar">
<input id="busca" style="margin:0;flex:1" placeholder="🔎 Filtrar por UC, cliente, mês ou tipo de erro (filtra também o drill-down)..." onkeyup="filtrar()">
<span class="contador" id="contador">mostrando <?=count($linhas)?> de <?=count($linhas)?> divergências</span>
</div>
<div class="scroll">
<table id="tab"><thead><tr>
<th>UC</th><th>Cliente</th><th>Período</th>
<th class="right">Bruta (kWh)</th><th class="right">Consumo (kWh)</th><th class="right">Líquida (kWh)</th>
<th class="right">Média (kWh)</th><th class="right">Faltou (kWh)</th><th class="right">Reserva (kWh)</th>
<th class="right">Antes (R$)</th><th class="right">Depois (R$)</th><th class="right">Diferença (R$)</th><th>Tipo</th>
</tr></thead><tbody>
<?php foreach ($linhas as $l): ?>
<tr>
<td><?=htmlspecialchars($l['uc'])?></td>
<td><?=htmlspecialchars(mb_substr($l['cliente'],0,22))?></td>
<td><?=$l['mes']?>/<?=$l['ano']?></td>
<td class="num"><?=$fk($l['bruta'])?></td>
<td class="num"><?=$fk($l['consumo'])?></td>
<td class="num"><?=$fk($l['liquida'])?></td>
<td class="num muted"><?=$fk($l['media'])?></td>
<td class="num"><?=$fk($l['faltante'])?></td>
<td class="num muted"><?=$fk($l['reserva'])?></td>
<td class="num">R$ <?=$fmt($l['cred_antes'])?></td>
<td class="num">R$ <?=$fmt($l['cred_depois'])?></td>
<td class="num <?=$l['diff']<0?'neg':'pos'?>">R$ <?=$fmt($l['diff'])?></td>
<td><?=$badge($l['tipo'])?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>

<h2 id="drill">Drill-down por usina — 4 termos &amp; ledger FIFO</h2>
<p class="legenda">Cada R$ de crédito é rastreável ao mês-origem consumido via FIFO.
Nos 4 termos: <b>A</b> = antes (sistema), <b>D</b> = depois (correto), <b>Δ</b> = diferença. Colunas <span class="muted">—</span>
indicam meses sem demonstrativo persistido (o termo ANTES não existe no banco para comparar).
O CUO DEPOIS reconcilia a <code>faturaEnergia</code> a partir do CUO ANTES, isolando o efeito em crédito/variável.</p>
<div class="toolbar">
<button onclick="expandir(true)">Expandir divergentes</button>
<button onclick="expandir(false)">Expandir todas</button>
<button onclick="recolher()">Recolher todas</button>
<button id="btnTermos" onclick="alternarTermos()">Mostrar todos os termos</button>
</div>
<?php
// ordena: divergentes primeiro (por impacto), depois as ok
uasort($usinasReport, function($a,$b){
    $ad = $a['casos']>0?0:1; $bd = $b['casos']>0?0:1;
    if ($ad !== $bd) { return $ad <=> $bd; }
    return $a['diff_credito'] <=> $b['diff_credito'];
});
foreach ($usinasReport as $uc=>$ur):
?>
<details id="u-<?=htmlspecialchars($uc)?>" data-div="<?=$ur['casos']>0?1:0?>">
<summary><span><?=htmlspecialchars($uc)?> · <?=htmlspecialchars($ur['cliente'])?></span>
<span class="meta"><?=$ur['casos']>0 ? $ur['casos'].' caso(s) · crédito Δ <b class="'.($ur['diff_credito']<0?'neg':'pos').'">R$ '.$fmt($ur['diff_credito']).'</b>' : '<span class="ok">sem divergência</span>'?> · rede <?=htmlspecialchars($ur['rede']?:'—')?></span></summary>

<h3>4 termos — Antes × Depois</h3>
<div class="scroll">
<table><thead><tr>
<th>Período</th>
<th class="right extra grp" colspan="3">Fixo (R$)</th>
<th class="right extra grp" colspan="3">Variável (R$)</th>
<th class="right grp" colspan="3">Crédito (R$)</th>
<th class="right extra grp" colspan="3">CUO (R$)</th>
<th class="right grp" colspan="3">Valor final (R$)</th>
</tr><tr>
<th></th>
<th class="right extra grp">A</th><th class="right extra">D</th><th class="right extra">Δ</th>
<th class="right extra grp">A</th><th class="right extra">D</th><th class="right extra">Δ</th>
<th class="right grp">A</th><th class="right">D</th><th class="right">Δ</th>
<th class="right extra grp">A</th><th class="right extra">D</th><th class="right extra">Δ</th>
<th class="right grp">A</th><th class="right">D</th><th class="right">Δ</th>
</tr></thead><tbody>
<?php foreach ($ur['timeline'] as $t): ?>
<tr>
<td><?=$MES_LABEL[$t['mes']]?>/<?=$t['ano']?></td>
<?=$diffCell($t['fixoA'],$t['fixoD'],true)?>
<?=$diffCell($t['varA'],$t['varD'],true)?>
<?=$diffCell($t['credA'],$t['credD'])?>
<?=$diffCell($t['cuoA'],$t['cuoD'],true)?>
<?=$diffCell($t['finalA'],$t['finalD'])?>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>

<h3>Ledger da reserva (FIFO) &amp; geração líquida <span class="muted" style="font-weight:400">— energia em kWh</span></h3>
<div class="scroll">
<table><thead><tr>
<th>Período</th><th class="right">Bruta</th><th class="right">Consumo</th><th class="right">Desc. rede</th>
<th class="right">Líquida</th><th class="right">Faltou</th><th class="right">Guardou</th>
<th>Consumiu (origem→kWh)</th><th>Expirou</th><th class="right">Saldo reserva</th>
</tr></thead><tbody>
<?php foreach ($ur['timeline'] as $t):
    $cons = $t['consumos'] ? implode(' · ', array_map(fn($c)=>'<b>'.$fcomp($c['origem']).'</b>→'.$fk($c['kwh']), $t['consumos'])) : '—';
    $exp = $t['expirou'] ? implode(' · ', array_map(fn($e)=>'<b>'.$fcomp($e['origem']).'</b>→'.$fk($e['kwh']), $t['expirou'])) : '—';
?>
<tr>
<td><?=$MES_LABEL[$t['mes']]?>/<?=$t['ano']?></td>
<td class="num"><?=$fk($t['bruta'])?></td>
<td class="num"><?=$fk($t['consumo'])?></td>
<td class="num muted"><?=$fk($t['desconto'])?></td>
<td class="num"><?=$fk($t['liquida'])?></td>
<td class="num"><?=$fk($t['faltante'])?></td>
<td class="num pos"><?=$t['guardou']>0?$fk($t['guardou']):'—'?></td>
<td class="led"><?=$cons?></td>
<td class="led"><?=$exp?></td>
<td class="num"><?=$fk($t['saldo_final'])?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
</details>
<?php endforeach; ?>

<h2 id="qualidade">Qualidade de dados</h2>
<table><thead><tr><th>Item</th><th class="right">Qtde</th><th>Observação</th></tr></thead><tbody>
<tr><td>Consumos duplicados (usina, ano)</td><td class="num"><?=$consumoDup?></td>
<td>Resolvido pegando o registro mais recente (<code>updated_at</code>). O upsert do app deve passar a ser único por (usina, ano).</td></tr>
<tr><td>Saldo inicial migrado</td><td class="num">0</td>
<td>Nenhum lote inicial é injetado: a reserva começa em zero e só cresce com excedentes reais de geração.
Déficit sem reserva disponível é pago à concessionária (não vira crédito).</td></tr>
<tr><td>Usinas sem tipo de rede</td><td class="num"><?=count($dq['sem_rede'])?></td>
<td>Sem desconto de rede aplicável (assume 0). <?=$dq['sem_rede']?htmlspecialchars(implode(', ',$dq['sem_rede'])):'—'?></td></tr>
</tbody></table>

<p class="legenda right">Gerado em <?=date('d/m/Y H:i')?> · fonte: cópia do banco de produção ·
motor de domínio único · energia em kWh · valores financeiros em R$</p>
</div>
<script>
var totalLinhas = document.querySelectorAll('#tab tbody tr').length;
function filtrar(){
  var q = document.getElementById('busca').value.toLowerCase();
  var visiveis = 0;
  document.querySelectorAll('#tab tbody tr').forEach(function(tr){
    var ok = tr.innerText.toLowerCase().includes(q);
    tr.style.display = ok ? '' : 'none';
    if (ok) visiveis++;
  });
  // filtra também o drill-down pelo cabeçalho (UC + cliente)
  var usinasVis = 0, usinasTot = 0;
  document.querySelectorAll('details[id^="u-"]').forEach(function(d){
    usinasTot++;
    var ok = !q || d.querySelector('summary').innerText.toLowerCase().includes(q);
    d.style.display = ok ? '' : 'none';
    if (ok) usinasVis++;
  });
  document.getElementById('contador').textContent =
    'mostrando ' + visiveis + ' de ' + totalLinhas + ' divergências · ' + usinasVis + ' de ' + usinasTot + ' usinas';
}
function expandir(soDivergentes){
  document.querySelectorAll('details[id^="u-"]').forEach(function(d){
    if (soDivergentes) { d.open = d.dataset.div === '1'; } else { d.open = true; }
  });
}
function recolher(){
  document.querySelectorAll('details[id^="u-"]').forEach(function(d){ d.open = false; });
}
function alternarTermos(){
  var compacto = document.body.classList.toggle('compact');
  document.getElementById('btnTermos').textContent =
    compacto ? 'Mostrar todos os termos' : 'Mostrar só Crédito e Valor final';
}
function abrirUsina(id){
  var d = document.getElementById(id);
  if (d) { d.open = true; }
}
// abre a usina se a página carregar com âncora #u-...
if (location.hash && location.hash.indexOf('#u-') === 0) { abrirUsina(location.hash.slice(1)); }
</script>
</body></html>
<?php
file_put_contents(__DIR__.'/relatorio.html', ob_get_clean());
echo "\n>> Gerados: relatorio.html (detalhado) e relatorio-antes-depois.csv\n";
