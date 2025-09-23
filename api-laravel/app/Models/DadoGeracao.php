<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadoGeracao extends Model {

    protected $table = 'dados_geracao';

    protected $primaryKey = 'dger_id';

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
        'media',
        'menor_geracao',
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
        'media' => 'float',
        'menor_geracao' => 'float',
    ];

    public function usina() {
        return $this->belongsTo(Usina::class, 'dger_id', 'dger_id');
    }
}
