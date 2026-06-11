<?php
/**
 * Reconstrução auditável do faturamento de geração (relatório ANTES × DEPOIS).
 *
 * Roda contra o STAGING (cópia de produção), SEM tocar em produção e SEM alterar o staging.
 * Reconstrói a reserva/crédito de cada usina a partir da GERAÇÃO REAL mês a mês, aplicando:
 *   - Crédito guardado quando geração >= média (excedente)
 *   - Consumo FIFO cross-ano (mais antigo primeiro) quando geração < média
 *   - Expiração em 180 dias do que sobrou sem uso
 * Classifica cada divergência por TIPO de erro e gera relatório HTML + CSV + resumo.
 *
 * Uso: php reconstruir.php          -> gera relatorio.html, relatorio-antes-depois.csv e resumo no terminal
 *      php reconstruir.php --uc=X   -> detalha uma usina
 */

const DB_HOST='127.0.0.1'; const DB_PORT='5440'; const DB_NAME='energia_assinatura';
const DB_USER='postgres';  const DB_PASS='staging';
const PRAZO_EXPIRACAO_DIAS=180;
const TOL=0.01;

$MESES=[1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
        7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$MES_LABEL=[1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];

$opts=getopt('',['uc::']);
$ucFiltro=$opts['uc']??null;

$pdo=new PDO('pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME,DB_USER,DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$sql="SELECT u.usi_id,u.uc,cli.nome AS cliente,
             c.valor_kwh,c.valor_fixo,d.media,d.menor_geracao
      FROM usina u
      JOIN comercializacao c ON c.com_id=u.com_id
      JOIN dados_geracao d ON d.dger_id=u.dger_id
      LEFT JOIN cliente cli ON cli.cli_id=u.cli_id";
if($ucFiltro){$sql.=" WHERE u.uc=".$pdo->quote($ucFiltro);}
$usinas=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$linhas=[];               // todas as divergências
$totDiff=0.0;
$porTipo=[];              // contagem e soma por tipo de erro
$usinasAfetadas=[];

foreach($usinas as $u){
    $usiId=(int)$u['usi_id']; $kwh=(float)$u['valor_kwh']; $media=(float)$u['media'];

    // geração real por ano (cronológico)
    $st=$pdo->prepare("SELECT dgru.ano,dgr.* FROM dados_geracao_real_usina dgru
        JOIN dados_geracao_real dgr ON dgr.dgr_id=dgru.dgr_id
        WHERE dgru.usi_id=:u ORDER BY dgru.ano");
    $st->execute([':u'=>$usiId]);
    $geracaoPorAno=$st->fetchAll(PDO::FETCH_ASSOC);
    if(!$geracaoPorAno) continue;

    // crédito ANTES (sistema)
    $st=$pdo->prepare("SELECT cdu.ano,cd.* FROM creditos_distribuidos_usina cdu
        JOIN creditos_distribuidos cd ON cd.cd_id=cdu.cd_id WHERE cdu.usi_id=:u");
    $st->execute([':u'=>$usiId]);
    $credAntes=[]; foreach($st as $r){$credAntes[(int)$r['ano']]=$r;}

    // timeline cronológica
    $timeline=[];
    foreach($geracaoPorAno as $ga){$ano=(int)$ga['ano'];
        foreach($MESES as $n=>$nome){$g=$ga[$nome];
            if($g===null||(float)$g==0.0)continue;
            $timeline[]=['ano'=>$ano,'mes'=>$n,'nome'=>$nome,'geracao'=>(float)$g];}}
    usort($timeline,fn($a,$b)=>[$a['ano'],$a['mes']]<=>[$b['ano'],$b['mes']]);

    // reconstrução FIFO
    $reserva=[]; $credDepois=[]; $detalhe=[];
    foreach($timeline as $t){
        $refFim=(new DateTime(sprintf('%04d-%02d-01',$t['ano'],$t['mes'])))->modify('last day of this month');
        // expira vencidos antes de consumir
        foreach($reserva as &$lote){
            if($lote['saldo']<=0)continue;
            if(new DateTime($lote['venc'])<$refFim){$lote['saldo']=0.0;}
        } unset($lote);

        if($t['geracao']>=$media){
            $exc=$t['geracao']-$media;
            if($exc>0){$venc=(new DateTime(sprintf('%04d-%02d-01',$t['ano'],$t['mes'])))
                ->modify('+'.PRAZO_EXPIRACAO_DIAS.' days')->format('Y-m-d');
                $reserva[]=['ano'=>$t['ano'],'mes'=>$t['mes'],'saldo'=>$exc,'venc'=>$venc];}
            $credDepois[$t['ano']][$t['mes']]=0.0;
            $detalhe[$t['ano']][$t['mes']]=['faltante'=>0,'consumido'=>0,'guardou'=>$exc,'saldo_disp'=>0];
        }else{
            $falta=$media-$t['geracao']; $saldoDisp=array_sum(array_column($reserva,'saldo'));
            $cons=0;
            foreach($reserva as &$lote){if($falta<=0)break;if($lote['saldo']<=0)continue;
                $r=min($lote['saldo'],$falta);$lote['saldo']-=$r;$falta-=$r;$cons+=$r;} unset($lote);
            $credDepois[$t['ano']][$t['mes']]=$cons*$kwh;
            $detalhe[$t['ano']][$t['mes']]=['faltante'=>$media-$t['geracao'],'consumido'=>$cons,'guardou'=>0,'saldo_disp'=>$saldoDisp];
        }
    }

    // compara e CLASSIFICA
    foreach($geracaoPorAno as $ga){$ano=(int)$ga['ano'];
        foreach($MESES as $n=>$nome){$g=$ga[$nome];
            if($g===null||(float)$g==0.0)continue;
            $cA=isset($credAntes[$ano])?(float)$credAntes[$ano][$nome]:0.0;
            $cD=$credDepois[$ano][$n]??0.0;
            $diff=round($cD-$cA,2);
            if(abs($diff)<TOL)continue;

            $det=$detalhe[$ano][$n]??['faltante'=>0,'consumido'=>0,'saldo_disp'=>0];
            // classificação do tipo de erro
            if($det['faltante']<=0 && $cA>TOL){
                $tipo='Crédito sem déficit'; // geração >= média mas creditou
            }elseif($cA > ($det['faltante']*$kwh)+TOL){
                $tipo='Creditou além do déficit'; // creditou mais que o que faltava
            }elseif($det['consumido'] < ($det['faltante']-TOL) && $cA>$cD+TOL){
                $tipo='Creditou além da reserva'; // não havia reserva suficiente
            }elseif($diff>0){
                $tipo='Creditou a menos (FIFO não usou reserva antiga)';
            }else{
                $tipo='Divergência de valor';
            }

            $totDiff+=$diff;
            $porTipo[$tipo]['n']=($porTipo[$tipo]['n']??0)+1;
            $porTipo[$tipo]['soma']=($porTipo[$tipo]['soma']??0)+$diff;
            $usinasAfetadas[$u['uc']]=true;

            $linhas[]=[
                'uc'=>$u['uc'],'cliente'=>$u['cliente']??'-','ano'=>$ano,'mes'=>$MES_LABEL[$n],
                'geracao'=>(float)$g,'media'=>$media,'faltante'=>round($det['faltante'],0),
                'saldo_disp'=>round($det['saldo_disp'],0),
                'cred_antes'=>round($cA,2),'cred_depois'=>round($cD,2),'diff'=>$diff,'tipo'=>$tipo,
            ];
        }
    }
}

// ---------- saída terminal (detalhe ou resumo) ----------
if($ucFiltro){
    echo "=== DETALHE UC {$ucFiltro} ===\n";
    foreach($linhas as $l){
        printf("%s %-3s | ger %.0f media %.0f faltante %.0f saldo %.0f | antes %.2f depois %.2f diff %.2f | %s\n",
            $l['ano'],$l['mes'],$l['geracao'],$l['media'],$l['faltante'],$l['saldo_disp'],
            $l['cred_antes'],$l['cred_depois'],$l['diff'],$l['tipo']);
    }
    exit;
}

echo "=== RESUMO ===\n";
echo "Usinas analisadas: ".count($usinas)." | com divergência: ".count($usinasAfetadas)."\n";
echo "Linhas divergentes: ".count($linhas)."\n";
printf("Diferença líquida total (depois-antes): R\$ %s\n", number_format($totDiff,2,',','.'));
echo "\nPor tipo de erro:\n";
uasort($porTipo,fn($a,$b)=>$a['soma']<=>$b['soma']);
foreach($porTipo as $tipo=>$x){
    printf("  %-42s %3d casos  R\$ %s\n",$tipo,$x['n'],number_format($x['soma'],2,',','.'));
}

// ---------- CSV ----------
$f=fopen(__DIR__.'/relatorio-antes-depois.csv','w');
fputcsv($f,['UC','Cliente','Ano','Mes','Geracao_kWh','Media_kWh','Faltante_kWh','Saldo_Reserva_kWh','Credito_ANTES','Credito_DEPOIS','Diferenca','Tipo_de_Erro']);
foreach($linhas as $l) fputcsv($f,array_values($l));
fclose($f);

// ---------- HTML apresentável ----------
$totUsinas=count($usinas); $totAfetadas=count($usinasAfetadas);
$creditoAMais=array_sum(array_map(fn($l)=>$l['diff']<0?-$l['diff']:0,$linhas));
$creditoAMenos=array_sum(array_map(fn($l)=>$l['diff']>0?$l['diff']:0,$linhas));

// agrupa por usina pra visão executiva
$porUsina=[];
foreach($linhas as $l){$porUsina[$l['uc']]['cliente']=$l['cliente'];
    $porUsina[$l['uc']]['diff']=($porUsina[$l['uc']]['diff']??0)+$l['diff'];
    $porUsina[$l['uc']]['casos']=($porUsina[$l['uc']]['casos']??0)+1;}
uasort($porUsina,fn($a,$b)=>$a['diff']<=>$b['diff']);

$fmt=fn($v)=>number_format($v,2,',','.');
$badge=function($tipo){
    $cor=['Crédito sem déficit'=>'#dc2626','Creditou além do déficit'=>'#ea580c',
          'Creditou além da reserva'=>'#d97706','Creditou a menos (FIFO não usou reserva antiga)'=>'#2563eb',
          'Divergência de valor'=>'#6b7280'][$tipo]??'#6b7280';
    return "<span style=\"background:$cor;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;white-space:nowrap\">".htmlspecialchars($tipo)."</span>";
};

ob_start(); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Auditoria de Crédito — Antes × Depois</title>
<style>
*{box-sizing:border-box} body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
.wrap{max-width:1200px;margin:0 auto;padding:32px}
h1{font-size:26px;margin:0 0 4px} .sub{color:#64748b;margin:0 0 24px;font-size:14px}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px}
.card .lbl{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.card .val{font-size:26px;font-weight:700;margin-top:6px}
.val.red{color:#dc2626} .val.blue{color:#2563eb}
h2{font-size:18px;margin:28px 0 12px;border-bottom:2px solid #e2e8f0;padding-bottom:6px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;font-size:13px}
th{background:#f1f5f9;text-align:left;padding:10px 12px;font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:.03em}
td{padding:9px 12px;border-top:1px solid #f1f5f9}
tr:hover td{background:#f8fafc}
.num{text-align:right;font-variant-numeric:tabular-nums}
.neg{color:#dc2626;font-weight:600} .pos{color:#2563eb;font-weight:600}
.muted{color:#94a3b8} .right{text-align:right}
input{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;margin-bottom:8px;font-size:14px}
.legenda{font-size:12px;color:#64748b;margin:10px 0 0;line-height:1.7}
</style></head><body><div class="wrap">
<h1>Auditoria de Crédito de Geração — Antes × Depois</h1>
<p class="sub">Comparação entre o crédito calculado pelo sistema (ANTES) e o crédito reconstruído pela regra correta — FIFO por ordem de geração, limitado ao déficit e à reserva disponível (DEPOIS). Gerado a partir de cópia fiel do banco de produção.</p>

<div class="cards">
  <div class="card"><div class="lbl">Usinas analisadas</div><div class="val"><?=$totUsinas?></div></div>
  <div class="card"><div class="lbl">Usinas com erro</div><div class="val red"><?=$totAfetadas?></div></div>
  <div class="card"><div class="lbl">Creditado a MAIS</div><div class="val red">R$ <?=$fmt($creditoAMais)?></div></div>
  <div class="card"><div class="lbl">Creditado a MENOS</div><div class="val blue">R$ <?=$fmt($creditoAMenos)?></div></div>
</div>

<h2>Por tipo de erro</h2>
<table><thead><tr><th>Tipo de erro</th><th class="right">Casos</th><th class="right">Impacto (R$)</th></tr></thead><tbody>
<?php foreach($porTipo as $tipo=>$x): ?>
<tr><td><?=$badge($tipo)?></td><td class="num"><?=$x['n']?></td>
<td class="num <?=$x['soma']<0?'neg':'pos'?>">R$ <?=$fmt($x['soma'])?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="legenda">
<b>Crédito sem déficit:</b> geração ≥ média, não faltava energia, mas o sistema creditou.<br>
<b>Creditou além do déficit:</b> creditou mais do que o necessário para atingir a média.<br>
<b>Creditou além da reserva:</b> creditou energia que não estava guardada.<br>
<b>Creditou a menos (FIFO):</b> havia reserva antiga (de anos anteriores) que não foi usada.
</p>

<h2>Resumo por usina</h2>
<table><thead><tr><th>UC</th><th>Cliente</th><th class="right">Casos</th><th class="right">Impacto total (R$)</th></tr></thead><tbody>
<?php foreach($porUsina as $uc=>$x): ?>
<tr><td><?=htmlspecialchars($uc)?></td><td><?=htmlspecialchars($x['cliente'])?></td>
<td class="num"><?=$x['casos']?></td>
<td class="num <?=$x['diff']<0?'neg':'pos'?>">R$ <?=$fmt($x['diff'])?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2>Detalhe — todas as divergências (<?=count($linhas)?>)</h2>
<input id="busca" placeholder="🔎 Filtrar por UC, cliente, mês ou tipo de erro..." onkeyup="filtrar()">
<table id="tab"><thead><tr>
<th>UC</th><th>Cliente</th><th>Período</th><th class="right">Geração</th><th class="right">Média</th>
<th class="right">Faltou</th><th class="right">Reserva</th><th class="right">Antes</th><th class="right">Depois</th><th class="right">Diferença</th><th>Tipo</th>
</tr></thead><tbody>
<?php foreach($linhas as $l): ?>
<tr>
<td><?=htmlspecialchars($l['uc'])?></td>
<td><?=htmlspecialchars(mb_substr($l['cliente'],0,22))?></td>
<td><?=$l['mes']?>/<?=$l['ano']?></td>
<td class="num"><?=$fmt($l['geracao'])?></td>
<td class="num muted"><?=$fmt($l['media'])?></td>
<td class="num"><?=$fmt($l['faltante'])?></td>
<td class="num muted"><?=$fmt($l['saldo_disp'])?></td>
<td class="num"><?=$fmt($l['cred_antes'])?></td>
<td class="num"><?=$fmt($l['cred_depois'])?></td>
<td class="num <?=$l['diff']<0?'neg':'pos'?>"><?=$fmt($l['diff'])?></td>
<td><?=$badge($l['tipo'])?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p class="legenda right">Gerado em <?=date('d/m/Y H:i')?> · fonte: cópia do banco de produção · valores em R$</p>
</div>
<script>
function filtrar(){var q=document.getElementById('busca').value.toLowerCase();
document.querySelectorAll('#tab tbody tr').forEach(function(tr){
tr.style.display=tr.innerText.toLowerCase().includes(q)?'':'none';});}
</script>
</body></html>
<?php
file_put_contents(__DIR__.'/relatorio.html',ob_get_clean());
echo "\n>> Gerados: relatorio.html (apresentável) e relatorio-antes-depois.csv\n";
