# Sen-Billets - Plateforme de billetterie moderne

Une plateforme de billetterie complète développée avec Symfony 7.2, Bootstrap 5.3 et Express.js.

## 🚀 Fonctionnalités

- **Framework Backend** : Symfony 7.2
- **Frontend** : Bootstrap 5.3 avec Asset Mapper
- **Base de données** : MySQL 8.0 avec Doctrine ORM
- **Serveur de développement** : Express.js
- **Templates** : Twig avec design responsive
- **Gestion des assets** : Asset Mapper (sans Webpack)

## 📋 Prérequis

- Node.js (v16+)
- PHP 8.2+
- MySQL 8.0+
- Composer
- Git
- VSCode (recommandé)

## 🛠 Installation

1. **Cloner le projet**
```bash
git clone <repository-url>
cd sen-billets
```

2. **Installer les dépendances Node.js**
```bash
npm install
```

3. **Installer les dépendances PHP (si vous avez PHP installé)**
```bash
composer install
```

4. **Configurer la base de données**
   - Créez une base de données MySQL nommée `sen_billets`
   - Modifiez le fichier `.env.local` avec vos informations de connexion

5. **Démarrer le serveur de développement**
```bash
npm run dev
```

6. **Accès à l'application**
   - Frontend: http://localhost:8000
   - API: http://localhost:8000/api

## 👥 Comptes de démonstration

- **Organisateur** : organizer@test.com / password
- **Client** : client@test.com / password

## 📁 Structure du projet

```
sen-billets/
├── assets/                  # Assets frontend
│   ├── app.js              # Point d'entrée JavaScript
│   ├── controllers/        # Contrôleurs Stimulus
│   ├── js/                 # Scripts JavaScript
│   └── styles/             # Styles CSS
├── config/                  # Configuration Symfony
├── public/                  # Point d'entrée web
│   ├── index.html          # Page d'accueil
│   ├── events.html         # Page des événements
│   ├── about.html          # Page à propos
│   ├── login.html          # Page de connexion
│   └── register.html       # Page d'inscription
├── src/                     # Code source PHP (Symfony)
│   ├── Controller/         # Contrôleurs Symfony
│   ├── Entity/             # Entités Doctrine
│   ├── Repository/         # Repositories
│   └── Service/            # Services
├── templates/               # Templates Twig
├── server.js                # Serveur Express.js
└── package.json             # Configuration npm
```

## 📚 Documentation

- [Symfony 7.2](https://symfony.com/doc/7.2/index.html)
- [Bootstrap 5.3](https://getbootstrap.com/docs/5.3/)
- [Express.js](https://expressjs.com/fr/)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)

## 🆘 Dépannage

### Erreur de connexion à la base de données

Vérifiez que MySQL est démarré et que les paramètres de connexion sont corrects dans `.env.local`.

### Erreur de serveur Express

Assurez-vous que les ports 8000 ne sont pas déjà utilisés par d'autres applications.

### Logs

```bash
# Voir les logs du serveur Express
npm run dev
```