<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;

/**
 * Formulaire de contact simple.
 */
final class ContactController extends AbstractController
{
    public function index(string $httpMethod): array
    {
        // Affiche le formulaire ou traite l'envoi.
        if ($httpMethod === 'POST') {
            $response = $this->handleSubmission();
            if ($response !== null) {
                return $response;
            }
        }

        return $this->render('contact/index.html.twig', [
            'csrf_token' => $this->csrfTokenManager->getToken('contact_form'),
        ]);
    }

    private function handleSubmission(): ?array
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->csrfTokenManager->validateToken($token, 'contact_form')) {
            $this->flashBag->add('danger', 'Session expirée. Merci de réessayer.');
            return $this->redirect('/contact');
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $goal = trim($_POST['goal'] ?? '');
        $slot = trim($_POST['slot'] ?? '');

        if ($fullName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashBag->add('danger', 'Merci de renseigner un nom et un e-mail valides.');
            return $this->redirect('/contact');
        }

        if ($goal === '') {
            $this->flashBag->add('danger', 'Merci de préciser votre besoin.');
            return $this->redirect('/contact');
        }

        $payload = [
            'submitted_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'full_name' => $fullName,
            'email' => $email,
            'goal' => $goal,
            'slot' => $slot,
        ];

        $this->storeRequest($payload);
        $this->flashBag->add('success', 'Votre demande a bien été envoyée. Nous revenons vers vous sous 24h.');

        return $this->redirect('/contact');
    }

    /**
     * @param array<string,string> $payload
     */
    private function storeRequest(array $payload): void
    {
        // Stockage simple en JSON (pas d'envoi d'email dans cette version).
        $directory = __DIR__ . '/../../var/contact';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $file = $directory . '/requests.json';
        $existing = [];
        if (is_file($file)) {
            $contents = file_get_contents($file);
            if ($contents !== false) {
                try {
                    $existing = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $existing = [];
                }
            }
        }

        $existing[] = $payload;
        file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
