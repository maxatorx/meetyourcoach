<?php

declare(strict_types=1);

namespace App\Model;

use App\Config\Database;
use App\Entity\Course;
use App\Entity\Workshop;
use PDO;

final class ContentModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * @return ContentItem[]
     */
    public function getPublished(string $type, int $page): array
    {
        // Catalogue public : uniquement contenus validés.
        $limit = 10;
        $offset = max(0, $page - 1) * $limit;
        return match ($type) {
            'cours' => $this->fetchCourses($limit, $offset),
            'atelier' => $this->fetchWorkshops($limit, $offset),
            default => $this->fetchAll($limit, $offset),
        };
    }

    /**
     * @param array{titre?:string,type?:string,niveau?:string,date?:string} $filters
     * @return ContentItem[]
     */
    public function search(array $filters): array
    {
        // Recherche publique avec filtres simples.
        $conditions = [];
        $params = [];

        if (($filters['type'] ?? '') === 'cours') {
            $conditions[] = "type = 'cours'";
        } elseif (($filters['type'] ?? '') === 'atelier') {
            $conditions[] = "type = 'atelier'";
        }

        if (!empty($filters['titre'])) {
            $conditions[] = 'LOWER(title) LIKE :titre';
            $params['titre'] = '%' . strtolower($filters['titre']) . '%';
        }

        if (!empty($filters['niveau'])) {
            $conditions[] = '(level = :niveau OR level IS NULL)';
            $params['niveau'] = $filters['niveau'];
        }

        if (!empty($filters['date'])) {
            $conditions[] = '(scheduled_at = :date OR scheduled_at IS NULL)';
            $params['date'] = $filters['date'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = 'SELECT * FROM (' . $this->buildUnionQuery() . ') aggregated ' . $where . ' ORDER BY created_at DESC LIMIT 50';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':course_status', Course::STATUT_PUBLIE);
        $statement->bindValue(':workshop_status', Workshop::STATUT_VALIDE);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->execute();

        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->mapRow($row);
        }

        return $items;
    }

    /**
     * @return ContentItem[]
     */
    private function fetchCourses(int $limit, int $offset): array
    {
        $sql = 'SELECT c.id, c.titre AS title, c.description, c.prix AS price, c.niveau AS level, c.statut AS status, c.image_url,
                CONCAT(u.prenom, " ", u.nom) AS trainer, c.created_at, NULL AS scheduled_at,
                NULL AS remaining_seats,
                "cours" AS type
                FROM cours c
                INNER JOIN utilisateur u ON u.id = c.formateur_id
                WHERE c.statut = :statut
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':statut', Course::STATUT_PUBLIE);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->mapRow($row);
        }

        return $items;
    }

    /**
     * @return ContentItem[]
     */
    private function fetchWorkshops(int $limit, int $offset): array
    {
        $sql = 'SELECT a.id, a.titre AS title, a.description, a.prix AS price, NULL AS level, a.statut AS status, a.image_url,
                CONCAT(u.prenom, " ", u.nom) AS trainer, a.created_at, a.scheduled_at,
                (a.nb_places - a.nb_inscrits) AS remaining_seats,
                "atelier" AS type
                FROM atelier a
                INNER JOIN utilisateur u ON u.id = a.formateur_id
                WHERE a.statut = :valide
                ORDER BY a.scheduled_at ASC
                LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':valide', Workshop::STATUT_VALIDE);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->mapRow($row);
        }

        return $items;
    }

    /**
     * @return ContentItem[]
     */
    private function fetchAll(int $limit, int $offset): array
    {
        $sql = 'SELECT * FROM (' . $this->buildUnionQuery() . ') aggregated ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':course_status', Course::STATUT_PUBLIE);
        $statement->bindValue(':workshop_status', Workshop::STATUT_VALIDE);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = [];
        while ($row = $statement->fetch()) {
            $items[] = $this->mapRow($row);
        }

        return $items;
    }

    private function buildUnionQuery(): string
    {
        return <<<SQL
            SELECT c.id, 'cours' AS type, c.titre AS title, CONCAT(u.prenom, ' ', u.nom) AS trainer, c.prix AS price, c.image_url, c.niveau AS level, c.statut AS status, c.created_at, NULL AS scheduled_at, NULL AS remaining_seats
            FROM cours c
            INNER JOIN utilisateur u ON u.id = c.formateur_id
            WHERE c.statut = :course_status
            UNION ALL
            SELECT a.id, 'atelier' AS type, a.titre AS title, CONCAT(u.prenom, ' ', u.nom) AS trainer, a.prix AS price, a.image_url, NULL AS level, a.statut AS status, a.created_at, a.scheduled_at, (a.nb_places - a.nb_inscrits) AS remaining_seats
            FROM atelier a
            INNER JOIN utilisateur u ON u.id = a.formateur_id
            WHERE a.statut = :workshop_status
        SQL;
    }

    private function mapRow(array $row): ContentItem
    {
        return new ContentItem(
            (int) $row['id'],
            $row['type'],
            $row['title'],
            $row['trainer'],
            (float) $row['price'],
            $row['image_url'],
            $row['level'] ?? null,
            $row['status'] ?? null,
            $row['scheduled_at'] ?? null,
            isset($row['remaining_seats']) ? (int) $row['remaining_seats'] : null
        );
    }
}
