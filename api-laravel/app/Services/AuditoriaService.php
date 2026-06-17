<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas read-only da auditoria: junta o baseline (Antes/Pago) com o valor Atual
 * (geracao_faturamento_pdf.valor_final). NÃO recalcula nada (motor é da Fase 3).
 * Inconclusivo = fatura_energia atual 0/nula OU sem atual.
 *
 * A query usa UNION dos dois lados em vez de FULL OUTER JOIN para garantir
 * compatibilidade com SQLite (testes) e PostgreSQL (produção).
 */
final class AuditoriaService
{
    private const TOL = 0.01;

    /** @return array{totais: array, usinas: array} */
    public function listaUsinas(): array
    {
        $linhas = $this->linhas();

        $porUsina = [];
        foreach ($linhas as $l) {
            $u = &$porUsina[$l->usi_id];
            if (! isset($u)) {
                $u = ['usi_id' => (int) $l->usi_id, 'uc' => $l->uc, 'cliente' => $l->cliente ?? '-',
                      'saldo' => 0.0, 'meses_divergentes' => 0, 'inconclusivos' => 0];
            }
            if ($this->inconclusivo($l)) {
                $u['inconclusivos']++;
                continue;
            }
            if ($l->pago === null) {
                continue;
            }
            $dif = round((float) $l->pago - (float) $l->atual, 2);
            $u['saldo'] = round($u['saldo'] + $dif, 2);
            if (abs($dif) >= self::TOL) {
                $u['meses_divergentes']++;
            }
        }
        unset($u);
        $usinas = array_values($porUsina);

        $pagoAMais = 0.0; $pagoAMenos = 0.0; $inconc = 0;
        foreach ($usinas as $u) {
            if ($u['saldo'] < 0) { $pagoAMais += -$u['saldo']; }
            elseif ($u['saldo'] > 0) { $pagoAMenos += $u['saldo']; }
            $inconc += $u['inconclusivos'];
        }

        return [
            'totais' => [
                'pago_a_mais' => round($pagoAMais, 2),
                'pago_a_menos' => round($pagoAMenos, 2),
                'saldo' => round($pagoAMenos - $pagoAMais, 2),
                'total_inconclusivos' => $inconc,
            ],
            'usinas' => $usinas,
        ];
    }

    /** @return array{usina: array, resumo: array, meses: array} */
    public function detalheUsina(int $usiId): array
    {
        $linhas = array_values(array_filter($this->linhas(), fn ($l) => (int) $l->usi_id === $usiId));
        usort($linhas, fn ($a, $b) => strcmp((string) $a->competencia, (string) $b->competencia));

        $meses = [];
        $pagoTotal = 0.0; $atualTotal = 0.0;
        $uc = null; $cliente = null;
        foreach ($linhas as $l) {
            $uc ??= $l->uc; $cliente ??= $l->cliente;
            $inconc = $this->inconclusivo($l);
            $dif = (! $inconc && $l->pago !== null) ? round((float) $l->pago - (float) $l->atual, 2) : null;
            if (! $inconc && $l->pago !== null) {
                $pagoTotal += (float) $l->pago; $atualTotal += (float) $l->atual;
            }
            $meses[] = [
                'competencia' => Carbon::parse($l->competencia)->format('Y-m'),
                'antes' => $l->antes !== null ? (float) $l->antes : null,
                'pago' => $l->pago !== null ? (float) $l->pago : null,
                'atual' => $l->atual !== null ? (float) $l->atual : null,
                'fatura_atual' => $l->fatura !== null ? (float) $l->fatura : null,
                'status' => $inconc ? 'inconclusivo' : 'conclusivo',
                'diferenca' => $dif,
                'termos' => $l->atual !== null ? $this->termos($l) : null,
            ];
        }

        return [
            'usina' => ['usi_id' => $usiId, 'uc' => $uc, 'cliente' => $cliente ?? '-'],
            'resumo' => [
                'pago_total' => round($pagoTotal, 2),
                'atual_total' => round($atualTotal, 2),
                'saldo' => round($pagoTotal - $atualTotal, 2),
            ],
            'meses' => $meses,
        ];
    }

    /**
     * União baseline + pdf por (usina, competência). Uma linha por chave.
     *
     * Usa UNION dos dois lados (LEFT JOIN + RIGHT side via LEFT JOIN invertido)
     * em vez de FULL OUTER JOIN para garantir compatibilidade com SQLite (testes)
     * e PostgreSQL (produção). Produz as mesmas linhas: meses presentes em
     * baseline e/ou em geracao_faturamento_pdf.
     *
     * O ORDER BY fica na subquery externa para evitar conflito SQLite com UNION.
     */
    private function linhas(): array
    {
        $sql = "
            SELECT * FROM (
                -- Lado 1: meses presentes no baseline (com ou sem pdf correspondente)
                SELECT
                    u.usi_id,
                    u.uc,
                    cli.nome AS cliente,
                    ab.competencia AS competencia,
                    ab.valor_sistema_antes AS antes,
                    ab.valor_pago AS pago,
                    g.valor_final AS atual,
                    g.fatura_energia AS fatura,
                    g.valor_fixo,
                    g.injetado,
                    g.creditado,
                    g.cuo
                FROM usina u
                LEFT JOIN cliente cli ON cli.cli_id = u.cli_id
                INNER JOIN auditoria_baseline ab ON ab.usi_id = u.usi_id
                LEFT JOIN geracao_faturamento_pdf g
                    ON g.usi_id = ab.usi_id
                    AND substr(g.competencia, 1, 7) = substr(ab.competencia, 1, 7)

                UNION

                -- Lado 2: meses presentes apenas no pdf (sem baseline)
                SELECT
                    u.usi_id,
                    u.uc,
                    cli.nome AS cliente,
                    g.competencia AS competencia,
                    NULL AS antes,
                    NULL AS pago,
                    g.valor_final AS atual,
                    g.fatura_energia AS fatura,
                    g.valor_fixo,
                    g.injetado,
                    g.creditado,
                    g.cuo
                FROM geracao_faturamento_pdf g
                LEFT JOIN usina u ON u.usi_id = g.usi_id
                LEFT JOIN cliente cli ON cli.cli_id = u.cli_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM auditoria_baseline ab
                    WHERE ab.usi_id = g.usi_id
                    AND substr(ab.competencia, 1, 7) = substr(g.competencia, 1, 7)
                )
            ) AS combined
            ORDER BY usi_id, competencia
        ";

        return DB::select($sql);
    }

    private function inconclusivo(object $l): bool
    {
        return $l->atual === null || $l->fatura === null || (float) $l->fatura == 0.0;
    }

    /** @return array<string, float> */
    private function termos(object $l): array
    {
        $fixo = (float) $l->valor_fixo; $inj = (float) $l->injetado;
        $cred = (float) $l->creditado; $cuo = (float) $l->cuo; $vf = (float) $l->atual;
        return [
            'fixo' => round($fixo, 2), 'injetado' => round($inj, 2), 'credito' => round($cred, 2),
            'cuo' => round($cuo, 2),
            'credito_expirado' => round($vf - ($fixo + $inj + $cred - $cuo), 2),
            'valor_final' => round($vf, 2),
        ];
    }
}
