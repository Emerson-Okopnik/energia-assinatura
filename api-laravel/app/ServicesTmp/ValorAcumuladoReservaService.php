<?php

namespace App\Services;

use App\Models\ValorAcumuladoReserva;
use App\Services\Concerns\CachesFindAll;

class ValorAcumuladoReservaService {

    use CachesFindAll;

    private ValorAcumuladoReserva $valorAcumuladoReserva;
    private string $cacheKey = 'valor_acumulado_reserva.find_all';

    public function __construct(ValorAcumuladoReserva $valorAcumuladoReserva) {
        $this->valorAcumuladoReserva = $valorAcumuladoReserva;
    }

    public function create(): int {
        $registro = $this->valorAcumuladoReserva->create(); // cria com defaults
        $this->forgetFindAllCache($this->cacheKey);

        return $registro->var_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->valorAcumuladoReserva->find($id);
        
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
        $registro = $this->valorAcumuladoReserva->find($id);
    
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
        $dados = $this->valorAcumuladoReserva->find($id);
    
        return $dados ? $dados->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->valorAcumuladoReserva->all();
        });
    }
}
