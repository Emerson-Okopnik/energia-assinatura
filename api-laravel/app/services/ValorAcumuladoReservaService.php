<?php

namespace App\Services;

use App\Models\ValorAcumuladoReserva;

class ValorAcumuladoReservaService {

    private ValorAcumuladoReserva $valorAcumuladoReserva;

    public function __construct(ValorAcumuladoReserva $valorAcumuladoReserva) {
        $this->valorAcumuladoReserva = $valorAcumuladoReserva;
    }

    public function create(): int {
        $registro = $this->valorAcumuladoReserva->create(); // cria com defaults
        return $registro->var_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->valorAcumuladoReserva->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->valorAcumuladoReserva->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): array|null {
        $dados = $this->valorAcumuladoReserva->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        $dados = $this->valorAcumuladoReserva->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
