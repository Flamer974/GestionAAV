# 🏠 HomeBase - Assistant Personnel IA

[![Laravel](https://img.shields.io/badge/Laravel-13.8-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![Mistral AI](https://img.shields.io/badge/Mistral_AI-API-F58025?style=for-the-badge)](https://mistral.ai)

**HomeBase** est une plateforme personnelle d'assistance intelligente hébergée localement, combinant gestion de productivité (notes, tâches, projets) et intelligence artificielle via l'API Mistral.

---

## ✨ Fonctionnalités

### 🤖 Assistant IA Conversationnel
- Chatbot intelligent avec historique des conversations
- Modèles Mistral (Small, Medium, Large) adaptables
- Gestion des quotas : 10 req/min, 100/jour par utilisateur
- Sauvegarde automatique des sessions et tokens

### 📝 Gestion des Notes *(À venir)*
- CRUD complet + amélioration IA des textes
- Résumé automatique en 3-5 points clés
- Tags, recherche et épinglage

### ✅ Gestion des Tâches *(À venir)*
- Tableau Kanban / Liste
- Décomposition IA en sous-tâches de <30min
- Suggestions de priorisation intelligentes

### 🔐 Sécurité
- Authentification Laravel Breeze
- Rôles et permissions (Spatie)
- Protection CSRF et validation des entrées

---

## 🛠️ Installation Rapide

```bash
# 1. Cloner et entrer dans le dossier
cd C:/laragon/www
git clone https://github.com/Flamer974/GestionAAV.git homebase
cd homebase

# 2. Installer les dépendances
composer install
npm install

# 3. Configurer
cp .env.example .env
php artisan key:generate
# → Éditer .env : ajouter MISTRAL_API_KEY et config DB

# 4. Base de données
php artisan migrate --seed

# 5. Lancer
npm run dev
# → Accéder à http://homebase.test
```

### Configuration .env minimale
```env
APP_URL=http://homebase.test
DB_CONNECTION=mysql
DB_DATABASE=homebase_db
DB_USERNAME=root
DB_PASSWORD=
MISTRAL_API_KEY=votre_clé_api_mistral
```

---

## 📦 Commandes Utiles

```bash
# Développement
npm run dev              # Frontend avec hot-reload
php artisan serve        # Serveur Laravel (si pas de vhost)

# Base de données
php artisan migrate:fresh --seed  # Reset complet

# Cache
php artisan optimize:clear  # Vider tous les caches

# Tests
php artisan test         # Exécuter les tests
```

---

## 🔑 Identifiants de Test (après seed)

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | admin@homebase.local | password |
| User | user@homebase.local | password |

---

## 📁 Structure Principale

```
homebase/
├── app/Http/Controllers/   # Chatbot, Notes, Tasks...
├── app/Services/           # MistralService.php
├── resources/views/        # Vues Blade + Tailwind
├── routes/web.php          # Routes principales
└── config/mistral.php      # Configuration IA
```

---

## 🤖 Intégration Mistral AI

```php
// Dans un contrôleur
$mistral = app(MistralService::class);

// Chat conversationnel
$response = $mistral->chat("Bonjour", $history);

// Résumé de texte
$summary = $mistral->summarize($longText);

// Amélioration rédactionnelle
$enhanced = $mistral->enhanceText($draft);
```

---

## 🚧 Roadmap

- [x] Authentification + Chatbot fonctionnel
- [ ] Module Notes complet
- [ ] Module Tâches avec Kanban
- [ ] Dashboard avancé + exports
- [ ] Mode sombre + PWA

---

## 📄 Licence

MIT — Voir [LICENSE](LICENSE) pour plus de détails.

---

<div align="center">

**Développé avec ❤️ par [@Flamer974](https://github.com/Flamer974)**

⭐ *Starrez ce projet si vous l'aimez !*

</div>
