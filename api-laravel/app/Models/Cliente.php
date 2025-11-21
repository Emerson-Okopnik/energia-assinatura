<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model {
    
    protected $table = 'cliente';
    
    protected $primaryKey = 'cli_id';

    protected $fillable = [
        'nome',
        'cpf_cnpj',
        'telefone',
        'email',
        'end_id',
    ];

    protected $casts = [
        'nome' => 'string',
        'cpf_cnpj' => 'string',
        'telefone' => 'string',
        'email' => 'string',
        'end_id' => 'integer',
    ];

    public function endereco() {
        return $this->belongsTo(Endereco::class, 'end_id', 'end_id');
    }

    public function consumidores() {
        return $this->hasOne(Consumidor::class, 'cli_id', 'cli_id');
    }

    public function usinas() {
        return $this->hasOne(Usina::class, 'cli_id', 'cli_id');
    }

    public function dadosGeracaoRealUsina() {
      return $this->hasMany(DadosGeracaoRealUsina::class, 'cli_id', 'cli_id');
    }

    public function dadosConsumoUsina() {
      return $this->hasMany(DadoConsumoUsina::class, 'cli_id', 'cli_id');
    }
}
