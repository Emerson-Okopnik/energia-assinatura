<?php

namespace App\Services;

use App\Models\DadoConsumo;
use App\Services\Concerns\CachesFindAll;

class DadoConsumoService {
    
    use CachesFindAll;

    private DadoConsumo $dadoConsumo;
    private string $cacheKey = 'dado_consumo.find_all';

    public function __construct(DadoConsumo $dadoConsumo) {
        $this->dadoConsumo = $dadoConsumo;
    }

    public function create(array $data): int {
        $id = $this->dadoConsumo->create($data)->dcon_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->dadoConsumo->find($id);
       
        if (!$registro) {
            return 0;
        }

        $updated = (int) $registro->update($data);
       
        if ($updated) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $updated;
    }

    public function delete(int $id): int {
        $registro = $this->dadoConsumo->find($id);
    
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
        $dados = $this->dadoConsumo->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->dadoConsumo->all();
        });
    }
}
