<?php

namespace App\Services;

use App\Models\CreditosDistribuidos;

class CreditosDistribuidosService {

    private CreditosDistribuidos $creditosDistribuidos;

    public function __construct(CreditosDistribuidos $creditosDistribuidos) {
        $this->creditosDistribuidos = $creditosDistribuidos;
    }
    
    public function create(): int {
        $registro =$this->creditosDistribuidos->create(); // cria com defaults
        return $registro->cd_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->creditosDistribuidos->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->creditosDistribuidos->find($id);
    
        return $registro ? $registro->delete() : 0;
    }

    public function findById(int $id): array|null {
        $dados = $this->creditosDistribuidos->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->creditosDistribuidos->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}