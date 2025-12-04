<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\CachesFindAll;

class UserService {

    use CachesFindAll;

    private User $user;
    private string $cacheKey = 'user.find_all';

    public function __construct(User $user) {
        $this->user = $user;
    }

    public function create(array $data): int
    {
        $id = $this->user->create($data)->id;

        $this->forgetFindAllCache($this->cacheKey);

        return $id;
    }

    public function update(int $id, array $data): int {
        $registro = $this->user->find($id);
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
        $registro = $this->user->find($id);
    
        if (!$registro) {
            return 0;
        }

        $deleted = (int) $registro->delete();

        if ($deleted) {
            $this->forgetFindAllCache($this->cacheKey);
        }

        return $deleted;
    }
    
    public function findById(int $id): ?User {
        return $this->user->find($id);
    }

    public function findAll(): array {
        return $this->rememberFindAll($this->cacheKey, function () {
            return $this->user->all();
        });
    }
}
