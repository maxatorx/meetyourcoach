<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\ContentModel;

/**
 * Recherche de cours/ateliers.
 */
final class SearchController extends AbstractController
{
    public function __construct(
        private ContentModel $contentModel,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag
    ) {
        parent::__construct($userSession, $csrfTokenManager, $flashBag);
    }

    public function search(): array
    {
        // Recherche par titre, type, niveau, date.
        $filters = [
            'titre' => $_GET['titre'] ?? null,
            'type' => $_GET['type'] ?? null,
            'niveau' => $_GET['niveau'] ?? null,
            'date' => $_GET['date'] ?? null,
        ];

        $results = $this->contentModel->search($filters);

        return $this->render('search/index.html.twig', [
            'filters' => $filters,
            'results' => $results,
        ]);
    }
}
