<?php

namespace App\Services;

use App\Models\Endereco;
use App\Services\Concerns\CachesFindAll;

class EnderecoService {

    use CachesFindAll;

    private Endereco $endereco;
    private string $cacheKey = 'endereco.find_all';

    public function __construct(Endereco $endereco) {
        $this->endereco = $endereco;
    }
    
    public function create(array $data): int {
        $id = $this->endereco->create($data)->end_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->endereco->find($id);
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
        $registro = $this->endereco->find($id);
    
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
        $dados = $this->endereco->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->endereco->all();
        });
    }
}
