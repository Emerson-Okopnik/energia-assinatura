<?php

namespace App\Services;

use App\Models\DadosGeracaoReal;
use App\Services\Concerns\CachesFindAll;

class DadosGeracaoRealService {

  use CachesFindAll;

  private DadosGeracaoReal $dadosGeracaoReal;
  private string $cacheKey = 'dados_geracao_real.find_all';

  public function __construct(DadosGeracaoReal $dadosGeracaoReal) {
    $this->dadosGeracaoReal = $dadosGeracaoReal;
  }

  public function create(array $data): int {
    $id = $this->dadosGeracaoReal->create($data)->dgr_id;

    $this->forgetFindAllCache($this->cacheKey);

    return $id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->dadosGeracaoReal->find($id);
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
    $registro = $this->dadosGeracaoReal->find($id);
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
    $dados = $this->dadosGeracaoReal->find($id);

    return $dados ? $dados->toArray() : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->dadosGeracaoReal->all();
    });
  }
}
