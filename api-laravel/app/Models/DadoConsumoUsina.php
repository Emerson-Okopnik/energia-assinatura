<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadoConsumoUsina extends Model {
    protected $table = 'dados_consumo_usina';

    protected $primaryKey = 'dcu_id';

    protected $fillable = [
        'usi_id',
        'cli_id',
        'dcon_id',
        'ano',
    ];

    protected $casts = [
        'usi_id' => 'integer',
        'cli_id' => 'integer',
        'dcon_id' => 'integer',
        'ano' => 'integer',
    ];

    public function usina() {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }

    public function cliente() {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    public function dadoConsumo() {
        return $this->belongsTo(DadoConsumo::class, 'dcon_id', 'dcon_id');
    }
}