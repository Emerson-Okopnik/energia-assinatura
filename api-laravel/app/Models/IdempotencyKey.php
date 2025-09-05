<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'hash_payload',
        'user_id',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];
}
