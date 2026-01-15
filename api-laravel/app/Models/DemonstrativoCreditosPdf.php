<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemonstrativoCreditosPdf extends Model
{
    protected $table = 'demonstrativo_creditos_pdf';

    protected $primaryKey = 'dcp_id';

    protected $fillable = [
        'usi_id',
        'competencia',
        'vencimento',
        'guardado_kwh',
        'creditado_kwh',
        'meses_utilizados',
    ];

    protected $casts = [
        'usi_id' => 'integer',
        'competencia' => 'date',
        'vencimento' => 'date',
        'guardado_kwh' => 'float',
        'creditado_kwh' => 'float',
    ];

    public function usina()
    {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }
}
