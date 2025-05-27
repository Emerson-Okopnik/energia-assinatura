<?php

namespace App\Services;

use App\Models\DadosGeracaoReal;

class DadosGeracaoRealService {

  private DadosGeracaoReal $dadosGeracaoReal;

  public function __construct(DadosGeracaoReal $dadosGeracaoReal) {
    $this->dadosGeracaoReal = $dadosGeracaoReal;
  }

  public function create(array $data): int {
    return $this->dadosGeracaoReal->create($data)->dgr_id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->dadosGeracaoReal->find($id);

    return $registro ? $registro->update($data) : 0;
  }

  public function delete(int $id): int {
    $registro = $this->dadosGeracaoReal->find($id);

    return $registro ? $registro->delete() : 0;
  }

  public function findById(int $id): array|null {
    $dados = $this->dadosGeracaoReal->find($id);

    return $dados ? $dados->toArray() : null;
  }

  public function findAll(): array {
    $dados = $this->dadosGeracaoReal->all();

    return $dados->isNotEmpty() ? $dados->toArray() : [];
  }
}
