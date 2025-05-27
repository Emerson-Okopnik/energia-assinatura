<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaturamentoUsina extends Model
{
   
    protected $table = 'faturamento_usina';

    protected $primaryKey = 'fa_id';

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
        'dezembro'
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
    ];

    public function creditosDistribuidosUsina() {
        return $this->hasOne(CreditosDistribuidosUsina::class, 'fa_id', 'fa_id');
    }
}
