<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\ValueObjects\UserId;
use App\Domain\Auth\ValueObjects\Email;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

final class UserRepository implements UserRepositoryInterface
{
    public function findById(UserId $id): ?User
    {
        return User::find($id->toString());
    }

    public function findByEmail(Email $email): ?User
    {
        return User::where('email', $email->toString())->first();
    }

    public function findByCredentials(Email $email, string $password): ?User
    {
        $user = $this->findByEmail($email);

        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }

        return null;
    }

    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        
        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function existsByEmail(Email $email): bool
    {
        return User::where('email', $email->toString())->exists();
    }

    public function findActiveUsers(): LengthAwarePaginator
    {
        return User::where('status', 'active')
            ->orderBy('last_login_at', 'desc')
            ->paginate(15);
    }

    public function findByRole(string $role): LengthAwarePaginator
    {
        return User::where('role', $role)
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function findUnverifiedUsers(): LengthAwarePaginator
    {
        return User::whereNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function countByStatus(string $status): int
    {
        return User::where('status', $status)->count();
    }

    public function findRecentlyActive(int $days = 30): LengthAwarePaginator
    {
        return User::where('last_login_at', '>=', now()->subDays($days))
            ->orderBy('last_login_at', 'desc')
            ->paginate(15);
    }
}

