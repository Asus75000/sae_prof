# 🚀 Guide d'Installation - KASTA CROSSFIT

## 📋 Prérequis

- **Serveur Web** : Apache 2.4+ ou Nginx
- **PHP** : Version 7.4 ou supérieure
- **MySQL/MariaDB** : Version 5.7+ / 10.2+
- **Extensions PHP requises** :
  - `pdo_mysql` : Connexion à la base de données
  - `mbstring` : Support des chaînes multioctets
  - `mail` : Envoi d'emails (fonction `mail()`)

---

## 🛠️ Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/Asus75000/sae.git
cd sae
```

### 2. Configuration de la base de données

#### a) Créer la base de données

Créez une base de données MySQL nommée `kasta` :

```sql
CREATE DATABASE kasta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### b) Importer le schéma existant

Si vous avez un fichier SQL de base :

```bash
mysql -u root -p kasta < schema.sql
```

#### c) Appliquer la mise à jour pour le motif de refus

**IMPORTANT** : Exécutez le script SQL fourni pour ajouter la colonne `motif_refus` :

```bash
mysql -u root -p kasta < update_db.sql
```

Ou manuellement :

```sql
USE kasta;
ALTER TABLE membre ADD COLUMN motif_refus TEXT NULL AFTER date_statut;
```

### 3. Configuration de l'application

Éditez le fichier `config.php` pour configurer la connexion à la base de données :

```php
// Configuration BDD
define('DB_HOST', 'localhost');      // Hôte MySQL
define('DB_NAME', 'kasta');          // Nom de la base
define('DB_USER', 'root');           // Utilisateur MySQL
define('DB_PASS', '');               // Mot de passe MySQL

// URL du site (pour les emails)
define('SITE_URL', 'http://votre-domaine.fr');
```

**⚠️ Recommandations de sécurité** :
- En production, utilisez un utilisateur MySQL dédié (pas `root`)
- Configurez un mot de passe fort pour la base de données
- Utilisez HTTPS (modifiez `SITE_URL` en `https://`)

### 4. Configuration des permissions

Assurez-vous que le serveur web a les droits d'écriture sur le dossier de sessions :

```bash
# Pour Apache sous Linux
sudo chown -R www-data:www-data /path/to/kasta
sudo chmod -R 755 /path/to/kasta
```

### 5. Configuration du serveur web

#### Apache

Créez un VirtualHost dans `/etc/apache2/sites-available/kasta.conf` :

```apache
<VirtualHost *:80>
    ServerName kasta-crossfit.local
    DocumentRoot /var/www/kasta

    <Directory /var/www/kasta>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/kasta_error.log
    CustomLog ${APACHE_LOG_DIR}/kasta_access.log combined
</VirtualHost>
```

Activez le site :

```bash
sudo a2ensite kasta
sudo systemctl reload apache2
```

#### Nginx

Exemple de configuration dans `/etc/nginx/sites-available/kasta` :

```nginx
server {
    listen 80;
    server_name kasta-crossfit.local;
    root /var/www/kasta;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Configuration de l'envoi d'emails

#### Méthode 1 : Utiliser `mail()` de PHP (par défaut)

Installez et configurez Postfix ou Sendmail :

```bash
# Ubuntu/Debian
sudo apt-get install postfix

# Configurer Postfix en "Internet Site"
```

#### Méthode 2 : Utiliser un serveur SMTP externe (recommandé)

Pour utiliser Gmail, SendGrid, ou un autre fournisseur, vous devrez modifier la fonction `sendEmail()` dans `functions.php` pour utiliser une bibliothèque comme PHPMailer.

Exemple avec PHPMailer :

```bash
composer require phpmailer/phpmailer
```

Puis modifiez `sendEmail()` pour utiliser SMTP.

---

## ✨ Nouvelles Fonctionnalités Implémentées

### 🔒 Sécurité Renforcée

#### 1. Protection Anti-DDoS / Brute Force (Rate Limiting)
- **Inscription** : 3 tentatives max en 10 minutes
- **Login** : 5 tentatives max en 5 minutes
- Messages d'erreur avec temps d'attente restant

#### 2. Validation des Données Améliorée
- **Prénom/Nom** : 2-50 caractères, lettres uniquement, pas d'injection HTML
- **Email** : Validation stricte, max 100 caractères
- **Mot de passe** : 8-255 caractères, 1 majuscule, 1 chiffre obligatoires
- **Téléphone** : Format français validé (0XXXXXXXXX)
- **Tailles** : Valeurs prédéfinies uniquement (XS, S, M, L, XL, XXL)

#### 3. Sécurité Existante (déjà présente)
- ✅ Protection CSRF sur tous les formulaires
- ✅ Protection XSS (sanitization systématique)
- ✅ Protection SQL Injection (requêtes préparées PDO)
- ✅ Hashage sécurisé des mots de passe (bcrypt)

### 🎯 Gestion des Adhésions

#### Lors de l'inscription
- Checkbox pour devenir adhérent dès l'inscription
- **Popup modal** si l'utilisateur ne coche pas "adhérent" :
  - Message d'avertissement : pas d'assurance, pas d'accès aux événements privés
  - 2 boutons : "Devenir adhérent" ou "Continuer sans souscrire"

#### Sur le dashboard membre
- Encadré visible pour les non-adhérents
- Bouton "Devenir adhérent de l'association"
- Modal de confirmation avec liste des avantages
- Adhésion définitive (à vie)

### 📧 Système d'Emails Automatiques

#### Email de validation de compte
- Envoyé automatiquement quand l'admin valide un compte
- Template HTML professionnel avec bouton de connexion
- Lien direct vers la page de login

#### Email de refus avec motif
- **Modal obligatoire** pour saisir le motif de refus (minimum 10 caractères)
- Email automatique avec le motif inclus
- Template HTML clair et professionnel
- Motif stocké en base de données (`motif_refus`)

### 👥 Rôle Gestionnaire

#### Attribution du statut
- Interface admin pour nommer/retirer des gestionnaires
- Colonne "Gestionnaire" dans la table des membres
- Bouton "Nommer gestionnaire" / "Retirer gestionnaire"
- Confirmation avant modification

#### Permissions des gestionnaires
- Accès à la gestion des événements (sportifs et associatifs)
- Création, modification et suppression d'événements
- Gestion des créneaux horaires
- Gestion des catégories d'événements
- **SANS accès** à la gestion des membres (réservé aux admins)
- Menu "Gestion Événements" dans la barre de navigation

---

## 🧪 Tests et Validation

### Tester l'inscription

1. Allez sur `http://votre-domaine/auth.php?register=1`
2. Testez le rate limiting (3 inscriptions rapides)
3. Testez le popup adhérent (ne cochez pas la case)
4. Vérifiez la validation des champs :
   - Prénom avec chiffres (doit échouer)
   - Email invalide (doit échouer)
   - Mot de passe < 8 caractères (doit échouer)
   - Téléphone invalide (doit échouer)

