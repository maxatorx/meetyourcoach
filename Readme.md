# MeetYourCoach

MeetYourCoach est une plateforme légère destinée aux formateurs indépendants pour gérer un catalogue de cours courts et d'ateliers. Le socle proposé privilégie la clarté : une architecture MVC minimale, Twig pour les vues et Bootstrap 5 pour l'interface.

## Fonctionnalités clés
- Authentification avec inscription, connexion, déconnexion et option « se souvenir de moi »
- Gestion des rôles (apprenant, formateur, admin) avec contrôles d'accès simples
- Catalogue public (cours et ateliers), filtres, recherche et fiches détaillées
- Inscriptions, avis et limites de places pour les ateliers
- Espaces privés pour apprenants, formateurs et administrateurs

## Prérequis
- PHP 8.1+
- Composer
- MySQL (base `meetyourcoach`)
- Serveur web (Apache + mod_rewrite via XAMPP/MAMP ou équivalent)

## Installation
1. Cloner le dépôt dans votre environnement local :
   ```bash
   git clone <repo> meetyourcoach
   cd meetyourcoach
   ```
2. Installer les dépendances :
   ```bash
   composer install
   ```
3. Créer la base de données MySQL `meetyourcoach` puis exécuter le script `migrations/01_create_tables.sql`.
4. Adapter les identifiants de connexion dans `config/database.php` (variables d'environnement `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_PORT`).
5. Configurer votre hôte virtuel pour pointer vers `public/` et activer `mod_rewrite`. Le fichier `public/.htaccess` route toutes les requêtes vers `public/index.php`.
6. Démarrer Apache (XAMPP, MAMP ou autre) et ouvrir `http://localhost` (ou `http://localhost/public`) pour accéder à MeetYourCoach.

## Déploiement Plesk
- Transférez intégralement ce dossier dans `/httpdocs/meetyourcoach/`.
- L'entrée principale (`/httpdocs/meetyourcoach/index.php`) charge automatiquement `bootstrap.php`, donc même si le document root reste `httpdocs`, l'URL `https://domaine/meetyourcoach/` fonctionnera (pas besoin de `.htaccess`). Si vous préférez, vous pouvez tout de même pointer le document root vers `/httpdocs/meetyourcoach/public` : un `index.php` y est conservé pour compatibilité locale.
- Exécutez `composer install` directement dans `/httpdocs/meetyourcoach` afin de générer `vendor/`.
- Le routeur et les helpers `path()` / `asset()` détectent le préfixe (`/meetyourcoach`) et génèrent des liens corrects automatiquement.

## Structure
```
public/          Front controller, assets et .htaccess
config/          Connexion PDO (singleton)
src/             Contrôleurs, entités, modèles, services, sécurité
templates/       Vues Twig (layouts, partiels, pages)
migrations/      Scripts SQL
var/             Cache et logs applicatifs
```

Ce socle reste volontairement simple. Ajoutez vos propres contrôleurs, validations ou intégrations tiers selon vos besoins.
