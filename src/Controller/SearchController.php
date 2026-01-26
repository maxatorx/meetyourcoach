<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\ContentModel;
use Twig\Environment;

/** Recherche de cours/ateliers. */
final class SearchController extends AbstractController
{
    public function __construct(
        private ContentModel $contentModel,
        Environment $twig,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag,
        string $basePath = ''
    ) {
        // injection dependances
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function search(): void
    {
        // filtres depuis query string. parametre absent = null
        $filters = [
            'titre' => isset($_GET['titre']) ? trim((string) $_GET['titre']) : null,
            'type' => isset($_GET['type']) ? trim((string) $_GET['type']) : null,
            'niveau' => isset($_GET['niveau']) ? trim((string) $_GET['niveau']) : null,
            'date' => isset($_GET['date']) ? trim((string) $_GET['date']) : null,
        ];

        // Le model applique la requete en base avec ces filtres
        $results = $this->contentModel->search($filters);

        $this->render('search/index.html.twig', [
            'filters' => $filters,
            'results' => $results,
        ]);
    }
}
