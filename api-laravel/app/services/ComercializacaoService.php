<?php

namespace App\Services;

use App\Models\Comercializacao;

class ComercializacaoService {

    private Comercializacao $comercializacao;

    public function __construct(Comercializacao $comercializacao) {
        $this->comercializacao = $comercializacao;
    }

    public function create(array $data): int {
        return $this->comercializacao->create($data)->com_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->comercializacao->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->comercializacao->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $dados = $this->comercializacao->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->comercializacao->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
