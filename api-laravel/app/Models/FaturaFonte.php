<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaturaFonte extends Model
{
    protected $table = 'fatura_fonte';

    public $timestamps = false;

    protected $fillable = ['uc', 'competencia', 'fatura_energia'];

    protected $casts = [
        'competencia' => 'date',
        'fatura_energia' => 'float',
    ];
}
