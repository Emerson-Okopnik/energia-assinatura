<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Calculo\ValidadorEntrada;
use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;
use App\Domain\Faturamento\ValueObject\Tarifa;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Testes dos Value Objects e do ValidadorEntrada.
 */
class ValueObjectTest extends TestCase
{
    #[Test]
    public function reais_em_centavos_evita_erro_de_float(): void
    {
        $this->assertSame(30, Reais::deReais(0.1)->mais(Reais::deReais(0.2))->emCentavos());
        $this->assertSame(0.30, Reais::deReais(0.1)->mais(Reais::deReais(0.2))->emReais());
    }

    #[Test]
    public function reais_formata_em_pt_br(): void
    {
        $this->assertSame('R$ 1.561,11', Reais::deCentavos(156111)->formatar());
        $this->assertSame('R$ 0,00', Reais::zero()->formatar());
        $this->assertSame('R$ 3.894,36', Reais::deReais(3894.36)->formatar());
    }

    #[Test]
    public function reais_soma_e_subtrai(): void
    {
        $a = Reais::deReais(3894.36);
        $b = Reais::deReais(883.96);

        $this->assertSame(3010.40, $a->menos($b)->emReais());
        $this->assertSame(4778.32, $a->mais($b)->emReais());
        $this->assertTrue($a->menos($b)->menos(Reais::deReais(4000))->ehNegativo());
    }

    #[Test]
    public function kwh_vezes_tarifa_gera_reais(): void
    {
        $resultado = Kwh::de(7636)->vezesTarifa(Tarifa::de(0.51));

        $this->assertSame(3894.36, $resultado->emReais());
    }

    #[Test]
    public function kwh_operacoes_e_comparacoes(): void
    {
        $this->assertSame(2214.0, Kwh::de(9850)->menos(Kwh::de(7636))->valor());
        $this->assertTrue(Kwh::de(100)->ehPositivo());
        $this->assertTrue(Kwh::de(12911)->ehMaiorOuIgualA(Kwh::de(12911)));
        $this->assertFalse(Kwh::de(9850)->ehMaiorOuIgualA(Kwh::de(12911)));
        $this->assertSame(0.0, Kwh::de(-5)->max(Kwh::zero())->valor());
        $this->assertSame(-5.0, Kwh::de(-5)->min(Kwh::zero())->valor());
    }

    #[Test]
    public function competencia_ordena_cross_ano(): void
    {
        $dez25 = Competencia::de(2025, 12);
        $jan26 = Competencia::de(2026, 1);

        $this->assertTrue($dez25->ehAnteriorA($jan26));
        $this->assertFalse($jan26->ehAnteriorA($dez25));
        $this->assertSame(-1, $dez25->comparar($jan26) <=> 0);
        $this->assertSame('2025-12', (string) $dez25);
    }

    #[Test]
    public function competencia_vencimento_em_dias_a_partir_do_dia_1(): void
    {
        $venc = Competencia::de(2025, 12)->vencimentoEmDias(180);

        // 2025-12-01 + 180 dias = 2026-05-30.
        $this->assertSame('2026-05-30', $venc->format('Y-m-d'));
    }

    #[Test]
    public function competencia_mes_invalido_lanca_excecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Competencia::de(2026, 13);
    }

    #[Test]
    public function entrada_de_array_aceita_competencia_por_ano_mes(): void
    {
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 9850,
            'media_kwh' => 12911,
            'menor_geracao_kwh' => 7636,
            'geracao_bruta_kwh' => 9858,
            'tarifa' => 0.51,
            'fio_b' => 0.13275,
            'percentual_lei' => 60.0,
            'fatura_energia' => 98.77,
            'adicional_cuo' => 0,
            'ano' => 2026,
            'mes' => 5,
        ]);

        $this->assertTrue($entrada->competencia->ehIgualA(Competencia::de(2026, 5)));
        $this->assertSame(98.77, $entrada->faturaEnergia->emReais());
    }

    #[Test]
    public function validador_rejeita_tarifa_nao_positiva(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ValidadorEntrada())->validar($this->entradaComTarifa(0.0));
    }

    #[Test]
    public function validador_rejeita_percentual_lei_fora_da_faixa(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ValidadorEntrada())->validar($this->entradaComPercentual(120.0));
    }

    #[Test]
    public function validador_aceita_entrada_coerente(): void
    {
        (new ValidadorEntrada())->validar($this->entradaComTarifa(0.51));
        $this->expectNotToPerformAssertions();
    }

    private function entradaComTarifa(float $tarifa): EntradaCalculoMes
    {
        return new EntradaCalculoMes(
            geracaoLiquidaKwh: Kwh::de(9850),
            mediaKwh: Kwh::de(12911),
            menorGeracaoKwh: Kwh::de(7636),
            geracaoBrutaKwh: Kwh::de(9858),
            tarifa: Tarifa::de($tarifa),
            fioB: 0.13275,
            percentualLei: 60.0,
            faturaEnergia: Reais::deReais(98.77),
            adicionalCuo: Reais::zero(),
            competencia: Competencia::de(2026, 5),
        );
    }

    private function entradaComPercentual(float $percentual): EntradaCalculoMes
    {
        return new EntradaCalculoMes(
            geracaoLiquidaKwh: Kwh::de(9850),
            mediaKwh: Kwh::de(12911),
            menorGeracaoKwh: Kwh::de(7636),
            geracaoBrutaKwh: Kwh::de(9858),
            tarifa: Tarifa::de(0.51),
            fioB: 0.13275,
            percentualLei: $percentual,
            faturaEnergia: Reais::deReais(98.77),
            adicionalCuo: Reais::zero(),
            competencia: Competencia::de(2026, 5),
        );
    }
}
