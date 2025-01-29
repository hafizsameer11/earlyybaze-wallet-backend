<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function create(array $data): User
    {
        return User::create($data);
    }
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }
    public function delete(User $user): void
    {
        $user->delete();
    }
    public function getById(int $id): ?User
    {
        return User::find($id);
    }
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return User::all();
    }

    public function findByUserCode($userCode): ?User
    {
        return User::where('user_code', $userCode)->first();
    }
    public function findByEmail($email): ?User
    {
        return User::where('email', $email)->first();
    }
    public function setPin(User $user, string $pin): User
    {
        $user->pin = $pin;
        $user->save();
        return $user;
    }
    public function verifyPin(User $user, string $pin): bool
    {
        return $user->pin === $pin;
    }
}
