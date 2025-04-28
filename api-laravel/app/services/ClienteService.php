<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Cliente;

class ClienteService {

    private Cliente $cliente;

    public function __construct(Cliente $cliente) {
        $this->cliente = $cliente;
    }

    public function create(array $data): int {
        return $this->cliente->create($data)->cli_id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->cliente->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        return DB::transaction(function () use ($id) {
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
    }

    public function findById(int $id): array|null {
        $cliente = $this->cliente->with(['endereco'])->find($id);
        
        return $cliente ? $cliente->toArray() : null;
    }

    public function findAll(): array {
        return $this->cliente->with(['endereco'])->get()->toArray();
    }
}
