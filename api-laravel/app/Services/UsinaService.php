<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Usina;
use App\Services\Concerns\CachesFindAll;

class UsinaService {
    
  use CachesFindAll;

  private Usina $usina;
  private string $cacheKey = 'usina.find_all';

  public function __construct(Usina $usina) {
    $this->usina = $usina;
  }

  public function create(array $data): int {
    $id = $this->usina->create($data)->usi_id;

    $this->forgetFindAllCache($this->cacheKey);

    return $id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->usina->find($id);
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
    $deleted = DB::transaction(function () use ($id) {
      $usina = $this->usina
        ->with(['dadoGeracao', 'comercializacao', 'cliente'])
        ->find($id);
      
      if (!$usina) {
        return 0;
      }

      $this->deletarCreditosDistribuidosOptimized($id);
      $this->deletarDadosGeracaoRealOptimized($id);

      // Primeiro, apaga a usina para quebrar a FK
      $usina->delete();
    
      // Depois, apaga os relacionados (se existirem)
      if ($usina->dadoGeracao) {
        $usina->dadoGeracao->delete();
      }
    
      if ($usina->comercializacao) {
        $usina->comercializacao->delete();
      }
    
      if ($usina->cliente) {
        $usina->cliente->delete();
      }
    
      return 1;
    });

    if ($deleted) {
      $this->forgetFindAllCache($this->cacheKey);
    }

    return $deleted;
  }

  private function deletarCreditosDistribuidosOptimized(int $usiId): void {
    // Delete all related records in a single query using subqueries
    DB::table('creditos_distribuidos')
        ->whereIn('cd_id', function ($query) use ($usiId) {
            $query->select('cd_id')
                ->from('creditos_distribuidos_usina')
                ->where('usi_id', $usiId);
        })
        ->delete();

    DB::table('valor_acumulado_reserva')
        ->whereIn('var_id', function ($query) use ($usiId) {
            $query->select('var_id')
                ->from('creditos_distribuidos_usina')
                ->where('usi_id', $usiId);
        })
        ->delete();

    DB::table('faturamento_usina')
        ->whereIn('fa_id', function ($query) use ($usiId) {
            $query->select('fa_id')
                ->from('creditos_distribuidos_usina')
                ->where('usi_id', $usiId);
        })
        ->delete();

    DB::table('creditos_distribuidos_usina')->where('usi_id', $usiId)->delete();
  }

  private function deletarDadosGeracaoRealOptimized(int $usiId): void {
    // Delete all related records in a single query
    DB::table('dados_geracao_real')
        ->whereIn('dgr_id', function ($query) use ($usiId) {
            $query->select('dgr_id')
                ->from('dados_geracao_real_usina')
                ->where('usi_id', $usiId);
        })
        ->delete();

    DB::table('dados_geracao_real_usina')->where('usi_id', $usiId)->delete();
  }

  // Deprecated methods kept for backward compatibility
  private function deletarCreditosDistribuidos(int $usiId): void {
    $this->deletarCreditosDistribuidosOptimized($usiId);
  }

  private function deletarDadosGeracaoReal(int $usiId): void {
    $this->deletarDadosGeracaoRealOptimized($usiId);
  }
    
