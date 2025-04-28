<?php

namespace App\Services;

use App\Models\Endereco;

class EnderecoService {

    private Endereco $endereco;

    public function __construct(Endereco $endereco) {
        $this->endereco = $endereco;
    }
    
    public function create(array $data): int {
        return $this->endereco->create($data)->end_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->endereco->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->endereco->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $dados = $this->endereco->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->endereco->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
