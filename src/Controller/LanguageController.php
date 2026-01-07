<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Translator;

/**
 * Changement de langue.
 */
final class LanguageController extends AbstractController
{
    public function __construct(
        private Translator $translator,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag
    ) {
        parent::__construct($userSession, $csrfTokenManager, $flashBag);
    }

    public function switch(string $locale): array
    {
        // Changement de langue, puis retour a la page precedente.
        $this->translator->setLocale($locale);
        $_SESSION['locale'] = $this->translator->getLocale();

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $parts = parse_url($referer) ?: [];
        $path = $parts['path'] ?? '/';
        $basePath = $this->detectBasePath();
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        if ($path === '') {
            $path = '/';
        }
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return $this->redirect($path);
    }

    private function detectBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName === '') {
            return '';
        }
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
            return '';
        }

        return rtrim($scriptDir, '/');
    }
}
