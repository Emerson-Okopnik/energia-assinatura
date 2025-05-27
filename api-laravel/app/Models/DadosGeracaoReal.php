<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadosGeracaoReal extends Model {
    protected $table = 'dados_geracao_real';

    protected $primaryKey = 'dgr_id';

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
    ];

    public function DadosGeracaoRealUsina() {
      return $this->hasOne(DadosGeracaoRealUsina::class, 'dgr_id', 'dgr_id');
    }
}
