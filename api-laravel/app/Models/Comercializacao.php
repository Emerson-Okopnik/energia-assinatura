<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comercializacao extends Model {

    protected $table = 'comercializacao';

    protected $primaryKey = 'com_id';

    protected $fillable = [
        'valor_kwh',
        'valor_fixo',
        'cia_energia',
        'valor_final_media',
        'previsao_conexao',
        'data_conexao',
    ];

    protected $casts = [
        'valor_kwh' => 'float',
        'valor_fixo' => 'float',
        'cia_energia' => 'string',
        'valor_final_media' => 'float',
        'previsao_conexao' => 'date',
        'data_conexao' => 'date',
    ];

    public function usinas()
    {
        return $this->hasMany(Usina::class, 'com_id', 'com_id');
    }
}
