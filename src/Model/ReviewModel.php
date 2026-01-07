<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\Review;
use DateTimeImmutable;
use PDO;

final class ReviewModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * @return Review[]
     */
    public function findByContent(string $type, int $contentId): array
    {
        $statement = $this->pdo->prepare('SELECT a.*, u.prenom, u.nom FROM avis a INNER JOIN utilisateur u ON u.id = a.utilisateur_id WHERE a.type = :type AND a.contenu_id = :content ORDER BY a.created_at DESC');
        $statement->execute([
            'type' => $type,
            'content' => $contentId,
        ]);

        $reviews = [];
        while ($data = $statement->fetch()) {
            $reviews[] = $this->hydrateReview($data);
        }

        return $reviews;
    }

    public function create(Review $review): void
    {
        // Ajout d'un avis (note + commentaire).
        $statement = $this->pdo->prepare('INSERT INTO avis (utilisateur_id, contenu_id, type, note, commentaire, created_at) VALUES (:user, :content, :type, :note, :commentaire, :created_at)');
        $statement->execute([
            'user' => $review->getUserId(),
            'content' => $review->getContentId(),
            'type' => $review->getType(),
            'note' => $review->getRating(),
            'commentaire' => $review->getComment(),
            'created_at' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return Review[]
     */
    public function findByUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM avis WHERE utilisateur_id = :user ORDER BY created_at DESC');
        $statement->execute(['user' => $userId]);
        $reviews = [];
        while ($data = $statement->fetch()) {
            $reviews[] = $this->hydrateReview($data);
        }

        return $reviews;
    }

    public function delete(int $reviewId, int $userId): void
    {
        // Suppression par l'auteur.
        $statement = $this->pdo->prepare('DELETE FROM avis WHERE id = :id AND utilisateur_id = :user');
        $statement->execute([
            'id' => $reviewId,
            'user' => $userId,
        ]);
    }

    private function hydrateReview(array $data): Review
    {
        $review = new Review((int) $data['utilisateur_id'], (int) $data['contenu_id'], $data['type'], (int) $data['note'], $data['commentaire']);
        $review->setId((int) $data['id']);
        $review->setCreatedAt(new DateTimeImmutable($data['created_at']));
        if (isset($data['prenom'], $data['nom'])) {
            $review->setAuthorName(trim($data['prenom'] . ' ' . $data['nom']));
        }
        return $review;
    }
}
