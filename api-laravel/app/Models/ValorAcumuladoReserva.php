<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValorAcumuladoReserva extends Model {
   
    protected $table = 'valor_acumulado_reserva';

    protected $primaryKey = 'var_id';

    protected $fillable = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro',
        'total'
    ];

    protected $casts = [
        'janeiro' => 'float',
        'fevereiro' => 'float',
        'marco' => 'float',
        'abril' => 'float',
        'maio' => 'float',
        'junho' => 'float',
        'julho' => 'float',
        'agosto' => 'float',
        'setembro' => 'float',
        'outubro' => 'float',
        'novembro' => 'float',
        'dezembro' => 'float',
        'total' => 'float',
    ];

    public function creditosDistribuidosUsina() {
        return $this->belongsTo(CreditosDistribuidosUsina::class, 'var_id', 'var_id');
    }
}
