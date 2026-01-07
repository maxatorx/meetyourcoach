<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final class UserSession
{
    private const SESSION_KEY = 'myc_user';

    public function setUser(User $user): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'id' => $user->getId(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'photo' => $user->getPhoto(),
            'bio' => $user->getBio(),
        ];
    }

    public function getUser(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public function isApprenant(): bool
    {
        return $this->isLoggedIn();
    }

    public function isFormateur(): bool
    {
        return $this->hasRole(User::ROLE_FORMATEUR);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(User::ROLE_ADMIN);
    }

    private function hasRole(string $role): bool
    {
        // Hierarchie simple des roles.
        $user = $this->getUser();
        if ($user === null) {
            return false;
        }

        $rolesHierarchy = [
            User::ROLE_APPRENANT => 1,
            User::ROLE_FORMATEUR => 2,
            User::ROLE_ADMIN => 3,
        ];

        $current = $rolesHierarchy[$user['role']] ?? 0;
        $required = $rolesHierarchy[$role] ?? 0;

        return $current >= $required;
    }
}
