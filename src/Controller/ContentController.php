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
use Twig\Environment;

/** Fiche cours ou atelier avec inscription + avis. */
final class ContentController extends AbstractController
{
    public function __construct(
        private CourseModel $courseModel,
        private WorkshopModel $workshopModel,
        private InscriptionModel $inscriptionModel,
        private ReviewModel $reviewModel,
        private UserModel $userModel,
        Environment $twig,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag,
        string $basePath = ''
    ) {
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function show(string $type, int $id, string $httpMethod): void
    {
        // Page detail
        if ($httpMethod === 'POST') {
            // Actions sur inscription et avis.
            $action = $_POST['action'] ?? '';
            if ($action === 'register') {
                $this->register($type, $id);
                return;
            }
            if ($action === 'unregister') {
                $this->unregister($type, $id);
                return;
            }
            if ($action === 'review') {
                $this->createReview($type, $id);
                return;
            }
            if ($action === 'delete_review') {
                $this->deleteReview($type, $id);
                return;
            }
            $this->redirect("/$type/$id");
            return;
        }

        $content = $this->loadContent($type, $id);
        if ($content === null) {
            $this->render('content/show.html.twig', [
                'content' => null,
                'type' => $type,
            ], 404);
            return;
        }

        // accès public bloqué tant que l'admin n'a pas validé le contenu.
        $isPublished = $type === 'cours'
            ? ($content->getStatus() === \App\Entity\Course::STATUT_PUBLIE)
            : ($content->getStatus() === \App\Entity\Workshop::STATUT_VALIDE);
        if (!$isPublished) {
            $user = $this->userSession->getUser();
            $isOwner = $user && (int) $user['id'] === $content->getTrainerId();
            $isAdmin = $user && ($user['role'] ?? null) === 'admin';
            if (!$isOwner && !$isAdmin) {
                $this->flashBag->add('danger', 'Ce contenu est en attente de validation.');
                $this->redirect('/');
                return;
            }
        }

        $isRegistered = false;
        if ($this->userSession->isLoggedIn()) {
            $user = $this->userSession->getUser();
            $isRegistered = $this->inscriptionModel->isRegistered((int) $user['id'], $type, $id);
        }

        $reviews = $this->reviewModel->findByContent($type, $id);
        $trainer = $this->userModel->findById($content->getTrainerId());

        $this->render('content/show.html.twig', [
            'content' => $content,
            'type' => $type,
            'reviews' => $reviews,
            'is_registered' => $isRegistered,
            'csrf_token' => $this->csrfTokenManager->getToken('content_' . $id),
            'trainer' => $trainer,
        ]);
    }

    private function register(string $type, int $id): void
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect("/$type/$id");
            return;
        }

        $content = $this->loadContent($type, $id);
        if ($content === null) {
            $this->flashBag->add('danger', 'Contenu introuvable.');
            $this->redirect('/');
            return;
        }
        $isPublished = $type === 'cours'
            ? ($content->getStatus() === \App\Entity\Course::STATUT_PUBLIE)
            : ($content->getStatus() === \App\Entity\Workshop::STATUT_VALIDE);
        if (!$isPublished) {
            $this->flashBag->add('danger', 'Ce contenu n\'est pas disponible.');
            $this->redirect("/$type/$id");
            return;
        }

        $user = $this->userSession->getUser();
        $userId = (int) $user['id'];
        if ($this->inscriptionModel->isRegistered($userId, $type, $id)) {
            $this->flashBag->add('info', 'Vous êtes déjà inscrit.');
            $this->redirect("/$type/$id");
            return;
        }

        if ($type === 'atelier' && method_exists($content, 'getNbPlaces')) {
            if ($content->getNbInscrits() >= $content->getNbPlaces()) {
                $this->flashBag->add('danger', 'Aucune place disponible.');
                $this->redirect("/$type/$id");
                return;
            }
            $this->workshopModel->incrementRegistrations($id);
        }

        $inscription = new Inscription($userId, $id, $type);
        $this->inscriptionModel->register($inscription);
        $this->flashBag->add('success', 'Inscription enregistrée.');

        $this->redirect("/$type/$id");
    }

    private function unregister(string $type, int $id): void
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect("/$type/$id");
            return;
        }

        $userId = (int) $this->userSession->getUser()['id'];
        $this->inscriptionModel->unregister($userId, $type, $id);
        if ($type === 'atelier') {
            $this->workshopModel->decrementRegistrations($id);
        }

        $this->flashBag->add('info', 'Inscription annulée.');
        $this->redirect("/$type/$id");
    }

    private function createReview(string $type, int $id): void
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect("/$type/$id");
            return;
        }

        $userId = (int) $this->userSession->getUser()['id'];
        if (!$this->inscriptionModel->isRegistered($userId, $type, $id)) {
            $this->flashBag->add('danger', 'Vous devez être inscrit pour laisser un avis.');
            $this->redirect("/$type/$id");
            return;
        }

        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        try {
            $review = new Review($userId, $id, $type, $rating, $comment);
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
            $this->redirect("/$type/$id");
            return;
        }

        $this->reviewModel->create($review);
        $this->flashBag->add('success', 'Merci pour votre avis.');

        $this->redirect("/$type/$id");
    }

    private function loadContent(string $type, int $id): object|null
    {
        return $type === 'cours'
            ? $this->courseModel->findById($id)
            : $this->workshopModel->findById($id);
    }

    private function deleteReview(string $type, int $id): void
    {
        $this->requireLogin();
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'content_' . $id)) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect("/$type/$id");
            return;
        }

        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId <= 0) {
            $this->redirect("/$type/$id");
            return;
        }

        $userId = (int) $this->userSession->getUser()['id'];
        $this->reviewModel->delete($reviewId, $userId);
        $this->flashBag->add('info', 'Avis supprimé.');

        $this->redirect("/$type/$id");
    }
}
