<?php
/**
 * Extrai o "Antes" (valor que estava no sistema original) do 1º dump (energia_antes):
 * geracao_faturamento_pdf.valor_final por (uc, competência).
 * Gera storage/reconstrucao/auditoria-antes.csv (uc,competencia,valor).
 *
 * Uso (contra o dump energia_antes restaurado num Postgres):
 *   DB_HOST=... DB_PORT=... DB_NAME=energia_antes DB_USER=... DB_PASS=... \
 *     php storage/reconstrucao/extrair_antes.php
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'energia_antes';
$user = getenv('DB_USER') ?: 'app';
$pass = getenv('DB_PASS') ?: 'app';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = "SELECT u.uc,
               to_char(date_trunc('month', g.competencia), 'YYYY-MM') AS ym,
               round(g.valor_final::numeric, 2) AS valor
        FROM geracao_faturamento_pdf g
        JOIN usina u ON u.usi_id = g.usi_id
        ORDER BY u.uc, g.competencia";

$saida = __DIR__ . '/auditoria-antes.csv';
$f = fopen($saida, 'w');
fputcsv($f, ['uc', 'competencia', 'valor']);
$n = 0;
foreach ($pdo->query($sql) as $row) {
    fputcsv($f, [$row['uc'], $row['ym'], number_format((float) $row['valor'], 2, '.', '')]);
    $n++;
}
fclose($f);
echo "Geradas {$n} linhas: {$saida}\n";
