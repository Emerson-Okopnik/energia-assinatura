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
        'competencia' => 'date:Y-m-d',
        'fatura_energia' => 'float',
    ];

    protected function setCompetenciaAttribute($value): void
    {
        $this->attributes['competencia'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
    }
}
