<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Consumidor;

class ConsumidorService {
    
    private Consumidor $consumidor;

    public function __construct(Consumidor $consumidor) {
        $this->consumidor = $consumidor;
    }

    public function create(array $data): int {
        return $this->consumidor->create($data)->con_id;
    }

    public function update(int $id, array $data): int {
        $consumidor = $this->consumidor->find($id);
    
        return $consumidor ? $consumidor->update($data) : 0;
    }

    public function delete(int $id): int {
        return DB::transaction(function () use ($id) {
            $consumidor = Consumidor::find($id);
    
            if (!$consumidor) {
                return 0;
            }
            
            // Guarda o dados_consumo antes de deletar o consumidor
            $dados_consumo = $consumidor->dado_consumo;

            // Primeiro apaga o consumidor
            $consumidor->delete();
            
            // Depois apaga o dados_consumo (se houver)
            if ($dados_consumo) {
                $dados_consumo->delete();
            }
    
            return 1;
        });
    }

    public function findById(int $id): array|null {
        $consumidor = $this->consumidor->with(['cliente.endereco', 'dado_consumo'])->find($id);
        return $consumidor ? $consumidor->toArray() : null;
    }

    public function findAll(): array {
        return $this->consumidor->with(['cliente.endereco', 'dado_consumo'])->get()->toArray();
    }

    public function buscarNaoVinculados() {
    return $this->consumidor->whereNotIn('con_id', function ($query) {
            $query->select('con_id')
                  ->from('usina_consumidor');
        })->with([
            'cliente.endereco',
            'dado_consumo'
        ])->get();
    }
}
