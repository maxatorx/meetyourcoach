<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Model\UserModel;

/**
 * Gestion de l'inscription, connexion et déconnexion.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private UserModel $userModel,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag
    ) {
        parent::__construct($userSession, $csrfTokenManager, $flashBag);
    }

    public function register(string $httpMethod): array
    {
        // Affiche le formulaire ou traite l'inscription.
        if ($httpMethod === 'POST') {
            return $this->handleRegister();
        }

        return $this->render('auth/register.html.twig', [
            'csrf_token' => $this->csrfTokenManager->getToken('register'),
        ]);
    }

    private function handleRegister(): array
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'register')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect('/register');
        }

        $firstName = trim($_POST['prenom'] ?? '');
        $lastName = trim($_POST['nom'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $passwordConfirm) {
            $this->flashBag->add('danger', 'Les mots de passe ne correspondent pas.');
            return $this->redirect('/register');
        }

        // Un nouvel utilisateur ne peut etre que apprenant ou formateur.
        $role = $_POST['role'] ?? User::ROLE_APPRENANT;
        if (!in_array($role, [User::ROLE_APPRENANT, User::ROLE_FORMATEUR], true)) {
            $role = User::ROLE_APPRENANT;
        }

        try {
            $user = new User($firstName, $lastName, $email, $password, $role);
        } catch (\Throwable $exception) {
            $this->flashBag->add('danger', $exception->getMessage());
            return $this->redirect('/register');
        }

        if ($this->userModel->findByEmail($user->getEmail())) {
            $this->flashBag->add('danger', 'Un compte existe déjà avec cet email.');
            return $this->redirect('/register');
        }

        if ($photoPath = $this->handlePhotoUpload()) {
            $user->setPhoto($photoPath);
        }

        $this->userModel->create($user);
        $this->flashBag->add('success', 'Inscription réussie. Vous pouvez vous connecter.');

        return $this->redirect('/login');
    }

    public function login(string $httpMethod): array
    {
        // Affiche le formulaire ou traite la connexion.
        if ($httpMethod === 'POST') {
            return $this->handleLogin();
        }

        return $this->render('auth/login.html.twig', [
            'csrf_token' => $this->csrfTokenManager->getToken('login'),
        ]);
    }

    private function handleLogin(): array
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'login')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect('/login');
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember_me']);

        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user->getPasswordHash())) {
            $this->flashBag->add('danger', 'Identifiants invalides.');
            return $this->redirect('/login');
        }

        // Connexion : session + option "se souvenir de moi".
        session_regenerate_id(true);
        $this->userSession->setUser($user);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $this->userModel->updateRememberToken((int) $user->getId(), $token);
            setcookie('myc_remember', $token, [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            $this->userModel->updateRememberToken((int) $user->getId(), null);
            setcookie('myc_remember', '', time() - 3600, '/');
        }

        $this->flashBag->add('success', 'Connexion réussie.');
        return $this->redirect('/');
    }

    public function logout(): array
    {
        // Déconnexion via POST.
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'logout')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            return $this->redirect('/');
        }

        $this->userSession->logout();
        setcookie('myc_remember', '', time() - 3600, '/');
        $this->flashBag->add('success', 'Vous êtes déconnecté.');

        return $this->redirect('/');
    }

    private function handlePhotoUpload(): ?string
    {
        if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $this->flashBag->add('danger', 'Erreur lors de l\'upload de la photo.');
            return null;
        }

        $tmpName = $_FILES['photo']['tmp_name'];
        if (!is_uploaded_file($tmpName)) {
            $this->flashBag->add('danger', 'Photo invalide.');
            return null;
        }

        $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if ($extension !== '' && !in_array($extension, $allowed, true)) {
            $this->flashBag->add('danger', 'Format de photo non supporté.');
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = uniqid('pp_', true) . ($extension ? '.' . $extension : '');
        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            $this->flashBag->add('danger', 'Impossible de sauvegarder la photo.');
            return null;
        }

        return '/uploads/' . $filename;
    }
}
