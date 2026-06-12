<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatNumeroTest extends TestCase
{
    public function test_numero_formata_pt_br_sem_unidade(): void
    {
        $this->assertSame('3.943', Format::numero(3943.2, 0));
        $this->assertSame('197,16', Format::numero(197.16));
        $this->assertSame('0', Format::numero(null, 0));
    }
}
