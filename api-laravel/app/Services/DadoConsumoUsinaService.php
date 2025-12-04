<?php

namespace App\Services;

use App\Models\DadoConsumoUsina;
use App\Services\Concerns\CachesFindAll;

class DadoConsumoUsinaService {

    use CachesFindAll;

    private DadoConsumoUsina $dadoConsumoUsina;
    private string $cacheKey = 'dado_consumo_usina.find_all';

    public function __construct(DadoConsumoUsina $dadoConsumoUsina) {
        $this->dadoConsumoUsina = $dadoConsumoUsina;
    }

    public function create(array $data): int {
        $id = $this->dadoConsumoUsina->create($data)->dcu_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->dadoConsumoUsina->find($id);

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
        $registro = $this->dadoConsumoUsina->find($id);

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
        $dados = $this->dadoConsumoUsina->with([
            'cliente.endereco',
            'usina',
            'dadoConsumo',
        ])->find($id);

        return $dados ? $dados : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->dadoConsumoUsina->with([
                'usina',
                'cliente.endereco',
                'dadoConsumo',
            ])->get();
        });
    }

    public function findByUsinaId(int $usiId): array {
        return $this->dadoConsumoUsina->where('usi_id', $usiId)
            ->with([
                'usina',
                'cliente.endereco',
                'dadoConsumo'
            ])
            ->get()
            ->toArray();
    }
}