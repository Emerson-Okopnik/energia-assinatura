<?php

namespace App\Services;

use App\Models\FaturamentoUsina;
use App\Services\Concerns\CachesFindAll;

class FaturamentoUsinaService {


    private FaturamentoUsina $faturamentoUsina;
    private string $cacheKey = 'faturamento_usina.find_all';

    public function __construct(FaturamentoUsina $faturamentoUsina) {
        $this->faturamentoUsina = $faturamentoUsina;
    }

    public function create(): int {
        $registro = $this->faturamentoUsina->create(); // cria com defaults
        $this->forgetFindAllCache($this->cacheKey);

        return $registro->fa_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->faturamentoUsina->find($id);
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
        $registro = $this->faturamentoUsina->find($id);
    
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
        $dados = $this->faturamentoUsina->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->faturamentoUsina->all();
        });
    }
}
