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
        'data_limite_troca_titularidade',
        'data_ass_contrato',
        'status',
        'andamento_processo',
    ];

    protected $casts = [
        'cli_id' => 'integer',
        'dger_id' => 'integer',
        'com_id' => 'integer',
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

    // NOVO: Muitos consumidores para uma usina
    /*public function consumidores() {
        return $this->belongsToMany(Consumidor::class, 'usina_consumidor', 'usi_id', 'con_id')
                    ->withTimestamps();
    }*/
}
