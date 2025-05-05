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

    public function delete(int $id): int
    {
        return DB::transaction(function () use ($id) {
            $usina = $this->usina
                ->with(['dadoGeracao', 'comercializacao', 'cliente'])
                ->find($id);
    
            if (!$usina) {
                return 0;
            }
    
            // Primeiro, apaga a usina para quebrar a FK
            $usina->delete();
    
            // Depois, apaga os relacionados (se existirem)
            if ($usina->dadoGeracao) {
                $usina->dadoGeracao->delete();
            }
    
            if ($usina->comercializacao) {
                $usina->comercializacao->delete();
            }
    
            /* if ($usina->cliente) {
                $usina->cliente->delete();
            } */
    
            return 1;
        });
    }
    
    
    public function findById(int $id): array|null {
        $usina = $this->usina->with([
            'cliente.endereco',
            'comercializacao',
            'dadoGeracao',
            'creditosDistribuidosUsina.creditosDistribuidos',
            'creditosDistribuidosUsina.valorAcumuladoReserva',
            'creditosDistribuidosUsina.faturamentoUsina'
           /*'consumidor.cliente.endereco',
            'consumidor.dado_consumo'*/
        ])->find($id);
        return $usina ? $usina->toArray() : null;
    }

    public function findAll(): array {
        return $this->usina->with([
            'cliente.endereco',
            'comercializacao',
            'dadoGeracao',
           /*consumidor.cliente.endereco',
            'consumidor.dado_consumo'*/
        ])->get()->toArray();
    }

    //ENVIA A LISTA APENAS COM OS IDS
    /*public function findAll(): array {  
        $dados = $this->usina->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }*/

    public function buscarNaoVinculados() {
      return $this->usina->whereNotIn('usi_id', function ($query) {
        $query->select('con_id')
              ->from('usina_consumidor');
        })->with([
          'cliente.endereco',
          'dadoGeracao'
        ])->get();
    }
}
