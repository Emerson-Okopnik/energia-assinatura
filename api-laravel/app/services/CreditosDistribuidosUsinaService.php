<?php

namespace App\Services;

use App\Models\CreditosDistribuidosUsina;

class CreditosDistribuidosUsinaService {

    private CreditosDistribuidosUsina $creditosDistribuidosUsina;

    public function __construct(CreditosDistribuidosUsina $creditosDistribuidosUsina) {
        $this->creditosDistribuidosUsina = $creditosDistribuidosUsina;
    }

    public function create(array $data): int {
        return $this->creditosDistribuidosUsina->create($data)->cdu_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->creditosDistribuidosUsina->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->creditosDistribuidosUsina->find($id);
    
        return $registro ? $registro->delete() : 0;
    }

    public function findById(int $id): array|null {
        $usina = $this->creditosDistribuidosUsina->with([
          'usina.cliente.endereco',
          'usina.comercializacao',
          'usina.dadoGeracao',
          'creditosDistribuidos',
          'valorAcumuladoReserva',
          'faturamentoUsina',
        ])->find($id);
        return $usina ? $usina->toArray() : null;
    }

    public function findAll(): array {
        return $usina = $this->creditosDistribuidosUsina->with([
          'usina.cliente.endereco',
          'usina.comercializacao',
          'usina.dadoGeracao',
          'creditosDistribuidos',
          'valorAcumuladoReserva',
          'faturamentoUsina',
        ])->get()->toArray();
    }

    public function buscarPorAnoEUsina(int $usiId, int $ano): array {
    $dados = $this->creditosDistribuidosUsina
        ->whereHas('usina', function ($query) use ($usiId) {
            $query->where('usi_id', $usiId);
        })
        ->where('ano', $ano)
        ->with([
          'usina.cliente.endereco',
          'usina.comercializacao',
          'usina.dadoGeracao',
          'creditosDistribuidos',
          'valorAcumuladoReserva',
          'faturamentoUsina',
        ])
        ->get();

    return $dados->toArray();
}

}
