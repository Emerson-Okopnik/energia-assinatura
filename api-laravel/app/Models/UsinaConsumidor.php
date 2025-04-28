<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsinaConsumidor extends Model
{
    protected $table = 'usina_consumidor';

    protected $primaryKey = 'usic_id';

    protected $fillable = [
        'usi_id',
        'cli_id',
        'con_id',
    ];

    protected $casts = [
        'usi_id' => 'integer',
        'cli_id' => 'integer',
        'con_id' => 'integer',
    ];

    public function usina()
    {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    public function consumidor()
    {
        return $this->belongsTo(Consumidor::class, 'con_id', 'con_id');
    }
}
