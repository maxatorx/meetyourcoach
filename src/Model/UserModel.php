<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\User;
use DateTimeImmutable;
use PDO;

final class UserModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare('SELECT * FROM utilisateur WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();

        return $data ? $this->hydrateUser($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare('SELECT * FROM utilisateur WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $data = $statement->fetch();

        return $data ? $this->hydrateUser($data) : null;
    }

    public function create(User $user): User
    {
        // Creation d'un compte utilisateur (mot de passe deja chiffre dans l'entite).
        $statement = $this->pdo->prepare(
            'INSERT INTO utilisateur (prenom, nom, email, mot_de_passe, role, remember_token, photo, created_at, updated_at) VALUES (:prenom, :nom, :email, :password, :role, :token, :photo, :created_at, :updated_at)'
        );

        $statement->execute([
            'prenom' => $user->getFirstName(),
            'nom' => $user->getLastName(),
            'email' => $user->getEmail(),
            'password' => $user->getPasswordHash(),
            'role' => $user->getRole(),
            'token' => $user->getRememberToken(),
            'photo' => $user->getPhoto(),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        $user->setId((int) $this->pdo->lastInsertId());

        return $user;
    }

    public function updateRememberToken(int $userId, ?string $token): void
    {
        $statement = $this->pdo->prepare('UPDATE utilisateur SET remember_token = :token WHERE id = :id');
        $statement->execute([
            'token' => $token,
            'id' => $userId,
        ]);
    }

    public function updateProfile(int $userId, array $data): void
    {
        $statement = $this->pdo->prepare('UPDATE utilisateur SET prenom = :prenom, nom = :nom, photo = :photo, biographie = :bio, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'prenom' => $data['first_name'] ?? null,
            'nom' => $data['last_name'] ?? null,
            'photo' => $data['photo'] ?? null,
            'bio' => $data['bio'] ?? null,
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query('SELECT * FROM utilisateur ORDER BY created_at DESC');
        $users = [];
        while ($row = $statement->fetch()) {
            $users[] = $this->hydrateUser($row);
        }

        return $users;
    }

    public function updateRole(int $userId, string $role): void
    {
        $statement = $this->pdo->prepare('UPDATE utilisateur SET role = :role WHERE id = :id');
        $statement->execute([
            'role' => $role,
            'id' => $userId,
        ]);
    }

    private function hydrateUser(array $data): User
    {
        // On cree l'objet avec un mot de passe fictif puis on remplace par le hash stocke.
        $user = new User($data['prenom'], $data['nom'], $data['email'], 'Password123!');
        $user->setId((int) $data['id']);
        $user->setPasswordHash($data['mot_de_passe']);
        $user->setRole($data['role']);
        $user->setRememberToken($data['remember_token']);
        if (isset($data['photo'])) {
            $user->setPhoto($data['photo']);
        }
        if (isset($data['biographie'])) {
            $user->setBio($data['biographie']);
        }
        $user->setCreatedAt(new DateTimeImmutable($data['created_at']));
        $user->setUpdatedAt(new DateTimeImmutable($data['updated_at']));

        return $user;
    }
}
