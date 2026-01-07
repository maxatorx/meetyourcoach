<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\CourseModel;
use App\Model\InscriptionModel;
use App\Model\ReviewModel;
use App\Model\UserModel;
use App\Model\WorkshopModel;
use App\Security\UserSession;
use App\Security\CsrfTokenManager;
use App\Service\FlashBag;
use Twig\Environment;

/**
 * Espace apprenant (profil + inscriptions + avis).
 */
final class LearnerController extends AbstractController
{
    public function __construct(
        private UserModel $userModel,
        private InscriptionModel $inscriptionModel,
        private ReviewModel $reviewModel,
        private CourseModel $courseModel,
        private WorkshopModel $workshopModel,
        Environment $twig,
        UserSession $userSession,
        CsrfTokenManager $csrfTokenManager,
        FlashBag $flashBag,
        string $basePath = ''
    ) {
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function dashboard(string $httpMethod): void
    {
        // Espace accessible a tout utilisateur connecte.
        $this->requireLogin();

        if ($httpMethod === 'POST') {
            $this->handlePost();
            return;
        }

        // Espace apprenant : inscriptions + avis.
        $user = $this->userSession->getUser();
        $enrolledCourses = [];
        $enrolledWorkshops = [];
        foreach ($this->inscriptionModel->findByUser((int) $user['id']) as $inscription) {
            $type = $inscription['type'];
            $contentId = (int) $inscription['contenu_id'];
            if ($type === 'cours') {
                $course = $this->courseModel->findById($contentId);
                if ($course !== null) {
                    $enrolledCourses[] = $course;
                }
                continue;
            }

            $workshop = $this->workshopModel->findById($contentId);
            if ($workshop !== null) {
                $enrolledWorkshops[] = $workshop;
            }
        }
        $reviews = $this->reviewModel->findByUser((int) $user['id']);

        $this->render('learner/dashboard.html.twig', [
            'enrolled_courses' => $enrolledCourses,
            'enrolled_workshops' => $enrolledWorkshops,
            'my_reviews' => $reviews,
            'csrf_token' => $this->csrfTokenManager->getToken('learner_profile'),
        ]);
    }

    private function handlePost(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'learner_profile')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return;
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'unregister') {
            $this->unregister();
            return;
        }

        $this->updateProfile();
    }

    private function unregister(): void
    {
        $type = $_POST['type'] ?? '';
        $contentId = (int) ($_POST['content_id'] ?? 0);
        if ($contentId <= 0 || ($type !== 'cours' && $type !== 'atelier')) {
            return;
        }

        $userId = (int) $this->userSession->getUser()['id'];
        $this->inscriptionModel->unregister($userId, $type, $contentId);
        if ($type === 'atelier') {
            $this->workshopModel->decrementRegistrations($contentId);
        }
        $this->flashBag->add('info', 'Inscription annulée.');
    }

    private function updateProfile(): void
    {
        $user = $this->userSession->getUser();
        $data = [
            'first_name' => trim($_POST['prenom'] ?? ''),
            'last_name' => trim($_POST['nom'] ?? ''),
            'photo' => trim($_POST['photo'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
        ];

        $this->userModel->updateProfile((int) $user['id'], $data);
        $updatedUser = $this->userModel->findById((int) $user['id']);
        if ($updatedUser) {
            $this->userSession->setUser($updatedUser);
        }
        $this->flashBag->add('success', 'Profil mis à jour.');
    }
}
