<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\UsinaConsumidor;
use App\Services\Concerns\CachesFindAll;

class UsinaConsumidorService {
    
    use CachesFindAll;

    private UsinaConsumidor $usinaConsumidor;
    private string $cacheKey = 'usina_consumidor.find_all';

    public function __construct(UsinaConsumidor $usinaConsumidor) {
        $this->usinaConsumidor = $usinaConsumidor;
    }

    public function create(array $data): int {
        $id = $this->usinaConsumidor->create($data)->usic_id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): string {
        $registro = $this->usinaConsumidor->find($id);
    
        if (!$registro) {
            return 0;
        }
    
        UsinaConsumidor::where('usi_id', $registro->usi_id)->delete();
    
        $novos = [];
        $now = now();
        foreach ($data['con_ids'] as $conId) {
            $novos[] = [
                'usi_id' => $data['usi_id'],
                'cli_id' => $data['cli_id'],
                'con_id' => $conId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    
        if (!empty($novos)) {
            DB::table('usina_consumidor')->insert($novos);
        }
    
        $updated = (int) $registro->update($data);

        if ($updated) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return (string) $updated;
    }

    public function delete(int $id): int {
        $registro = $this->usinaConsumidor->find($id);
    
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
        $usinaConsumidor = $this->usinaConsumidor
            ->select(['usic_id', 'usi_id', 'con_id', 'cli_id', 'created_at', 'updated_at'])
            ->with([
                'usina' => function ($query) {
                    $query->select('usi_id', 'cli_id', 'dger_id', 'com_id', 'ven_id', 'uc', 'status')
                        ->with([
                            'dadoGeracao' => function ($q) {
                                $q->select('dger_id', 'media', 'menor_geracao');
                            },
                            'comercializacao' => function ($q) {
                                $q->select('com_id', 'valor_kwh', 'cia_energia', 'data_conexao');
                            },
                            'cliente' => function ($q) {
                                $q->select('cli_id', 'nome', 'cpf_cnpj', 'end_id')
                                    ->with(['endereco' => function ($eq) {
                                        $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                    }]);
                            }
                        ]);
                },
                'consumidor' => function ($query) {
                    $query->select('con_id', 'cli_id', 'dcon_id', 'ven_id', 'cia_energia', 'uc', 'status')
                        ->with([
                            'cliente' => function ($q) {
                                $q->select('cli_id', 'nome', 'cpf_cnpj', 'end_id')
                                    ->with(['endereco' => function ($eq) {
                                        $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                    }]);
                            },
                            'dado_consumo' => function ($q) {
                                $q->select('dcon_id', 'media');
                            }
                        ]);
                }
            ])
            ->find($id);
        return $usinaConsumidor ? $usinaConsumidor->toArray() : null;
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->usinaConsumidor
                ->select(['usic_id', 'usi_id', 'con_id', 'cli_id', 'created_at', 'updated_at'])
                ->with([
                    'usina' => function ($query) {
                        $query->select('usi_id', 'cli_id', 'dger_id', 'com_id', 'ven_id', 'uc', 'status')
                            ->with([
                                'dadoGeracao' => function ($q) {
                                    $q->select('dger_id', 'media', 'menor_geracao');
                                },
                                'comercializacao' => function ($q) {
                                    $q->select('com_id', 'valor_kwh', 'cia_energia', 'data_conexao');
                                },
                                'cliente' => function ($q) {
                                    $q->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'end_id')
                                        ->with(['endereco' => function ($eq) {
                                            $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                        }]);
                                },
                                'vendedor' => function ($q) {
                                    $q->select('ven_id', 'nome');
                                }
                            ]);
                    },
                    'consumidor' => function ($query) {
                        $query->select('con_id', 'cli_id', 'dcon_id', 'ven_id', 'cia_energia', 'uc', 'status')
                            ->with([
                                'cliente' => function ($q) {
                                    $q->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'end_id')
                                        ->with(['endereco' => function ($eq) {
                                            $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                        }]);
                                },
                                'dado_consumo' => function ($q) {
                                    $q->select('dcon_id', 'media');
                                },
                                'vendedor' => function ($q) {
                                    $q->select('ven_id', 'nome');
                                }
                            ]);
                    }
                ])
                ->get();
        });
    }

    public function createMany(array $data): int {
        $inserts = [];
        $now = now();
    
        foreach ($data['con_ids'] as $conId) {
            $inserts[] = [
                'usi_id' => $data['usi_id'],
                'cli_id' => $data['cli_id'],
                'con_id' => $conId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    
        $inserted = !empty($inserts) && DB::table('usina_consumidor')->insert($inserts) ? count($inserts) : 0;

        if ($inserted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $inserted;
    }

    public function deleteVinculo(int $usi_id, int $con_id): bool {
        $deleted = UsinaConsumidor::where('usi_id', $usi_id)
            ->where('con_id', $con_id)
            ->delete() > 0;

        if ($deleted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $deleted;
    }

    public function findByUsinaId(int $usi_id) {
        return $this->usinaConsumidor
            ->select(['usic_id', 'usi_id', 'con_id', 'cli_id'])
            ->where('usi_id', $usi_id)
            ->with([
                'usina' => function ($query) {
                    $query->select('usi_id', 'cli_id', 'dger_id', 'com_id', 'ven_id', 'uc', 'status')
                        ->with([
                            'cliente' => function ($q) {
                                $q->select('cli_id', 'nome', 'cpf_cnpj', 'end_id')
                                    ->with(['endereco' => function ($eq) {
                                        $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                    }]);
                            },
                            'comercializacao' => function ($q) {
                                $q->select('com_id', 'valor_kwh', 'cia_energia');
                            },
                            'dadoGeracao' => function ($q) {
                                $q->select('dger_id', 'media');
                            },
                            'vendedor' => function ($q) {
                                $q->select('ven_id', 'nome');
                            }
                        ]);
                },
                'consumidor' => function ($query) {
                    $query->select('con_id', 'cli_id', 'dcon_id', 'ven_id', 'cia_energia', 'uc', 'status')
                        ->with([
                            'cliente' => function ($q) {
                                $q->select('cli_id', 'nome', 'cpf_cnpj', 'end_id')
                                    ->with(['endereco' => function ($eq) {
                                        $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                    }]);
                            },
                            'dado_consumo' => function ($q) {
                                $q->select('dcon_id', 'media');
                            },
                            'vendedor' => function ($q) {
                                $q->select('ven_id', 'nome');
                            }
                        ]);
                }
            ])
            ->get();
    }
}
