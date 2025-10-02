<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
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
        ->with(['dadoGeracao', 'comercializacao', 'cliente', 'dadosGeracaoRealUsina'])
        ->find($id);
      
      if (!$usina) {
        return 0;
      }

      $this->deletarCreditosDistribuidos($id);
      $this->deletarDadosGeracaoReal($id);

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

  private function deletarCreditosDistribuidos(int $usiId): void {
    $creditos = DB::table('creditos_distribuidos_usina')->where('usi_id', $usiId)->get();

    foreach ($creditos as $registro) {
      DB::table('creditos_distribuidos')->where('cd_id', $registro->cd_id)->delete();
      DB::table('valor_acumulado_reserva')->where('var_id', $registro->var_id)->delete();
      DB::table('faturamento_usina')->where('fa_id', $registro->fa_id)->delete();
    }

    DB::table('creditos_distribuidos_usina')->where('usi_id', $usiId)->delete();
  }

  private function deletarDadosGeracaoReal(int $usiId): void {
    // Busca todos os vínculos na tabela intermediária
    $vinculos = DB::table('dados_geracao_real_usina')
        ->where('usi_id', $usiId)
        ->get();

    foreach ($vinculos as $registro) {
        // Deleta o dado de geração real vinculado (se existir)
        DB::table('dados_geracao_real')->where('dgr_id', $registro->dgr_id)->delete();
    }

    // Após excluir os dados, remove os vínculos intermediários
    DB::table('dados_geracao_real_usina')->where('usi_id', $usiId)->delete();
  }
    
  public function findById(int $id): array|null {
    $usina = $this->usina->with([
      'cliente.endereco',
      'comercializacao',
      'vendedor',
      'dadoGeracao',
    ])->find($id);
    return $usina ? $usina->toArray() : null;
  }

  public function findAll(): array {
    return $this->rememberFindAll($this->cacheKey, function () {
      return $this->usina->with([
        'cliente.endereco',
        'vendedor',
        'comercializacao',
        'dadoGeracao',
      ])->get();
    });
  }
  
  public function buscarNaoVinculados() {
    return $this->usina
    ->whereNotIn('usi_id', function ($query) {
      $query->select('usi_id')->from('usina_consumidor');
    })
    ->with([
      'cliente.endereco',
      'vendedor',
      'dadoGeracao'
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
}
