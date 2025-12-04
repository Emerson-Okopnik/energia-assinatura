<?php

namespace App\Services;

use App\Models\Comercializacao;
use App\Services\Concerns\CachesFindAll;

class ComercializacaoService {

    use CachesFindAll;

    private Comercializacao $comercializacao;
    private string $cacheKey = 'comercializacao.find_all';

    public function __construct(Comercializacao $comercializacao) {
        $this->comercializacao = $comercializacao;
    }

    public function create(array $data): int {
        $id = $this->comercializacao->create($data)->com_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->comercializacao->find($id);

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
        $registro = $this->comercializacao->find($id);
    
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
        $dados = $this->comercializacao->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->comercializacao->all();

        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->comercializacao->all();
        });
    }
}
