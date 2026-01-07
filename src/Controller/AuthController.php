<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Model\UserModel;
use Twig\Environment;

/**
 * Gestion de l'inscription, connexion et déconnexion.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private UserModel $userModel,
        Environment $twig,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag,
        string $basePath = ''
    ) {
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function register(string $httpMethod): void
    {
        // Affiche le formulaire ou traite l'inscription.
        if ($httpMethod === 'POST') {
            $this->handleRegister();
            return;
        }

        $this->render('auth/register.html.twig', [
            'csrf_token' => $this->csrfTokenManager->getToken('register'),
        ]);
    }

    private function handleRegister(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'register')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect('/register');
            return;
        }

        $firstName = trim($_POST['prenom'] ?? '');
        $lastName = trim($_POST['nom'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $passwordConfirm) {
            $this->flashBag->add('danger', 'Les mots de passe ne correspondent pas.');
            $this->redirect('/register');
            return;
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
            $this->redirect('/register');
            return;
        }

        if ($this->userModel->findByEmail($user->getEmail())) {
            $this->flashBag->add('danger', 'Un compte existe déjà avec cet email.');
            $this->redirect('/register');
            return;
        }

        if ($photoPath = $this->handlePhotoUpload()) {
            $user->setPhoto($photoPath);
        }

        $this->userModel->create($user);
        $this->flashBag->add('success', 'Inscription réussie. Vous pouvez vous connecter.');

        $this->redirect('/login');
    }

    public function login(string $httpMethod): void
    {
        // Affiche le formulaire ou traite la connexion.
        if ($httpMethod === 'POST') {
            $this->handleLogin();
            return;
        }

        // On mémorise la page précédente pour y revenir après connexion.
        $this->storeReturnPath();

        $this->render('auth/login.html.twig', [
            'csrf_token' => $this->csrfTokenManager->getToken('login'),
        ]);
    }

    private function handleLogin(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'login')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect('/login');
            return;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember_me']);

        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user->getPasswordHash())) {
            $this->flashBag->add('danger', 'Identifiants invalides.');
            $this->redirect('/login');
            return;
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
        $this->redirect($this->getReturnPath());
    }

    public function logout(): void
    {
        // Déconnexion via POST.
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'logout')) {
            $this->flashBag->add('danger', 'Jeton CSRF invalide.');
            $this->redirect('/');
            return;
        }

        $this->userSession->logout();
        setcookie('myc_remember', '', time() - 3600, '/');
        $this->flashBag->add('success', 'Vous êtes déconnecté.');

        $this->redirect('/index.php');
    }

    private function getReturnPath(): string
    {
        // Retourne a la page precedente si possible, sinon accueil.
        $default = '/index.php';
        $stored = $_SESSION['login_redirect'] ?? null;
        if (!is_string($stored) || $stored === '') {
            return $default;
        }

        unset($_SESSION['login_redirect']);
        return $stored;
    }

    private function storeReturnPath(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') {
            return;
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return;
        }

        $host = $parts['host'] ?? '';
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '' && $currentHost !== '' && $host !== $currentHost) {
            return;
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || $path === '/') {
            return;
        }

        // Ignore les pages d'auth pour éviter une boucle.
        if (str_contains($path, '/login') || str_contains($path, '/register')) {
            return;
        }

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $_SESSION['login_redirect'] = $path;
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
