<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoEstorno extends Model
{
    protected $table = 'historico_estorno';

    protected $primaryKey = 'he_id';

    protected $fillable = [
        'usi_id',
        'ano',
        'mes',
        'mes_nome',
        'user_id',
        'user_id_estorno',
        'idempotency_key',
        'snapshot_reserva_atual',
        'snapshot_reserva_anterior',
        'snapshot_credito_mes',
        'snapshot_faturamento_mes',
        'snapshot_geracao_mes',
        'revertido_em',
    ];

    protected $casts = [
        'snapshot_reserva_atual'    => 'array',
        'snapshot_reserva_anterior' => 'array',
        'snapshot_credito_mes'      => 'float',
        'snapshot_faturamento_mes'  => 'float',
        'snapshot_geracao_mes'      => 'float',
        'revertido_em'              => 'datetime',
    ];

    public function usina()
    {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioEstorno()
    {
        return $this->belongsTo(User::class, 'user_id_estorno');
    }
}
