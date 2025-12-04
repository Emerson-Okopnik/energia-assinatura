<?php

namespace App\Services;

use App\Models\Vendedor;
use App\Services\Concerns\CachesFindAll;

class VendedorService {

  use CachesFindAll;

  private Vendedor $vendedor;
  private string $cacheKey = 'vendedor.find_all';

  public function __construct(Vendedor $vendedor) {
    $this->vendedor = $vendedor;
  }

  public function create(array $data): int {
    $id = $this->vendedor->create($data)->ven_id;

    $this->forgetFindAllCache($this->cacheKey);

    return $id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->vendedor->find($id);
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
    $registro = $this->vendedor->find($id);
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
    $dados = $this->vendedor->find($id);

    return $dados ? $dados->toArray() : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->vendedor->all();
    });
  }
}
