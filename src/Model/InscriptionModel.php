<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\Inscription;
use PDO;

final class InscriptionModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function isRegistered(int $userId, string $type, int $contentId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM inscription WHERE utilisateur_id = :user AND contenu_id = :content AND type = :type');
        $statement->execute([
            'user' => $userId,
            'content' => $contentId,
            'type' => $type,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function register(Inscription $inscription): void
    {
        // Inscription d'un utilisateur a un cours/atelier.
        $statement = $this->pdo->prepare(
            'INSERT INTO inscription (utilisateur_id, contenu_id, type, created_at) VALUES (:user, :content, :type, :created_at)'
        );
        $statement->execute([
            'user' => $inscription->getUserId(),
            'content' => $inscription->getContentId(),
            'type' => $inscription->getType(),
            'created_at' => $inscription->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function unregister(int $userId, string $type, int $contentId): void
    {
        // Desinscription.
        $statement = $this->pdo->prepare('DELETE FROM inscription WHERE utilisateur_id = :user AND type = :type AND contenu_id = :content');
        $statement->execute([
            'user' => $userId,
            'type' => $type,
            'content' => $contentId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM inscription WHERE utilisateur_id = :user ORDER BY created_at DESC');
        $statement->execute(['user' => $userId]);

        return $statement->fetchAll() ?: [];
    }
}
