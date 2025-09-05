<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consumidor extends Model {
    protected $table = 'consumidor';

    protected $primaryKey = 'con_id';

    protected $fillable = [
        'cli_id',
        'dcon_id',
        'ven_id',
        'cia_energia',
        'uc',
        'rede',
        'data_entrega',
        'status',
        'alocacao',
    ];

    protected $casts = [
        'cli_id' => 'integer',
        'dcon_id' => 'integer',
        'ven_id' => 'integer',
        'cia_energia' => 'string',
        'uc' => 'string',
        'rede' => 'string',
        'data_entrega' => 'date',
        'status' => 'string',
        'alocacao' => 'string',
    ];

    public function cliente() {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    public function dado_consumo() {
        return $this->belongsTo(DadoConsumo::class, 'dcon_id', 'dcon_id');
    }

    public function vendedor() {
        return $this->belongsTo(Vendedor::class, 'ven_id', 'ven_id');
    }

    // NOVO: Um consumidor pode estar vinculado a várias usinas
    /*public function usinas() {
        return $this->belongsToMany(Usina::class, 'usina_consumidor', 'con_id', 'usi_id')
                    ->withTimestamps();
    }*/
}
