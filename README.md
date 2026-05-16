# HomeBase — Plateforme personnelle IA sous Laragon

Projet Laravel complet avec chatbot Mistral AI, gestion de tâches/projets, notes intelligentes,
tableau de bord et système d'administration. Hébergé en local sous Laragon/Windows.

---

## Prérequis

- Laragon Full (https://laragon.org) → PHP 8.2+, MySQL 8, Nginx/Apache
- Node.js 18+ (pour npm/Vite)
- Composer (inclus dans Laragon)
- Clé API Mistral gratuite → https://console.mistral.ai

---

## Installation en 15 étapes

```bash
# 1. Ouvrir le terminal Laragon (clic droit > Terminal)
cd C:/laragon/www

# 2. Créer le projet Laravel
composer create-project laravel/laravel homebase

cd homebase

# 3. Installer les dépendances PHP
composer require laravel/sanctum          # Auth API tokens
composer require spatie/laravel-permission # Gestion des rôles
composer require guzzlehttp/guzzle        # Client HTTP pour Mistral

# 4. Installer les dépendances JS
npm install
npm install axios @tailwindcss/forms @tailwindcss/typography

# 5. Configurer TailwindCSS
npx tailwindcss init -p

# 6. Copier le fichier .env
cp .env.example .env
php artisan key:generate

# 7. Configurer .env (voir section Configuration)

# 8. Créer la base de données dans HeidiSQL ou phpMyAdmin
#    Nom : homebase_db

# 9. Lancer les migrations
php artisan migrate

# 10. Seeder initial (admin + données de démo)
php artisan db:seed

# 11. Publier Sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 12. Publier les permissions Spatie
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

# 13. Compiler les assets
npm run dev     # développement avec hot-reload
# OU
npm run build   # production

# 14. Vider les caches
php artisan optimize:clear

# 15. Accéder au site
# http://homebase.test (si Laragon est configuré en virtual host)
# OU http://localhost/homebase/public
```

---

## Configuration Laragon (Virtual Host)

Dans Laragon → Menu → Apache/Nginx → Virtual Hosts → ajouter :

```
homebase.test → C:/laragon/www/homebase/public
```

Redémarrer Laragon. Le site est accessible sur http://homebase.test

---

## Accès par défaut (après seeder)

- Admin : admin@homebase.local / password
- User  : user@homebase.local / password

