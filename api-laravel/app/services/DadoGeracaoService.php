<?php

namespace App\Services;

use App\Models\DadoGeracao;
use App\Services\Concerns\CachesFindAll;

class DadoGeracaoService {

    use CachesFindAll;

    private DadoGeracao $dadoGeracao;
    private string $cacheKey = 'dado_geracao.find_all';

    public function __construct(DadoGeracao $dadoGeracao) {
        $this->dadoGeracao = $dadoGeracao;
    }

    public function create(array $data): int {
        $id = $this->dadoGeracao->create($data)->dger_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->dadoGeracao->find($id);
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
        $registro = $this->dadoGeracao->find($id);
    
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
        $dados = $this->dadoGeracao->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->dadoGeracao->all();
        });
    }
}
