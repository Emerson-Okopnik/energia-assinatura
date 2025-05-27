<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Usina;

class UsinaService {
    
  private Usina $usina;

  public function __construct(Usina $usina) {
    $this->usina = $usina;
  }

  public function create(array $data): int {
    return $this->usina->create($data)->usi_id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->usina->find($id);
    return $registro ? $registro->update($data) : 0;
  }

  public function delete(int $id): int {
    return DB::transaction(function () use ($id) {
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
    return $this->usina->with([
      'cliente.endereco',
      'vendedor',
      'comercializacao',
      'dadoGeracao',
    ])->get()->toArray();
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
