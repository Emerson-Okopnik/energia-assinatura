<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\UsinaConsumidor;

class UsinaConsumidorService {
    
    private UsinaConsumidor $usinaConsumidor;

    public function __construct(UsinaConsumidor $usinaConsumidor) {
        $this->usinaConsumidor = $usinaConsumidor;
    }

    public function create(array $data): int {
        return $this->usinaConsumidor->create($data)->usic_id;
    }

    public function update(int $id, array $data): string {
        $registro = $this->usinaConsumidor->find($id);
    
        if (!$registro) {
            return 0;
        }
    
        UsinaConsumidor::where('usi_id', $registro->usi_id)->delete();
    
        $novos = [];
        foreach ($data['con_ids'] as $conId) {
            $novos[] = [
                'usi_id' => $data['usi_id'],
                'cli_id' => $data['cli_id'],
                'con_id' => $conId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    
        DB::table('usina_consumidor')->insert($novos);
    
        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->usinaConsumidor->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $usinaConsumidor = $this->usinaConsumidor->with([
            'usina.dadoGeracao',
            'usina.comercializacao',
            'usina.cliente.endereco',
            'consumidor.cliente.endereco',
            'consumidor.dado_consumo'
        ])->find($id);
        return $usinaConsumidor ? $usinaConsumidor->toArray() : null;
    }

    public function findAll(): array {
        return $this->usinaConsumidor->with([
            'usina.dadoGeracao',
            'usina.comercializacao',
            'usina.cliente.endereco',
            'usina.vendedor',
            'consumidor.cliente.endereco',
            'consumidor.dado_consumo',
            'consumidor.vendedor'
        ])->get()->toArray();
    }

    public function createMany(array $data): int {
        $inserts = [];
    
        foreach ($data['con_ids'] as $conId) {
            $inserts[] = [
                'usi_id' => $data['usi_id'],
                'cli_id' => $data['cli_id'],
                'con_id' => $conId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    
        return DB::table('usina_consumidor')->insert($inserts) ? count($inserts) : 0;
    }

    public function deleteVinculo(int $usi_id, int $con_id): bool {
        return UsinaConsumidor::where('usi_id', $usi_id)
            ->where('con_id', $con_id)
            ->delete() > 0;
    }

    public function findByUsinaId(int $usi_id) {
        return $this->usinaConsumidor->where('usi_id', $usi_id)->with([
                'usina.cliente.endereco',
                'usina.comercializacao',
                'usina.dadoGeracao',
                'usina.vendedor',
                'consumidor.cliente.endereco',
                'consumidor.dado_consumo',
                'consumidor.vendedor'
            ])->get();
    }
}
