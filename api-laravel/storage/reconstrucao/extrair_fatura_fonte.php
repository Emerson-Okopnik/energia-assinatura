<?php
/**
 * Extrai a fatura-fonte (derivada do CUO do PDF antigo) do dump pré-correção.
 *   fatura = max(cuo − geracao_kwh × fio_b × percentual_lei / 100, 0)
 * Gera storage/reconstrucao/fatura-fonte.csv (uc,competencia,fatura_energia).
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

$sql = "SELECT u.uc,
               to_char(date_trunc('month', g.competencia), 'YYYY-MM') AS ym,
               GREATEST(g.cuo - g.geracao_kwh * c.fio_b * c.percentual_lei / 100.0, 0) AS fatura
        FROM geracao_faturamento_pdf g
        JOIN usina u ON u.usi_id = g.usi_id
        JOIN comercializacao c ON c.com_id = u.com_id
        ORDER BY u.uc, g.competencia";

$saida = __DIR__ . '/fatura-fonte.csv';
$f = fopen($saida, 'w');
fputcsv($f, ['uc', 'competencia', 'fatura_energia']);

$total = 0;
$comFatura = 0;
foreach ($pdo->query($sql) as $row) {
    $fatura = round((float) $row['fatura'], 2);
    fputcsv($f, [$row['uc'], $row['ym'], number_format($fatura, 2, '.', '')]);
    $total++;
    if ($fatura > 0) {
        $comFatura++;
    }
}
fclose($f);

echo "Geradas {$total} linhas ({$comFatura} com fatura > 0): {$saida}\n";
