<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\UserModel;
use App\Security\CsrfTokenManager;
use App\Security\UserSession;
use App\Service\FlashBag;
use Twig\Environment;

/** Profil user */
final class ProfileController extends AbstractController
{
    public function __construct(
        private UserModel $userModel,
        Environment $twig,
        UserSession $userSession,
        CsrfTokenManager $csrfTokenManager,
        FlashBag $flashBag,
        string $basePath = ''
    ) {
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function index(string $httpMethod): void
    {
        $this->requireLogin();

        if ($httpMethod === 'POST') {
            $this->updateProfile();
            return;
        }

        $this->render('profile/index.html.twig', [
            'csrf_token' => $this->csrfTokenManager->getToken('profile'),
        ]);
    }

    private function updateProfile(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'profile')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return;
        }

        // maj du profil user (infos + photo).
        $user = $this->userSession->getUser();
        $data = [
            'first_name' => trim($_POST['prenom'] ?? ''),
            'last_name' => trim($_POST['nom'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
        ];

        if ($data['first_name'] === '') {
            $data['first_name'] = $user['first_name'];
        }
        if ($data['last_name'] === '') {
            $data['last_name'] = $user['last_name'];
        }

        $photoPath = $this->handlePhotoUpload();
        $data['photo'] = $photoPath !== null ? $photoPath : ($user['photo'] ?? null);

        $this->userModel->updateProfile((int) $user['id'], $data);
        $updatedUser = $this->userModel->findById((int) $user['id']);
        if ($updatedUser) {
            $this->userSession->setUser($updatedUser);
        }

        $this->flashBag->add('success', 'Profil mis à jour.');
    }

    private function handlePhotoUpload(): ?string
    {
        if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $this->flashBag->add('danger', 'Erreur lors du téléchargement de la photo.');
            return null;
        }

        $tmpName = $_FILES['photo']['tmp_name'];
        if (!is_uploaded_file($tmpName)) {
            $this->flashBag->add('danger', 'Fichier invalide.');
            return null;
        }

        $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if ($extension && !in_array($extension, $allowed, true)) {
            $this->flashBag->add('danger', 'Format de photo non supporté.');
            return null;
        }

        $uploadDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = uniqid('avatar_', true) . ($extension ? '.' . $extension : '');
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            $this->flashBag->add('danger', 'Impossible d\'enregistrer la photo.');
            return null;
        }

        return '/uploads/' . $filename;
    }
}
