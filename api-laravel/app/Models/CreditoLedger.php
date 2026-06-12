<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lançamento imutável do ledger de reserva (REGRAS_DE_CALCULO.md §8).
 *
 * Cada linha é um movimento de crédito. O saldo de uma origem é a soma dos
 * `kwh` não-estornados daquela origem. Saídas (CONSUMO/EXPIRACAO) têm `kwh`
 * negativo e apontam para o CREDITO de origem via `ref_lancamento_id`.
 */
class CreditoLedger extends Model
{
    public const TIPO_SALDO_INICIAL = 'SALDO_INICIAL';
    public const TIPO_CREDITO = 'CREDITO';
    public const TIPO_CONSUMO = 'CONSUMO';
    public const TIPO_EXPIRACAO = 'EXPIRACAO';

    protected $table = 'credito_ledger';

    protected $primaryKey = 'cl_id';

    protected $fillable = [
        'usi_id',
        'competencia_origem',
        'competencia_evento',
        'tipo',
        'kwh',
        'tarifa_kwh',
        'valor_reais',
        'vencimento',
        'ref_lancamento_id',
        'idempotency_key',
        'estornado_em',
        'user_id',
    ];

    protected $casts = [
        'usi_id' => 'integer',
        'competencia_origem' => 'date',
        'competencia_evento' => 'date',
        'tipo' => 'string',
        'kwh' => 'decimal:4',
        'tarifa_kwh' => 'decimal:6',
        'valor_reais' => 'decimal:2',
        'vencimento' => 'date',
        'ref_lancamento_id' => 'integer',
        'estornado_em' => 'datetime',
        'user_id' => 'integer',
    ];

    public function usina(): BelongsTo
    {
        return $this->belongsTo(Usina::class, 'usi_id', 'usi_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lancamentoOrigem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ref_lancamento_id', 'cl_id');
    }

    /** Apenas lançamentos válidos (não estornados). */
    public function scopeNaoEstornado(Builder $query): Builder
    {
        return $query->whereNull('estornado_em');
    }

    public function scopeDoUsina(Builder $query, int $usiId): Builder
    {
        return $query->where('usi_id', $usiId);
    }

    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }
}
