<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendedor extends Model {
     protected $table = 'vendedor';

    protected $primaryKey = 'ven_id';

    protected $fillable = [
        'nome',
        'patente',
    ];

    protected $casts = [
        'nome' => 'string',
        'patente' => 'string',
    ];


    public function consumidores() {
        return $this->hasOne(Consumidor::class, 'ven_id', 'ven_id');
    }


    public function usinas() {
        return $this->hasOne(Usina::class, 'ven_id', 'ven_id');
    }
}
