<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Calculo\DescontoRede;
use App\Domain\Faturamento\ValueObject\Kwh;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DescontoRedeTest extends TestCase
{
    #[Test]
    public function desconto_por_tipo_de_conexao(): void
    {
        $this->assertSame(100.0, DescontoRede::kwhPorTipo('Trifásico'));
        $this->assertSame(50.0, DescontoRede::kwhPorTipo('Bifásico'));
        $this->assertSame(30.0, DescontoRede::kwhPorTipo('Monofásico'));
        $this->assertSame(0.0, DescontoRede::kwhPorTipo(null));
        $this->assertSame(0.0, DescontoRede::kwhPorTipo('desconhecido'));
    }

    #[Test]
    public function liquida_subtrai_consumo_descontavel(): void
    {
        // bruta 9858, consumo 134, trifásico 100 -> descontável 34 -> líquida 9824
        $liquida = DescontoRede::liquida(Kwh::de(9858), Kwh::de(134), 'Trifásico');
        $this->assertSame(9824.0, $liquida->valor());
    }

    #[Test]
    public function consumo_abaixo_do_desconto_nao_reduz(): void
    {
        // consumo 80 <= desconto 100 -> descontável 0 -> líquida = bruta
        $liquida = DescontoRede::liquida(Kwh::de(9858), Kwh::de(80), 'Trifásico');
        $this->assertSame(9858.0, $liquida->valor());
    }

    #[Test]
    public function liquida_nunca_negativa(): void
    {
        $liquida = DescontoRede::liquida(Kwh::de(50), Kwh::de(500), 'Monofásico');
        $this->assertSame(0.0, $liquida->valor());
    }
}
