<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\ValueObjects\UserId;
use App\Domain\Auth\ValueObjects\Email;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByCredentials(Email $email, string $password): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    public function existsByEmail(Email $email): bool;

    public function findActiveUsers(): LengthAwarePaginator;

    public function findByRole(string $role): LengthAwarePaginator;

    public function findUnverifiedUsers(): LengthAwarePaginator;

    public function countByStatus(string $status): int;

    public function findRecentlyActive(int $days = 30): LengthAwarePaginator;
}

