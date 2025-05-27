<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadosGeracaoRealUsina extends Model {
  protected $table = 'dados_geracao_real_usina';

  protected $primaryKey = 'dgru_id';

  protected $fillable = [
    'usi_id',
    'cli_id',
    'dgr_id',
    'ano',
  ];

  protected $casts = [
    'usi_id' => 'integer',
    'cli_id' => 'integer',
    'dgr_id' => 'integer',
    'ano' => 'integer',
  ];

  public function usina() {
    return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
  }

  public function cliente() {
    return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
  }

  public function DadosGeracaoReal() {
    return $this->belongsTo(DadosGeracaoReal::class, 'dgr_id', 'dgr_id');
  }

}
