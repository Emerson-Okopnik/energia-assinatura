<?php

namespace App\Services;

use App\Models\DadosGeracaoRealUsina;

class DadosGeracaoRealUsinaService {

  private DadosGeracaoRealUsina $dadosGeracaoRealUsina;

  public function __construct(DadosGeracaoRealUsina $dadosGeracaoRealUsina) {
    $this->dadosGeracaoRealUsina = $dadosGeracaoRealUsina;
  }

  public function create(array $data): int {
    return $this->dadosGeracaoRealUsina->create($data)->dgru_id;
  }

  public function update(int $id, array $data): int {
    $registro = $this->dadosGeracaoRealUsina->find($id);

    return $registro ? $registro->update($data) : 0;
  }

  public function delete(int $id): int {
    $registro = $this->dadosGeracaoRealUsina->find($id);

    return $registro ? $registro->delete() : 0;
  }

  public function findById(int $id): array|null {
    $dados = $this->dadosGeracaoRealUsina->with([
      'cliente.endereco',
      'usina',
      'dadosGeracaoReal',
    ])->find($id);
    return $dados ? $dados : null;
  }

  public function findAll(): array {
    $dados = $this->dadosGeracaoRealUsina->with([
      'usina',
      'cliente.endereco',
      'dadosGeracaoReal',
    ])->get()->toArray();

    return $dados ? $dados : [];
  }

  public function findByUsinaId(int $usi_id): array {
    return $this->dadosGeracaoRealUsina->where('usi_id', $usi_id)
    ->with([
      'usina',
      'cliente.endereco',
      'dadosGeracaoReal'
    ])
    ->get()
    ->toArray();
  }
}
