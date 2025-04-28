<?php

namespace App\Services;

use App\Models\User;

class UserService {

    private User $user;

    public function __construct(User $user) {
        $this->user = $user;
    }

    public function create(array $data): int
    {
        return $this->user->create($data)->id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->user->find($id);

        return $registro ? $registro->update($data) : 0;
    }

    public function delete(int $id): int {
        $registro = $this->user->find($id);
    
        return $registro ? $registro->delete() : 0;
    }
    
    public function findById(int $id): ?User {
        return $this->user->find($id);
    }

    public function findAll(): array {
        $dados = $this->user->all();

        return $dados->isNotEmpty() ? $dados->toArray() : [];
    }
}
