<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class User
{
    public const ROLE_APPRENANT = 'apprenant';
    public const ROLE_FORMATEUR = 'formateur';
    public const ROLE_ADMIN = 'admin';

    private ?int $id = null;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $passwordHash;
    private string $role;
    private ?string $rememberToken = null;
    private ?string $photo = null;
    private ?string $bio = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(string $firstName, string $lastName, string $email, string $plainPassword, string $role = self::ROLE_APPRENANT)
    {
        $this->setFirstName($firstName);
        $this->setLastName($lastName);
        $this->setEmail($email);
        $this->setRole($role);
        $this->setPassword($plainPassword);
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        if ($id !== null && $id < 0) {
            throw new InvalidArgumentException('Invalid user id');
        }

        $this->id = $id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $firstName = trim($firstName);
        if ($firstName === '') {
            throw new InvalidArgumentException('First name required');
        }

        $this->firstName = $firstName;
        $this->touch();
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $lastName = trim($lastName);
        if ($lastName === '') {
            throw new InvalidArgumentException('Last name required');
        }

        $this->lastName = $lastName;
        $this->touch();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }

        $this->email = $email;
        $this->touch();
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPassword(string $plainPassword): void
    {
        if (strlen($plainPassword) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters');
        }

        $this->passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->touch();
    }

    public function setPasswordHash(string $hash): void
    {
        if ($hash === '') {
            throw new InvalidArgumentException('Password hash is required');
        }

        $this->passwordHash = $hash;
        $this->touch();
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $role = strtolower($role);
        $allowed = [self::ROLE_APPRENANT, self::ROLE_FORMATEUR, self::ROLE_ADMIN];
        if (!in_array($role, $allowed, true)) {
            throw new InvalidArgumentException('Invalid role');
        }

        $this->role = $role;
        $this->touch();
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $token): void
    {
        $this->rememberToken = $token;
        $this->touch();
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): void
    {
        $this->photo = $photo;
        $this->touch();
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'role' => $this->role,
            'remember_token' => $this->rememberToken,
            'photo' => $this->photo,
            'bio' => $this->bio,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
