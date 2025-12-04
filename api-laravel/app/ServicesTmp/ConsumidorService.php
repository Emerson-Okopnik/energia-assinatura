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
      $consumidor = Consumidor::with(['dado_consumo', 'cliente.endereco'])->find($id);
    
      if (!$consumidor) {
        return 0;
      }
      
      // Primeiro apaga o consumidor
      $consumidor->delete();
            
      // Depois apaga os relacionados
      if ($consumidor->dado_consumo) {
        $consumidor->dado_consumo->delete();
      }

      if ($consumidor->cliente) {
        if ($consumidor->cliente->endereco) {
          $consumidor->cliente->endereco->delete();
        }
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
    $consumidor = $this->consumidor
      ->select([
        'con_id', 'cli_id', 'dcon_id', 'ven_id', 'cia_energia', 'uc', 'rede',
        'data_entrega', 'status', 'alocacao', 'created_at', 'updated_at'
      ])
      ->with([
        'cliente' => function ($query) {
          $query->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'email', 'end_id')
            ->with(['endereco' => function ($q) {
              $q->select('end_id', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep');
            }]);
        },
        'vendedor' => function ($query) {
          $query->select('ven_id', 'nome', 'email', 'telefone');
        },
        'dado_consumo' => function ($query) {
          $query->select('dcon_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'media');
        }
      ])
      ->find($id);
    return $consumidor ? $consumidor->toArray() : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->consumidor
        ->select([
          'con_id', 'cli_id', 'dcon_id', 'ven_id', 'cia_energia', 'uc', 'rede',
          'data_entrega', 'status', 'alocacao', 'created_at', 'updated_at'
        ])
        ->with([
          'cliente' => function ($query) {
            $query->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'email', 'end_id')
              ->with(['endereco' => function ($q) {
                $q->select('end_id', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep');
              }]);
          },
          'vendedor' => function ($query) {
            $query->select('ven_id', 'nome', 'email', 'telefone');
          },
          'dado_consumo' => function ($query) {
            $query->select('dcon_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
              'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'media');
          }
        ])
        ->get();
    });
  }

  public function buscarNaoVinculados() {
    return $this->consumidor
    ->select([
      'con_id', 'cli_id', 'dcon_id', 'ven_id', 'cia_energia', 'uc', 'rede',
      'data_entrega', 'status', 'alocacao'
    ])
    ->whereNotIn('con_id', function ($query) {
      $query->select('con_id')
        ->from('usina_consumidor');
    })
    ->with([
      'cliente' => function ($query) {
        $query->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'email', 'end_id')
          ->with(['endereco' => function ($q) {
            $q->select('end_id', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep');
          }]);
      },
      'vendedor' => function ($query) {
        $query->select('ven_id', 'nome', 'email', 'telefone');
      },
      'dado_consumo' => function ($query) {
        $query->select('dcon_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
          'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'media');
      }
    ])
    ->get();
  }
}
