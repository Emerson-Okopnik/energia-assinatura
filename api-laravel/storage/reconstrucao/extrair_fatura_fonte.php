<?php
/**
 * Extrai a fatura-fonte (derivada do CUO do PDF antigo) do dump pré-correção.
 *   fatura = max(cuo − geracao_real × fio_b × percentual_lei / 100, 0)
 * Gera storage/reconstrucao/fatura-fonte.csv (uc,competencia,fatura_energia).
 *
 * ATENÇÃO: a geração é lida de dados_geracao_real (mesma fonte que reconstruir.php /
 * motor de domínio), e NÃO do campo geracao_kwh de geracao_faturamento_pdf.
 * Isso garante que a fatura derivada aqui bate exatamente com a derivada na auditoria,
 * eliminando divergências causadas por valores de geração diferentes entre as duas fontes.
 *
 * Uso (contra um Postgres com o dump antigo restaurado):
 *   DB_HOST=... DB_PORT=... DB_NAME=... DB_USER=... DB_PASS=... \
 *     php storage/reconstrucao/extrair_fatura_fonte.php
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'dump_antigo';
$user = getenv('DB_USER') ?: 'app';
$pass = getenv('DB_PASS') ?: 'app';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Geração real é lida de dados_geracao_real (coluna mensal por nome), via join pela
// usina e o ano da competência — mesma lógica de reconstruir.php (dados_geracao_real_usina).
// O CASE converte EXTRACT(MONTH) na coluna correta (janeiro..dezembro).
// fatura = GREATEST(cuo − gen_real × fio_b × pct/100, 0); sem arredondamento no SQL
// para preservar a precisão numérica — o arredondamento a 2dp é feito em PHP, consistente
// com o fato de que cuo já é decimal(12,2) e o campo fatura_energia também é decimal(12,2).
$sql = "
SELECT u.uc,
       to_char(date_trunc('month', g.competencia), 'YYYY-MM') AS ym,
       GREATEST(
           g.cuo - (
               CASE EXTRACT(MONTH FROM g.competencia)
                   WHEN  1 THEN dgr.janeiro
                   WHEN  2 THEN dgr.fevereiro
                   WHEN  3 THEN dgr.marco
                   WHEN  4 THEN dgr.abril
                   WHEN  5 THEN dgr.maio
                   WHEN  6 THEN dgr.junho
                   WHEN  7 THEN dgr.julho
                   WHEN  8 THEN dgr.agosto
                   WHEN  9 THEN dgr.setembro
                   WHEN 10 THEN dgr.outubro
                   WHEN 11 THEN dgr.novembro
                   WHEN 12 THEN dgr.dezembro
               END
           ) * c.fio_b * c.percentual_lei / 100.0,
           0
       ) AS fatura
FROM geracao_faturamento_pdf g
JOIN usina u ON u.usi_id = g.usi_id
JOIN comercializacao c ON c.com_id = u.com_id
JOIN dados_geracao_real_usina dgru
    ON dgru.usi_id = g.usi_id
   AND dgru.ano = EXTRACT(YEAR FROM g.competencia)
JOIN dados_geracao_real dgr ON dgr.dgr_id = dgru.dgr_id
ORDER BY u.uc, g.competencia
";

$saida = __DIR__ . '/fatura-fonte.csv';
$f = fopen($saida, 'w');
fputcsv($f, ['uc', 'competencia', 'fatura_energia']);

$total = 0;
$comFatura = 0;
foreach ($pdo->query($sql) as $row) {
    // 2dp no write: consistente com decimal(12,2) de fatura_energia e cuo no banco.
    $fatura = round((float) $row['fatura'], 2);
    fputcsv($f, [$row['uc'], $row['ym'], number_format($fatura, 2, '.', '')]);
    $total++;
    if ($fatura > 0) {
        $comFatura++;
    }
}
fclose($f);

echo "Geradas {$total} linhas ({$comFatura} com fatura > 0): {$saida}\n";
