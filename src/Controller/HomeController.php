<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\ContentModel;
use Twig\Environment;

/** Page d'accueil + catalogue. */
final class HomeController extends AbstractController
{
    public function __construct(
        private ContentModel $contentModel,
        Environment $twig,
        \App\Security\UserSession $userSession,
        \App\Security\CsrfTokenManager $csrfTokenManager,
        \App\Service\FlashBag $flashBag,
        string $basePath = ''
    ) {
        parent::__construct($twig, $userSession, $csrfTokenManager, $flashBag, $basePath);
    }

    public function index(): void
    {
        // Catalogue public avec filtre par type et pagination.
        $type = $_GET['type'] ?? 'all';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $items = $this->contentModel->getPublished($type, $page);

        $this->render('home/index.html.twig', [
            'items' => $items,
            'current_type' => $type,
            'page' => $page,
        ]);
    }
}
