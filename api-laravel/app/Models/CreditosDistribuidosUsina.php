<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditosDistribuidosUsina extends Model {
  protected $table = 'creditos_distribuidos_usina';

  protected $primaryKey = 'cdu_id';

  protected $fillable = [
    'cd_id',
    'usi_id',
    'cli_id',
    'fa_id',
    'var_id',
    'ano',
  ];

  protected $casts = [
    'cd_id' => 'integer',
    'cli_id' => 'integer',
    'usi_id' => 'integer',
    'fa_id' => 'integer',
    'var_id' => 'integer',
    'ano' => 'integer',
  ];

  public function usina() {
    return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
  }

  public function cliente() {
    return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
  }

  public function creditosDistribuidos() {
    return $this->belongsTo(CreditosDistribuidos::class, 'cd_id', 'cd_id');
  }

  public function valorAcumuladoReserva() {
    return $this->belongsTo(ValorAcumuladoReserva::class, 'var_id', 'var_id');
  }

  public function faturamentoUsina() {
    return $this->belongsTo(FaturamentoUsina::class, 'fa_id', 'fa_id');
  }
}
