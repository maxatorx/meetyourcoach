<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\CsrfTokenManager;
use App\Security\UserSession;
use App\Service\FlashBag;

/**
 * Base commune des controllers (render/redirect/roles).
 */
abstract class AbstractController
{
    public function __construct(
        protected UserSession $userSession,
        protected CsrfTokenManager $csrfTokenManager,
        protected FlashBag $flashBag
    ) {
    }

    protected function render(string $template, array $context = [], int $status = 200): array
    {
        return [
            'template' => $template,
            'context' => $context,
            'status' => $status,
        ];
    }

    protected function redirect(string $path, int $status = 302): array
    {
        return [
            'redirect' => $path,
            'status' => $status,
        ];
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
