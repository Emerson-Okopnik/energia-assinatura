<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadoConsumo extends Model {
    protected $table = 'dados_consumo';

    protected $primaryKey = 'dcon_id';

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
    ];

    protected $casts = [
        'dcon_id' => 'integer',
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
    ];

    public function consumidor() {
        return $this->hasOne(Consumidor::class, 'dcon_id', 'dcon_id');
    }

    public function dadoConsumoUsina() {
        return $this->hasOne(DadoConsumoUsina::class, 'dcon_id', 'dcon_id');
    }
}
