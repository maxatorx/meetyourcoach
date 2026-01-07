<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Review;
use App\Model\CourseModel;
use App\Model\InscriptionModel;
use App\Model\ReviewModel;
use App\Model\UserModel;
use App\Model\WorkshopModel;

/**
 * Fiche d'un cours ou d'un atelier (inscription + avis).
 */
final class ContentController extends AbstractController
{
    public function __construct(
        private CourseModel $courseModel,
        private WorkshopModel $workshopModel,
        private InscriptionModel $inscriptionModel,
        private ReviewModel $reviewModel,
        private UserModel $userModel,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag
    ) {
        parent::__construct($userSession, $csrfTokenManager, $flashBag);
    }

    public function show(string $type, int $id, string $httpMethod): array
    {
        // Page detail + actions associees.
        if ($httpMethod === 'POST') {
            // Actions sur la fiche (inscription / avis).
            $action = $_POST['action'] ?? '';
            if ($action === 'register') {
                return $this->register($type, $id);
            }
            if ($action === 'unregister') {
                return $this->unregister($type, $id);
            }
            if ($action === 'review') {
                return $this->createReview($type, $id);
            }
            if ($action === 'delete_review') {
                return $this->deleteReview($type, $id);
            }
            return $this->redirect("/$type/$id");
        }

        $content = $this->loadContent($type, $id);
        if ($content === null) {
            return $this->render('content/show.html.twig', [
                'content' => null,
                'type' => $type,
            ], 404);
        }

        // Bloque l'accès public tant que l'admin n'a pas validé le contenu.
        $isPublished = $type === 'cours'
            ? ($content->getStatus() === \App\Entity\Course::STATUT_PUBLIE)
            : ($content->getStatus() === \App\Entity\Workshop::STATUT_VALIDE);
        if (!$isPublished) {
            $user = $this->userSession->getUser();
            $isOwner = $user && (int) $user['id'] === $content->getTrainerId();
            $isAdmin = $user && ($user['role'] ?? null) === 'admin';
            if (!$isOwner && !$isAdmin) {
                $this->flashBag->add('danger', 'Ce contenu est en attente de validation.');
                return $this->redirect('/');
            }
        }

        $isRegistered = false;
        if ($this->userSession->isLoggedIn()) {
            $user = $this->userSession->getUser();
            $isRegistered = $this->inscriptionModel->isRegistered((int) $user['id'], $type, $id);
        }

        $reviews = $this->reviewModel->findByContent($type, $id);
        $trainer = $this->userModel->findById($content->getTrainerId());

        return $this->render('content/show.html.twig', [
            'content' => $content,
            'type' => $type,
            'reviews' => $reviews,
            'is_registered' => $isRegistered,
            'csrf_token' => $this->csrfTokenManager->getToken('content_' . $id),
            'trainer' => $trainer,
        ]);
    }

    private function register(string $type, int $id): array
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect("/$type/$id");
        }

        $content = $this->loadContent($type, $id);
        if ($content === null) {
            $this->flashBag->add('danger', 'Contenu introuvable.');
            return $this->redirect('/');
        }
        $isPublished = $type === 'cours'
            ? ($content->getStatus() === \App\Entity\Course::STATUT_PUBLIE)
            : ($content->getStatus() === \App\Entity\Workshop::STATUT_VALIDE);
        if (!$isPublished) {
            $this->flashBag->add('danger', 'Ce contenu n\'est pas disponible.');
            return $this->redirect("/$type/$id");
        }

        $user = $this->userSession->getUser();
        $userId = (int) $user['id'];
        if ($this->inscriptionModel->isRegistered($userId, $type, $id)) {
            $this->flashBag->add('info', 'Vous êtes déjà inscrit.');
            return $this->redirect("/$type/$id");
        }

        if ($type === 'atelier' && method_exists($content, 'getNbPlaces')) {
            if ($content->getNbInscrits() >= $content->getNbPlaces()) {
                $this->flashBag->add('danger', 'Aucune place disponible.');
                return $this->redirect("/$type/$id");
            }
            $this->workshopModel->incrementRegistrations($id);
        }

        $inscription = new Inscription($userId, $id, $type);
        $this->inscriptionModel->register($inscription);
        $this->flashBag->add('success', 'Inscription enregistrée.');

        return $this->redirect("/$type/$id");
    }

    private function unregister(string $type, int $id): array
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect("/$type/$id");
        }

        $userId = (int) $this->userSession->getUser()['id'];
        $this->inscriptionModel->unregister($userId, $type, $id);
        if ($type === 'atelier') {
            $this->workshopModel->decrementRegistrations($id);
        }

        $this->flashBag->add('info', 'Inscription annulée.');
        return $this->redirect("/$type/$id");
    }

    private function createReview(string $type, int $id): array
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect("/$type/$id");
        }

        $userId = (int) $this->userSession->getUser()['id'];
        if (!$this->inscriptionModel->isRegistered($userId, $type, $id)) {
            $this->flashBag->add('danger', 'Vous devez être inscrit pour laisser un avis.');
            return $this->redirect("/$type/$id");
        }

        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        try {
            $review = new Review($userId, $id, $type, $rating, $comment);
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
            return $this->redirect("/$type/$id");
        }

        $this->reviewModel->create($review);
        $this->flashBag->add('success', 'Merci pour votre avis.');

        return $this->redirect("/$type/$id");
    }

    private function loadContent(string $type, int $id): object|null
    {
        return $type === 'cours'
            ? $this->courseModel->findById($id)
            : $this->workshopModel->findById($id);
    }

    private function deleteReview(string $type, int $id): array
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect("/$type/$id");
        }

        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId <= 0) {
            return $this->redirect("/$type/$id");
        }

        $userId = (int) $this->userSession->getUser()['id'];
        $this->reviewModel->delete($reviewId, $userId);
        $this->flashBag->add('info', 'Avis supprimé.');

        return $this->redirect("/$type/$id");
    }
}
