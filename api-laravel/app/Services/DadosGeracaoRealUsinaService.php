<?php

namespace App\Services;

use App\Models\DadosGeracaoRealUsina;
use App\Services\Concerns\CachesFindAll;

class DadosGeracaoRealUsinaService {

  use CachesFindAll;

  private DadosGeracaoRealUsina $dadosGeracaoRealUsina;
  private string $cacheKey = 'dados_geracao_real_usina.find_all';

  public function __construct(DadosGeracaoRealUsina $dadosGeracaoRealUsina) {
    $this->dadosGeracaoRealUsina = $dadosGeracaoRealUsina;
  }

  public function create(array $data): int {
    $id = $this->dadosGeracaoRealUsina->create($data)->dgru_id;

    $this->forgetFindAllCache($this->cacheKey);

    return $id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->dadosGeracaoRealUsina->find($id);
    
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
    $registro = $this->dadosGeracaoRealUsina->find($id);
    
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
    $dados = $this->dadosGeracaoRealUsina->with([
      'dadosGeracaoReal',
    ])->find($id);
    return $dados ? $dados : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->dadosGeracaoRealUsina->with([
        'dadosGeracaoReal',
      ])->get();
    });
  }

  public function findByUsinaId(int $usi_id): array {
    return $this->dadosGeracaoRealUsina->where('usi_id', $usi_id)
    ->with([
      'dadosGeracaoReal'
    ])
    ->get()
    ->toArray();
  }
}