### Tester le login

1. Tentez 6 connexions avec un mauvais mot de passe
2. Vérifiez le message de rate limiting après 5 tentatives
3. Connectez-vous avec un compte validé

### Tester les emails (Admin)

1. Connectez-vous en tant qu'admin
2. Allez dans "Membres" → Filtrer "En attente"
3. **Valider un compte** :
   - Cliquez sur "Valider"
   - Confirmez
   - Vérifiez l'email reçu par le membre
4. **Refuser un compte** :
   - Cliquez sur "Refuser"
   - Saisissez un motif (min 10 caractères)
   - Confirmez
   - Vérifiez l'email reçu avec le motif

### Tester les gestionnaires

1. En tant qu'admin, nommez un membre comme gestionnaire
2. Connectez-vous avec ce compte gestionnaire
3. Vérifiez l'accès à "Gestion Événements"
4. Créez un événement sportif
5. Vérifiez que vous n'avez PAS accès à "Administration"

### Tester l'adhésion

1. Connectez-vous avec un compte non-adhérent
2. Vérifiez l'encadré jaune sur le dashboard
3. Cliquez sur "Devenir adhérent"
4. Confirmez dans le modal
5. Vérifiez que le statut passe à "Adhérent"
6. Vérifiez qu'on ne peut plus revenir en arrière (adhésion à vie)

---

## 📁 Structure de la Base de Données

### Table `membre` (mise à jour)

Nouvelles colonnes ajoutées :
- `motif_refus` (TEXT, NULL) : Motif du refus d'inscription

Colonnes existantes utilisées :
- `adherent` (TINYINT) : 0 = Non-adhérent, 1 = Adhérent
- `gestionnaire_o_n_` (TINYINT) : 0 = Membre, 1 = Gestionnaire

---

## 🔐 Comptes de Test Recommandés

Créez manuellement ces comptes pour tester :

### Administrateur
```sql
INSERT INTO membre (prenom, nom, mail, mdp, statut, gestionnaire_o_n_, adherent)
VALUES ('Admin', 'Test', 'admin@test.fr', '$2y$10$...', 'VALIDE', 1, 1);
```

### Gestionnaire
```sql
INSERT INTO membre (prenom, nom, mail, mdp, statut, gestionnaire_o_n_, adherent)
VALUES ('Gestionnaire', 'Test', 'gestion@test.fr', '$2y$10$...', 'VALIDE', 1, 0);
```

### Membre Standard
```sql
INSERT INTO membre (prenom, nom, mail, mdp, statut, gestionnaire_o_n_, adherent)
VALUES ('Membre', 'Test', 'membre@test.fr', '$2y$10$...', 'VALIDE', 0, 0);
```

> **Note** : Utilisez `password_hash('VotreMotDePasse1', PASSWORD_DEFAULT)` en PHP pour générer le hash.

---

## 🐛 Dépannage

### Les emails ne sont pas envoyés

1. Vérifiez les logs d'erreur PHP : `tail -f /var/log/apache2/error.log`
2. Testez la fonction `mail()` :
   ```php
   mail('test@example.com', 'Test', 'Message de test');
   ```
3. Vérifiez la configuration Postfix : `sudo postfix status`

### Erreur de connexion à la base de données

1. Vérifiez les credentials dans `config.php`
2. Testez la connexion MySQL :
   ```bash
   mysql -u root -p -e "SHOW DATABASES;"
   ```

### Rate limiting trop strict

Modifiez les paramètres dans `functions.php` :

```php
// Login : 5 tentatives en 5 minutes (300 secondes)
checkRateLimit('login', 5, 300);

// Register : 3 tentatives en 10 minutes (600 secondes)
checkRateLimit('register', 3, 600);
```

---

## 📞 Support

Pour toute question ou problème :
- **GitHub Issues** : https://github.com/Asus75000/sae/issues
- **Email** : contact@kasta-crossfit.fr

---

## 📄 Licence

Ce projet est développé pour l'association KASTA CROSSFIT.

---

**Dernière mise à jour** : 31 octobre 2025
