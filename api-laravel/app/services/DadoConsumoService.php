<?php

namespace App\Services;

use App\Models\DadoConsumo;

class DadoConsumoService {
    
    private DadoConsumo $dadoConsumo;

    public function __construct(DadoConsumo $dadoConsumo) {
        $this->dadoConsumo = $dadoConsumo;
    }

    public function create(array $data): int {
        return $this->dadoConsumo->create($data)->dcon_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->dadoConsumo->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->dadoConsumo->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $dados = $this->dadoConsumo->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->dadoConsumo->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
