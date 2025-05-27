<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Endereco extends Model
{
    protected $table = 'endereco';
    
    protected $primaryKey = 'end_id';

    protected $fillable = [
        'rua',
        'cidade',
        'estado',
        'complemento',
        'cep',
        'numero',
    ];

    protected $casts = [
        'rua' => 'string',
        'cidade' => 'string',
        'estado' => 'string',
        'complemento' => 'string',
        'cep' => 'string',
        'numero' => 'integer',
    ];

    public function clientes() {
        return $this->hasMany(Cliente::class, 'end_id', 'end_id');
    }
}
