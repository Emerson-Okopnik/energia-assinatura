<?php

namespace App\Services;

use App\Models\DadoGeracao;

class DadoGeracaoService {

    private DadoGeracao $dadoGeracao;

    public function __construct(DadoGeracao $dadoGeracao) {
        $this->dadoGeracao = $dadoGeracao;
    }

    public function create(array $data): int {
        return $this->dadoGeracao->create($data)->dger_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->dadoGeracao->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->dadoGeracao->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $dados = $this->dadoGeracao->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->dadoGeracao->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
