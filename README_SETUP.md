# Sen-Billets - Configuration sans Docker

## Prérequis

1. **PHP 8.1 ou supérieur** avec les extensions suivantes :
   - pdo_mysql
   - mbstring
   - xml
   - ctype
   - iconv
   - intl
   - zip

2. **Composer** (gestionnaire de dépendances PHP)

3. **MySQL 8.0** ou **MariaDB 10.4+**

4. **Serveur web** (Apache, Nginx, ou serveur de développement Symfony)

## Installation

### 1. Installation des dépendances PHP

```bash
composer install
```

### 2. Configuration de la base de données

Créez une base de données MySQL :

```sql
CREATE DATABASE sen_billets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sen_billets'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON sen_billets.* TO 'sen_billets'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configuration des variables d'environnement

Modifiez le fichier `.env` avec vos paramètres de base de données :

```env
DATABASE_URL="mysql://sen_billets:password@127.0.0.1:3306/sen_billets?serverVersion=8.0.32&charset=utf8mb4"
```

### 4. Génération des clés JWT

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Mettez à jour le passphrase dans `.env` :

```env
JWT_PASSPHRASE=votre-passphrase-ici
```

### 5. Création de la base de données et des tables

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 6. Chargement des données de démonstration

```bash
php bin/console doctrine:fixtures:load
```

### 7. Démarrage du serveur de développement

```bash
php bin/console server:run
# ou
symfony serve
```

L'application sera accessible sur `http://localhost:8000`

## Comptes de démonstration

- **Organisateur** : organizer@test.com / password
- **Client** : client@test.com / password

## Configuration pour la production

### Apache

Créez un VirtualHost :

```apache
<VirtualHost *:80>
    ServerName sen-billets.local
    DocumentRoot /path/to/sen-billets/public
    
    <Directory /path/to/sen-billets/public>
        AllowOverride All
        Require all granted
        
        FallbackResource /index.php
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/sen-billets_error.log
    CustomLog ${APACHE_LOG_DIR}/sen-billets_access.log combined
</VirtualHost>
```

### Nginx

Configuration Nginx :

```nginx
server {
    listen 80;
    server_name sen-billets.local;
    root /path/to/sen-billets/public;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
    
    location ~ \.php$ {
        return 404;
    }
}
```

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Créer un utilisateur
php bin/console make:user

# Créer un contrôleur
php bin/console make:controller

# Voir les routes
php bin/console debug:router
```

## Dépannage

### Erreur de permissions

```bash
chmod -R 755 var/
chmod -R 755 config/jwt/
```

### Erreur de base de données

Vérifiez que MySQL est démarré et que les paramètres de connexion sont corrects dans `.env`.

### Erreur JWT

Assurez-vous que les clés JWT sont générées et que les permissions sont correctes :

```bash
chmod 644 config/jwt/public.pem
chmod 600 config/jwt/private.pem
```