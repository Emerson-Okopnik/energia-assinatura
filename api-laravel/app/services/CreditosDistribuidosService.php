<?php

namespace App\Services;

use App\Models\CreditosDistribuidos;
use App\Services\Concerns\CachesFindAll;

class CreditosDistribuidosService {

    use CachesFindAll;

    private CreditosDistribuidos $creditosDistribuidos;
    private string $cacheKey = 'creditos_distribuidos.find_all';

    public function __construct(CreditosDistribuidos $creditosDistribuidos) {
        $this->creditosDistribuidos = $creditosDistribuidos;
    }
    
    public function create(): int {
        $registro =$this->creditosDistribuidos->create(); // cria com defaults
        $this->forgetFindAllCache($this->cacheKey);
        return $registro->cd_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->creditosDistribuidos->find($id);
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
        $registro = $this->creditosDistribuidos->find($id);
    
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
        $dados = $this->creditosDistribuidos->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->creditosDistribuidos->all();
        });
    }
}