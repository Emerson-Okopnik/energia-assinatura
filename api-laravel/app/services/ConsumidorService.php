<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Consumidor;
use App\Services\Concerns\CachesFindAll;

class ConsumidorService {
    
  use CachesFindAll;

  private Consumidor $consumidor;
  private string $cacheKey = 'consumidor.find_all';

  public function __construct(Consumidor $consumidor) {
    $this->consumidor = $consumidor;
  }

  public function create(array $data): int {
    $id = $this->consumidor->create($data)->con_id;

    $this->forgetFindAllCache($this->cacheKey);

    return $id;
  }

  public function update(int $id, array $data): int {
    $consumidor = $this->consumidor->find($id);
    
    if (!$consumidor) {
      return 0;
    }

    $updated = (int) $consumidor->update($data);

    if ($updated) {
      $this->forgetFindAllCache($this->cacheKey);
    }

    return $updated;
  }

  public function delete(int $id): int {
    $deleted = DB::transaction(function () use ($id) {
      $consumidor = Consumidor::find($id);
    
      if (!$consumidor) {
        return 0;
      }

      $consumidor->loadMissing(['dado_consumo', 'cliente']);
      
      // Primeiro apaga o consumidor
      $consumidor->delete();
            
      // Depois apaga os relacionados
      if ($consumidor->dado_consumo) {
        $consumidor->dado_consumo->delete();
      }

      if ($consumidor->cliente) {
        $consumidor->cliente->delete();
      }
    
      return 1;
    });
    
    if ($deleted) {
      $this->forgetFindAllCache($this->cacheKey);
    }

    return $deleted;
  }

  public function findById(int $id): array|null {
    $consumidor = $this->consumidor->with(['cliente.endereco', 'vendedor', 'dado_consumo'])->find($id);
    return $consumidor ? $consumidor->toArray() : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->consumidor->with(['cliente.endereco', 'vendedor', 'dado_consumo'])->get();
    });
  }

  public function buscarNaoVinculados() {
    return $this->consumidor
    ->whereNotIn('con_id', function ($query) {
      $query->select('con_id')
        ->from('usina_consumidor');
    })
    ->with([
      'cliente.endereco',
      'vendedor',
      'dado_consumo'
    ])
    ->get();
  }
}
