<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usina extends Model {

  protected $table = 'usina';

  protected $primaryKey = 'usi_id';

  protected $fillable = [
    'cli_id',
    'dger_id',
    'com_id',
    'ven_id',
    'uc',
    'rede',
    'data_limite_troca_titularidade',
    'data_ass_contrato',
    'status',
    'andamento_processo',
  ];

  protected $casts = [
    'cli_id' => 'integer',
    'dger_id' => 'integer',
    'com_id' => 'integer',
    'ven_id' => 'integer',
    'uc' => 'string',
    'rede' => 'string',
    'data_limite_troca_titularidade' => 'date',
    'data_ass_contrato' => 'date',
    'status' => 'string',
    'andamento_processo' => 'string',
  ];

  public function cliente() {
    return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
  }

  public function dadoGeracao() {
    return $this->belongsTo(DadoGeracao::class, 'dger_id', 'dger_id');
  }

  public function comercializacao() {
    return $this->belongsTo(Comercializacao::class, 'com_id', 'com_id');
  }

  public function creditosDistribuidosUsina() {
    return $this->hasMany(CreditosDistribuidosUsina::class, 'usi_id', 'usi_id');
  }
    
  public function vendedor() {
    return $this->belongsTo(Vendedor::class, 'ven_id', 'ven_id');
  }

  public function dadosGeracaoRealUsina() {
    return $this->hasMany(DadosGeracaoRealUsina::class, 'usi_id', 'usi_id');
  }

  public function dadosConsumoUsina() {
    return $this->hasMany(DadoConsumoUsina::class, 'usi_id', 'usi_id');
  }
}
