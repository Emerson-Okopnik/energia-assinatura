<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoCalculoGeracao extends Model
{
    protected $table = 'historico_calculo_geracao';

    protected $primaryKey = 'hcg_id';

    protected $fillable = [
        'usi_id',
        'ano',
        'mes',
        'snapshot',
        'dcon_id',
        'dcu_id',
        'reverted_at',
    ];

    protected $casts = [
        'usi_id' => 'integer',
        'ano' => 'integer',
        'mes' => 'integer',
        'snapshot' => 'array',
        'dcon_id' => 'integer',
        'dcu_id' => 'integer',
        'reverted_at' => 'datetime',
    ];

    public function usina()
    {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }
}
