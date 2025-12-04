<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Cliente;
use App\Services\Concerns\CachesFindAll;

class ClienteService {

    use CachesFindAll;

    private Cliente $cliente;
    private string $cacheKey = 'cliente.find_all';

    public function __construct(Cliente $cliente) {
        $this->cliente = $cliente;
    }

    public function create(array $data): int {
        $id = $this->cliente->create($data)->cli_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->cliente->find($id);
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
            $cliente = Cliente::find($id);
    
            if (!$cliente) {
                return 0;
            }
    
            // Guarda o endereço antes de deletar o cliente
            $endereco = $cliente->endereco;
    
            // Primeiro apaga o cliente
            $cliente->delete();
    
            // Depois apaga o endereço (se houver)
            if ($endereco) {
                $endereco->delete();
            }
    
            return 1;
        });
        
        if ($deleted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $deleted;
    }

    public function findById(int $id): array|null {
        $cliente = $this->cliente->with(['endereco'])->find($id);
        
        return $cliente ? $cliente->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->cliente->with(['endereco'])->get();
        });
    }
}
