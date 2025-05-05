<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditosDistribuidosUsina extends Model
{
    protected $table = 'creditos_distribuidos_usina';

    protected $primaryKey = 'cdu_id';

    protected $fillable = [
        'cd_id',
        'fa_id',
        'var_id',
        'ano',
    ];

    protected $casts = [
        'cd_id' => 'integer',
        'fa_id' => 'integer',
        'var_id' => 'integer',
        'ano' => 'integer',
    ];

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
