<?php

namespace App\Services;

use App\Models\Vendedor;

class VendedorService {

  private Vendedor $vendedor;

  public function __construct(Vendedor $vendedor) {
    $this->vendedor = $vendedor;
  }

  public function create(array $data): int {
    return $this->vendedor->create($data)->ven_id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->vendedor->find($id);

    return $registro ? $registro->update($data) : 0;
  }

  public function delete(int $id): int {
    $registro = $this->vendedor->find($id);

    return $registro ? $registro->delete() : 0;
  }

  public function findById(int $id): array|null {
    $dados = $this->vendedor->find($id);

    return $dados ? $dados->toArray() : null;
  }

  public function findAll(): array {
    $dados = $this->vendedor->all();

    return $dados->isNotEmpty() ? $dados->toArray() : [];
  }
}
