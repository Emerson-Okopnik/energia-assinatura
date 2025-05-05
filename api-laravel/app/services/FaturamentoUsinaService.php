<?php

namespace App\Services;

use App\Models\FaturamentoUsina;

class FaturamentoUsinaService {

    private FaturamentoUsina $faturamentoUsina;

    public function __construct(FaturamentoUsina $faturamentoUsina) {
        $this->faturamentoUsina = $faturamentoUsina;
    }

    public function create(): int {
        $registro = $this->faturamentoUsina->create(); // cria com defaults
        return $registro->fa_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->faturamentoUsina->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->faturamentoUsina->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $dados = $this->faturamentoUsina->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->faturamentoUsina->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
