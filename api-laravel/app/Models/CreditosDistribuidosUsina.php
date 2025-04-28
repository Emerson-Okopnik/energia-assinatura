<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditosDistribuidosUsina extends Model
{
    protected $table = 'creditos_distribuidos_usina';

    protected $primaryKey = 'cdu_id';

    protected $fillable = [
        'usi_id',
        'cli_id',
        'cd_id',
        'ano',
    ];

    protected $casts = [
        'usi_id' => 'integer',
        'cli_id' => 'integer',
        'cd_id' => 'integer',
        'ano' => 'integer',
    ];

    public function usina()
    {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    public function creditosDistribuidos()
    {
        return $this->belongsTo(CreditosDistribuidos::class, 'cd_id', 'cd_id');
    }
}
