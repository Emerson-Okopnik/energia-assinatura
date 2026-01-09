<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeracaoFaturamentoPdf extends Model {
  protected $table = 'geracao_faturamento_pdf';

  protected $primaryKey = 'gfp_id';

  protected $fillable = [
    'usi_id',
    'competencia',
    'geracao_kwh',
    'valor_fixo',
    'injetado',
    'creditado',
    'cuo',
    'valor_final',
  ];

  protected $casts = [
    'usi_id' => 'integer',
    'competencia' => 'date',
    'geracao_kwh' => 'float',
    'valor_fixo' => 'float',
    'injetado' => 'float',
    'creditado' => 'float',
    'cuo' => 'float',
    'valor_final' => 'float',
  ];

  public function usina() {
    return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
  }
}
