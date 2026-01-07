<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\Course;
use DateTimeImmutable;
use PDO;

final class CourseModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * @return Course[]
     */
    public function findPublished(int $page, ?string $level = null): array
    {
        // Catalogue public : uniquement les cours publies.
        $limit = 10;
        $offset = max(0, $page - 1) * $limit;
        $sql = 'SELECT * FROM cours WHERE statut = :statut';
        if ($level) {
            $sql .= ' AND niveau = :niveau';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':statut', Course::STATUT_PUBLIE);
        if ($level) {
            $statement->bindValue(':niveau', $level);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $courses = [];
        while ($data = $statement->fetch()) {
            $courses[] = $this->hydrateCourse($data);
        }

        return $courses;
    }

    public function findById(int $id): ?Course
    {
        $statement = $this->pdo->prepare('SELECT * FROM cours WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();

        return $data ? $this->hydrateCourse($data) : null;
    }

    /**
     * @return Course[]
     */
    public function findByTrainer(int $trainerId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cours WHERE formateur_id = :trainer ORDER BY updated_at DESC');
        $statement->execute(['trainer' => $trainerId]);
        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->hydrateCourse($row);
        }

        return $items;
    }

    public function create(Course $course): Course
    {
        $statement = $this->pdo->prepare('INSERT INTO cours (titre, description, prix, niveau, statut, formateur_id, image_url, created_at, updated_at) VALUES (:titre, :description, :prix, :niveau, :statut, :formateur, :image, :created_at, :updated_at)');
        $statement->execute([
            'titre' => $course->getTitle(),
            'description' => $course->getDescription(),
            'prix' => $course->getPrice(),
            'niveau' => $course->getLevel(),
            'statut' => $course->getStatus(),
            'formateur' => $course->getTrainerId(),
            'image' => $course->getImageUrl(),
            'created_at' => $course->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $course->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
        $course->setId((int) $this->pdo->lastInsertId());

        return $course;
    }

    public function updateStatus(int $id, string $status): void
    {
        // Mise a jour du statut par l'admin.
        $statement = $this->pdo->prepare('UPDATE cours SET statut = :statut, updated_at = :updated WHERE id = :id');
        $statement->execute([
            'statut' => $status,
            'updated' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cours WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function update(Course $course): void
    {
        $statement = $this->pdo->prepare('UPDATE cours SET titre = :titre, description = :description, prix = :prix, niveau = :niveau, statut = :statut, image_url = :image, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'titre' => $course->getTitle(),
            'description' => $course->getDescription(),
            'prix' => $course->getPrice(),
            'niveau' => $course->getLevel(),
            'statut' => $course->getStatus(),
            'image' => $course->getImageUrl(),
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $course->getId(),
        ]);
    }

    /**
     * @return Course[]
     */
    public function findAll(): array
    {
        // Liste complete pour l'administration.
        $statement = $this->pdo->query('SELECT * FROM cours ORDER BY updated_at DESC');
        $courses = [];
        while ($row = $statement->fetch()) {
            $courses[] = $this->hydrateCourse($row);
        }

        return $courses;
    }

    private function hydrateCourse(array $data): Course
    {
        $course = new Course($data['titre'], $data['description'], (float) $data['prix'], (int) $data['formateur_id'], $data['niveau'], $data['statut']);
        $course->setId((int) $data['id']);
        $course->setImageUrl($data['image_url']);
        $course->setCreatedAt(new DateTimeImmutable($data['created_at']));
        $course->setUpdatedAt(new DateTimeImmutable($data['updated_at']));

        return $course;
    }
}
