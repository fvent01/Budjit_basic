<?php
// app/models/UserModel.php

class UserModel extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->findOneWhere(['email' => $email]);
    }

    public function createUser(string $firstName, string $lastName, string $email, string $password, int $roleId = 2): int
    {
        return $this->insert([
            'role_id'       => $roleId,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function updatePassword(int $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }

    public function getAllWithRoles(): array
    {
        return $this->query(
            "SELECT u.*, r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC"
        )->fetchAll();
    }
}