  public function findById(int $id): array|null {
    $usina = $this->buildUsinaQuery()->find($id);
    return $usina ? $usina->toArray() : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->buildUsinaQuery()->get();
    });
  }

  public function findPaginated(int $perPage = 10, int $page = 1): LengthAwarePaginator {
    return $this->buildUsinaQuery()
      ->paginate($perPage, ['*'], 'page', $page);
  }

  public function findListagemPaginated(int $perPage = 10, int $page = 1): LengthAwarePaginator {
    return DB::table('usina as u')
      ->join('cliente as c', 'c.cli_id', '=', 'u.cli_id')
      ->leftJoin('endereco as e', 'e.end_id', '=', 'c.end_id')
      ->leftJoin('dados_geracao as dg', 'dg.dger_id', '=', 'u.dger_id')
      ->leftJoin('comercializacao as com', 'com.com_id', '=', 'u.com_id')
      ->selectRaw("
        u.usi_id as usi_id,
        c.nome as nome_cliente,
        concat(coalesce(e.cidade, '-'), ' - ', coalesce(e.estado, '-')) as endereco,
        u.status as status,
        u.rede as rede,
        coalesce(dg.media, 0) as media_geracao_kwh,
        coalesce(u.uc, '-') as unidade_consumidor,
        coalesce(com.cia_energia, '-') as cia_energia,
        com.data_conexao as data_conexao
      ")
      ->orderByDesc('u.usi_id')
      ->paginate($perPage, ['*'], 'page', $page);
  }

  public function searchByClienteNomePaginated(
    string $nomeCliente,
    int $perPage = 10,
    int $page = 1
  ): LengthAwarePaginator {
    $nomeCliente = trim($nomeCliente);

    return $this->buildUsinaListagemQuery()
      ->whereHas('cliente', function ($query) use ($nomeCliente) {
        $query->whereRaw(
          "translate(lower(nome), 'áàâãäéèêëíìîïóòôõöúùûüç', 'aaaaaeeeeiiiiooooouuuuc') " .
          "LIKE translate(lower(?), 'áàâãäéèêëíìîïóòôõöúùûüç', 'aaaaaeeeeiiiiooooouuuuc')",
          ['%' . $nomeCliente . '%']
        );
      })
      ->orderByDesc('usi_id')
      ->paginate($perPage, ['*'], 'page', $page);
  }
  
  public function buscarNaoVinculados() {
    return $this->usina
    ->select([
      'usi_id', 'cli_id', 'dger_id', 'com_id', 'ven_id', 'uc', 'rede',
      'data_limite_troca_titularidade', 'data_ass_contrato', 'status', 'andamento_processo'
    ])
    ->whereNotIn('usi_id', function ($query) {
      $query->select('usi_id')->from('usina_consumidor');
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
      'dadoGeracao' => function ($query) {
        $query->select('dger_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
          'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'media', 'menor_geracao');
      }
    ])
    ->get();
  }

  public function listarAnosPorUsina(int $usiId): array {
    return DB::table('creditos_distribuidos_usina')
    ->where('usi_id', $usiId)
    ->distinct()
    ->orderBy('ano')
    ->pluck('ano')
    ->toArray();
  }

  private function buildUsinaQuery(): Builder {
    return $this->usina
      ->select([
        'usi_id', 'cli_id', 'dger_id', 'com_id', 'ven_id', 'uc', 'rede',
        'data_limite_troca_titularidade', 'data_ass_contrato', 'status', 'andamento_processo',
        'created_at', 'updated_at'
      ])
      ->with([
        'cliente' => function ($query) {
          $query->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'email', 'end_id')
            ->with(['endereco' => function ($q) {
              $q->select('end_id', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep');
            }]);
        },
        'comercializacao' => function ($query) {
          $query->select(
            'com_id',
            'valor_kwh',
            'valor_fixo',
            'cia_energia',
            'valor_final_media',
            'previsao_conexao',
            'data_conexao',
            'fio_b',
            'percentual_lei'
          );
        },
        'vendedor' => function ($query) {
          $query->select('ven_id', 'nome', 'email', 'telefone');
        },
        'dadoGeracao' => function ($query) {
          $query->select('dger_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'media', 'menor_geracao');
        },
      ]);
  }

  private function buildUsinaListagemQuery(): Builder {
    return $this->usina
      ->select([
        'usi_id', 'cli_id', 'dger_id', 'com_id', 'uc', 'rede', 'status'
      ])
      ->with([
        'cliente' => function ($query) {
          $query->select('cli_id', 'nome', 'end_id')
            ->with(['endereco' => function ($q) {
              $q->select('end_id', 'cidade', 'estado');
            }]);
        },
        'comercializacao' => function ($query) {
          $query->select('com_id', 'cia_energia', 'data_conexao');
        },
        'dadoGeracao' => function ($query) {
          $query->select('dger_id', 'media');
        },
      ]);
  }
}
