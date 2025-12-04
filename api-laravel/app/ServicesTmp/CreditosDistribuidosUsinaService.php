<?php

namespace App\Services;

use App\Models\CreditosDistribuidosUsina;
use App\Services\Concerns\CachesFindAll;

class CreditosDistribuidosUsinaService {

    use CachesFindAll;

    private CreditosDistribuidosUsina $creditosDistribuidosUsina;
    private string $cacheKey = 'creditos_distribuidos_usina.find_all';

    public function __construct(CreditosDistribuidosUsina $creditosDistribuidosUsina) {
        $this->creditosDistribuidosUsina = $creditosDistribuidosUsina;
    }

    public function create(array $data): int {
        $id = $this->creditosDistribuidosUsina->create($data)->cdu_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->creditosDistribuidosUsina->find($id);
        
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
        $registro = $this->creditosDistribuidosUsina->find($id);
    
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
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->creditosDistribuidosUsina->with([
              'usina.cliente.endereco',
              'usina.comercializacao',
              'usina.dadoGeracao',
              'creditosDistribuidos',
              'valorAcumuladoReserva',
              'faturamentoUsina',
            ])->get();
        });
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
