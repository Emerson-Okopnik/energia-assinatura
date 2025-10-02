<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\UsinaConsumidor;
use App\Services\Concerns\CachesFindAll;

class UsinaConsumidorService {
    
    use CachesFindAll;

    private UsinaConsumidor $usinaConsumidor;
    private string $cacheKey = 'usina_consumidor.find_all';

    public function __construct(UsinaConsumidor $usinaConsumidor) {
        $this->usinaConsumidor = $usinaConsumidor;
    }

    public function create(array $data): int {
        $id = $this->usinaConsumidor->create($data)->usic_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
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
    
        $updated = (int) $registro->update($data);

        if ($updated) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return (string) $updated;
    }

    public function delete(int $id): int {
        $registro = $this->usinaConsumidor->find($id);
    
        if (!$registro) {
            return 0;
        }

        $deleted = (int) $registro->delete();

        if ($deleted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $deleted;
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
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->usinaConsumidor->with([
                'usina.dadoGeracao',
                'usina.comercializacao',
                'usina.cliente.endereco',
                'usina.vendedor',
                'consumidor.cliente.endereco',
                'consumidor.dado_consumo',
                'consumidor.vendedor'
            ])->get();
        });
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
    
        $inserted = DB::table('usina_consumidor')->insert($inserts) ? count($inserts) : 0;

        if ($inserted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $inserted;
    }

    public function deleteVinculo(int $usi_id, int $con_id): bool {
        $deleted = UsinaConsumidor::where('usi_id', $usi_id)
            ->where('con_id', $con_id)
            ->delete() > 0;

        if ($deleted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $deleted;
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
