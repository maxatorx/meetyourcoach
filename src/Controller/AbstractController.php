<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\CsrfTokenManager;
use App\Security\UserSession;
use App\Service\FlashBag;
use Twig\Environment;

/**
 * Base commune des controllers (render/redirect/roles).
 */
abstract class AbstractController
{
    public function __construct(
        protected Environment $twig,
        protected UserSession $userSession,
        protected CsrfTokenManager $csrfTokenManager,
        protected FlashBag $flashBag,
        protected string $basePath = ''
    ) {
    }

    protected function render(string $template, array $context = [], int $status = 200): void
    {
        http_response_code($status);
        $flashes = $this->flashBag->all();
        echo $this->twig->render($template, array_merge($context, [
            'current_user' => $this->userSession->getUser(),
            'is_logged_in' => $this->userSession->isLoggedIn(),
            'csrf_logout' => $this->csrfTokenManager->getToken('logout'),
            'flashes' => $flashes,
        ]));
    }

    protected function redirect(string $path, int $status = 302): void
    {
        $url = $path === '' ? '/' : $path;
        if ($url[0] !== '/') {
            $url = '/' . $url;
        }
        if ($this->basePath !== '') {
            $url = rtrim($this->basePath, '/') . $url;
        }
        header('Location: ' . $url);
        http_response_code($status);
    }

    protected function requireLogin(): void
    {
        // Espace protege : login obligatoire.
        if (!$this->userSession->isLoggedIn()) {
            throw new \RuntimeException('AUTH_REQUIRED');
        }
    }

    protected function requireRole(string $role): void
    {
        // Controle des droits par roles (apprenant < formateur < admin).
        $this->requireLogin();
        $rolesHierarchy = [
            'apprenant' => 1,
            'formateur' => 2,
            'admin' => 3,
        ];

        $user = $this->userSession->getUser();
        $current = $rolesHierarchy[$user['role'] ?? 'apprenant'] ?? 0;
        $required = $rolesHierarchy[$role] ?? 0;
        if ($current < $required) {
            throw new \RuntimeException('ACCESS_DENIED');
        }
    }
}
