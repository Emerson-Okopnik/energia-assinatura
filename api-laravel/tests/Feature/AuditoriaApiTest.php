<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_detalhe_usina_status_diferenca_e_termos(): void
    {
        $usiId = $this->seedUsina('UC777', 'Cliente 777');
        // mês conclusivo: fatura>0, antes/pago/atual
        $this->seedBaseline($usiId, '2026-01-01', antes: 1059.21, pago: 1058.75);
        $this->seedPdf($usiId, '2026-01-01', fixo: 2129.54, injetado: 1575.16, creditado: 0, cuo: 2645.95, valorFinal: 2446.29, fatura: 1663.71);
        // mês inconclusivo: fatura 0
        $this->seedBaseline($usiId, '2025-06-01', antes: null, pago: 2705.80);
        $this->seedPdf($usiId, '2025-06-01', fixo: 1000, injetado: 500, creditado: 0, cuo: 200, valorFinal: 1300, fatura: 0);

        $resp = $this->withoutMiddleware()->getJson("/api/auditoria/usinas/{$usiId}");
        $resp->assertOk();
        $meses = collect($resp->json('meses'))->keyBy('competencia');

        $jan = $meses['2026-01'];
        $this->assertSame('conclusivo', $jan['status']);
        $this->assertEqualsWithDelta(2446.29, $jan['atual'], 0.01);
        $this->assertEqualsWithDelta(1058.75 - 2446.29, $jan['diferenca'], 0.01);
        $this->assertEqualsWithDelta(1387.54, $jan['termos']['credito_expirado'], 0.01); // 2446.29 - (2129.54+1575.16+0-2645.95)

        $jun = $meses['2025-06'];
        $this->assertSame('inconclusivo', $jun['status']); // fatura 0
    }

    public function test_lista_usinas_saldo_e_inconclusivos(): void
    {
        $usiId = $this->seedUsina('UC888', 'Cliente 888');
        $this->seedBaseline($usiId, '2026-01-01', antes: 1000, pago: 800);
        $this->seedPdf($usiId, '2026-01-01', fixo: 0, injetado: 0, creditado: 0, cuo: 0, valorFinal: 1000, fatura: 50); // conclusivo, dif = 800-1000 = -200
        $this->seedBaseline($usiId, '2025-06-01', antes: null, pago: 500);
        $this->seedPdf($usiId, '2025-06-01', fixo: 0, injetado: 0, creditado: 0, cuo: 0, valorFinal: 500, fatura: 0); // inconclusivo

        $resp = $this->withoutMiddleware()->getJson('/api/auditoria/usinas');
        $resp->assertOk();
        $u = collect($resp->json('usinas'))->firstWhere('usi_id', $usiId);
        $this->assertEqualsWithDelta(-200.0, $u['saldo'], 0.01);
        $this->assertSame(1, $u['inconclusivos']);
        $this->assertSame(1, $u['meses_divergentes']);

        // totais: saldo<0 ("a menos") deve cair em pago_a_menos, NÃO em pago_a_mais
        $totais = $resp->json('totais');
        $this->assertEqualsWithDelta(200.0, $totais['pago_a_menos'], 0.01);
        $this->assertEqualsWithDelta(0.0, $totais['pago_a_mais'], 0.01);
        $this->assertEqualsWithDelta(-200.0, $totais['saldo'], 0.01);
    }

    public function test_mes_so_baseline_sem_pdf_e_inconclusivo(): void
    {
        $usiId = $this->seedUsina('UC901', 'Cliente 901');
        // Apenas baseline — nenhum seedPdf: atual será null → inconclusivo
        $this->seedBaseline($usiId, '2026-03-01', antes: 800.0, pago: 750.0);

        // detalheUsina: mês aparece com status inconclusivo e atual null
        $resp = $this->withoutMiddleware()->getJson("/api/auditoria/usinas/{$usiId}");
        $resp->assertOk();
        $meses = collect($resp->json('meses'))->keyBy('competencia');
        $mar = $meses['2026-03'];
        $this->assertSame('inconclusivo', $mar['status']);
        $this->assertNull($mar['atual']);
        $this->assertNull($mar['diferenca']);

        // listaUsinas: contado em inconclusivos e NÃO contribui ao saldo
        $lista = $this->withoutMiddleware()->getJson('/api/auditoria/usinas');
        $lista->assertOk();
        $u = collect($lista->json('usinas'))->firstWhere('usi_id', $usiId);
        $this->assertSame(1, $u['inconclusivos']);
        $this->assertEqualsWithDelta(0.0, $u['saldo'], 0.01);
    }

    public function test_mes_so_pdf_sem_baseline_e_inconclusivo(): void
    {
        $usiId = $this->seedUsina('UC902', 'Cliente 902');
        // Apenas pdf — nenhum seedBaseline: pago será null → inconclusivo
        $this->seedPdf($usiId, '2026-04-01', fixo: 1000, injetado: 500, creditado: 0, cuo: 200, valorFinal: 1300, fatura: 650);

        // detalheUsina: mês aparece com status inconclusivo (pago null)
        $resp = $this->withoutMiddleware()->getJson("/api/auditoria/usinas/{$usiId}");
        $resp->assertOk();
        $meses = collect($resp->json('meses'))->keyBy('competencia');
        $abr = $meses['2026-04'];
        $this->assertSame('inconclusivo', $abr['status']);
        $this->assertNull($abr['diferenca']);

        // listaUsinas: contado em inconclusivos e NÃO contribui ao saldo
        $lista = $this->withoutMiddleware()->getJson('/api/auditoria/usinas');
        $lista->assertOk();
        $u = collect($lista->json('usinas'))->firstWhere('usi_id', $usiId);
        $this->assertSame(1, $u['inconclusivos']);
        $this->assertEqualsWithDelta(0.0, $u['saldo'], 0.01);
    }

    // ---- helpers ----
    private function seedUsina(string $uc, string $nome): int
    {
        $now = now();
        $end = DB::table('endereco')->insertGetId(['created_at'=>$now,'updated_at'=>$now], 'end_id');
        $cli = DB::table('cliente')->insertGetId(['nome'=>$nome,'cpf_cnpj'=>'0','end_id'=>$end,'created_at'=>$now,'updated_at'=>$now], 'cli_id');
        $dger = DB::table('dados_geracao')->insertGetId(array_merge(array_fill_keys(['janeiro','fevereiro','marco','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'],0), ['media'=>0,'menor_geracao'=>0,'created_at'=>$now,'updated_at'=>$now]), 'dger_id');
        $com = DB::table('comercializacao')->insertGetId(['valor_kwh'=>0.5,'valor_fixo'=>0,'cia_energia'=>'T','valor_final_media'=>0,'previsao_conexao'=>$now->toDateString(),'created_at'=>$now,'updated_at'=>$now], 'com_id');
        $ven = DB::table('vendedor')->insertGetId(['nome'=>'V','patente'=>'junior','created_at'=>$now,'updated_at'=>$now], 'ven_id');
        return (int) DB::table('usina')->insertGetId(['cli_id'=>$cli,'dger_id'=>$dger,'com_id'=>$com,'ven_id'=>$ven,'uc'=>$uc,'created_at'=>$now,'updated_at'=>$now], 'usi_id');
    }

    private function seedBaseline(int $usiId, string $comp, ?float $antes, ?float $pago): void
    {
        DB::table('auditoria_baseline')->insert([
            'usi_id'=>$usiId,'competencia'=>$comp,'valor_sistema_antes'=>$antes,'valor_pago'=>$pago,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function seedPdf(int $usiId, string $comp, float $fixo, float $injetado, float $creditado, float $cuo, float $valorFinal, float $fatura): void
    {
        DB::table('geracao_faturamento_pdf')->insert([
            'usi_id'=>$usiId,'competencia'=>$comp,'geracao_kwh'=>0,
            'valor_fixo'=>$fixo,'injetado'=>$injetado,'creditado'=>$creditado,'cuo'=>$cuo,
            'valor_final'=>$valorFinal,'fatura_energia'=>$fatura,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
