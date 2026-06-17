<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaBaseline extends Model
{
    protected $table = 'auditoria_baseline';

    protected $primaryKey = 'ab_id';

    protected $fillable = [
        'usi_id', 'competencia', 'valor_sistema_antes', 'valor_pago',
        'fatura_informada', 'consumo_informado',
    ];

    protected $casts = [
        'competencia' => 'date:Y-m-d',
        'valor_sistema_antes' => 'float',
        'valor_pago' => 'float',
        'fatura_informada' => 'float',
        'consumo_informado' => 'float',
    ];

    protected function setCompetenciaAttribute($value): void
    {
        $this->attributes['competencia'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
    }
}
