# 📝 Changelog - KASTA CROSSFIT

Tous les changements notables de ce projet sont documentés dans ce fichier.

---

## [2.0.0] - 2025-10-31

### ✨ Nouvelles Fonctionnalités

#### 🔒 Sécurité

- **Rate Limiting (Anti-DDoS/Brute Force)**
  - Protection login : 5 tentatives max en 5 minutes
  - Protection inscription : 3 tentatives max en 10 minutes
  - Messages d'erreur avec temps d'attente restant
  - Réinitialisation automatique après connexion réussie
  - Fonctions : `checkRateLimit()`, `recordAttempt()`, `resetRateLimit()`

- **Validation des Données Renforcée**
  - Prénom/Nom : 2-50 caractères, lettres/espaces/tirets uniquement
  - Email : validation stricte, max 100 caractères
  - Mot de passe : 8-255 caractères, 1 majuscule + 1 chiffre obligatoires
  - Téléphone : format français strict (0XXXXXXXXX)
  - Tailles vêtements : validation des valeurs prédéfinies
  - Protection contre injection de code dans les noms
  - Validation côté client (HTML5) et serveur (PHP)

#### 🎯 Gestion des Adhésions

- **Inscription avec Choix Adhérent**
  - Checkbox "Je souhaite devenir adhérent" sur le formulaire d'inscription
  - Modal d'avertissement si non-adhérent :
    - Message : pas d'assurance, pas d'événements privés
    - Bouton "Je souhaite devenir adhérent"
    - Bouton "Continuer sans souscrire"
  - Stockage du statut adhérent dès l'inscription

- **Dashboard Membre - Devenir Adhérent**
  - Encadré jaune visible uniquement pour les non-adhérents
  - Bouton "Devenir adhérent de l'association"
  - Modal de confirmation avec :
    - Liste des avantages (assurance, événements privés, tarifs)
    - Note : adhésion définitive à vie
    - Confirmation CSRF sécurisée
  - Badge "Adhérent" ou "Non-adhérent" dans le dashboard
  - Modification du formulaire de profil existant (checkbox adhérent)

#### 📧 Système d'Emails Automatiques

- **Fonction d'Envoi d'Email Sécurisée**
  - Nouvelle fonction `sendEmail($to, $subject, $message, $is_html)`
  - Validation de l'email destinataire
  - Headers sécurisés (From, Reply-To, MIME)
  - Support HTML et texte brut
  - Logs d'envoi pour debug
  - Gestion des erreurs

- **Email de Validation de Compte**
  - Template HTML professionnel
  - Header bleu KASTA CROSSFIT
  - Message de bienvenue personnalisé
  - Liste des fonctionnalités disponibles
  - Bouton d'action "Se connecter" (lien direct)
  - Footer avec contact et copyright
  - Envoi automatique lors de la validation admin
  - Fonction : `getEmailTemplateValidation($prenom, $nom)`

- **Email de Refus avec Motif**
  - Template HTML professionnel
  - Header rouge (refus)
  - Message explicatif
  - Encadré jaune avec le motif du refus
  - Contact pour plus d'informations
  - Envoi automatique lors du refus admin
  - Fonction : `getEmailTemplateRefus($prenom, $nom, $motif)`

#### 👥 Système de Gestionnaires

- **Nouveaux Rôles et Permissions**
  - Ajout du rôle "Gestionnaire" (utilise le champ `gestionnaire_o_n_`)
  - Nouvelles fonctions :
    - `isGestionnaire()` : Vérifie si l'utilisateur est gestionnaire
    - `isGestionnaireOrAdmin()` : Vérifie si gestionnaire OU admin
    - `requireGestionnaireOrAdmin()` : Protège les pages événements

- **Interface d'Attribution**
  - Colonne "Gestionnaire" dans `admin_membres.php`
  - Badge "Gestionnaire" (bleu) ou "Non" (gris)
  - Bouton "Nommer gestionnaire" / "Retirer gestionnaire"
  - Accessible uniquement pour les membres VALIDÉS
  - Confirmation avant modification
  - Messages flash de succès
  - Handler GET : `?toggle_gestionnaire=ID&csrf=TOKEN`

- **Accès Gestionnaires**
  - Accès à `admin_events.php` (création/édition/suppression événements sport et asso)
  - Accès à `admin_creneaux.php` (gestion des créneaux horaires)
  - Accès à `admin_categories.php` (gestion des catégories)
  - PAS d'accès à `admin.php` (dashboard réservé admins)
  - PAS d'accès à `admin_membres.php` (validation/refus réservé admins)
  - Menu "Gestion Événements" dans la barre de navigation (header.php)

#### 🗄️ Base de Données

- **Nouvelle Colonne**
  - Table `membre` : ajout de `motif_refus` (TEXT, NULL)
  - Stockage du motif lors du refus d'inscription
  - Script SQL fourni : `update_db.sql`

- **Colonnes Existantes Utilisées**
  - `adherent` : 0 = Non-adhérent, 1 = Adhérent (à vie)
  - `gestionnaire_o_n_` : 0 = Membre, 1 = Gestionnaire

### 🔧 Modifications

#### Interface Admin

- **admin_membres.php**
  - Ajout colonne "Gestionnaire" dans le tableau
  - Modal de refus avec champ texte obligatoire (min 10 caractères)
  - Validation JavaScript du motif avant soumission
  - Passage de GET à POST pour le refus (plus sécurisé)
  - Confirmation pour la validation (avec mention de l'email)
  - Messages flash améliorés avec nom du membre
  - Handler POST : `refuser_membre` avec `motif_refus`
  - Handler GET : `toggle_gestionnaire` pour le rôle gestionnaire

#### Formulaires

- **auth.php (Inscription)**
  - Ajout du champ `adherent` (checkbox)
  - Traitement du champ dans le POST
  - Attributs HTML5 : `minlength`, `maxlength`, `pattern`, `title`
  - Prénom : `pattern="[a-zA-ZÀ-ÿ\s\-]+"`, `maxlength="50"`
  - Nom : `pattern="[a-zA-ZÀ-ÿ\s\-]+"`, `maxlength="50"`
  - Email : `maxlength="100"`
  - Mot de passe : `maxlength="255"`, label mis à jour (exigences claires)
  - Modal adhérent en bas de page (HTML + JavaScript)
  - Gestion de la soumission avec flag `formSubmitAllowed`

- **index.php (Login)**
  - Ajout du rate limiting avant traitement
  - Affichage du temps d'attente en cas de blocage
  - Réinitialisation du compteur en cas de succès
  - Incrémentation du compteur en cas d'échec

#### Backend

- **functions.php**
  - Fonction `validateMembreData()` complètement réécrite :
    - Validation stricte prénom/nom (regex, longueur, caractères)
    - Validation email avec longueur max
    - Validation mot de passe avec longueur max
    - Validation tailles vêtements (valeurs autorisées)
    - Messages d'erreur explicites
  - Fonction `createMembre()` modifiée :
    - Ajout du paramètre `adherent` dans l'INSERT
    - Valeur par défaut : 0 (non-adhérent)
  - Nouvelles fonctions utilitaires :
    - `sendEmail()` : Envoi d'emails sécurisé
    - `getEmailTemplateValidation()` : Template HTML validation
    - `getEmailTemplateRefus()` : Template HTML refus
    - `checkRateLimit()` : Vérification rate limiting
    - `recordAttempt()` : Enregistrement tentative
    - `resetRateLimit()` : Réinitialisation compteur
    - `isGestionnaire()` : Vérification rôle gestionnaire
    - `isGestionnaireOrAdmin()` : Vérification multi-rôles
    - `requireGestionnaireOrAdmin()` : Protection pages

- **admin_events.php**
  - Remplacement `requireAdmin()` par `requireGestionnaireOrAdmin()`
  - Accès ouvert aux gestionnaires

- **admin_creneaux.php**
  - Remplacement `requireAdmin()` par `requireGestionnaireOrAdmin()`
  - Accès ouvert aux gestionnaires

- **admin_categories.php**
  - Remplacement `requireAdmin()` par `requireGestionnaireOrAdmin()`
  - Accès ouvert aux gestionnaires

- **header.php**
  - Ajout condition `elseif(isGestionnaire())`
  - Lien "Gestion Événements" pour les gestionnaires
  - Distinction admin (dashboard complet) vs gestionnaire (événements)

#### Dashboard Membre

- **membre.php**
  - Encadré jaune pour non-adhérents (dashboard)
  - Bouton "Devenir adhérent de l'association"
  - Modal de confirmation d'adhésion
  - Gestion POST adhésion depuis le dashboard
  - Handler `isset($_POST['adherent'])` dans `update` existant
  - JavaScript : `showAdherentModal()`, `hideAdherentModal()`
  - Fermeture modal par clic extérieur

### 📁 Nouveaux Fichiers

- `update_db.sql` : Script de mise à jour de la base de données
- `INSTALLATION.md` : Guide complet d'installation et de configuration
- `CHANGELOG.md` : Ce fichier, historique des modifications

### 🐛 Corrections de Bugs

- Aucun bug identifié dans le code existant (code bien sécurisé de base)

### 🔐 Sécurité Existante (Non Modifiée)

- ✅ Protection CSRF sur tous les formulaires
- ✅ Protection XSS via `sanitize()` (htmlspecialchars)
- ✅ Protection SQL Injection (PDO + requêtes préparées)
- ✅ Hashage bcrypt des mots de passe
- ✅ Gestion sécurisée des sessions

---

## [1.0.0] - Date Antérieure

### Fonctionnalités Initiales

- Système d'inscription avec validation admin
- Login avec vérification de statut
- Gestion des événements sportifs (créneaux bénévoles)
- Gestion des événements associatifs (participants + invités)
- Dashboard admin
- Dashboard membre
- Gestion des catégories
- Gestion des créneaux horaires
- Système d'adhésion (champ en DB mais pas d'interface)
- Rôle gestionnaire (champ en DB mais pas utilisé)

---

## 🔮 Prochaines Améliorations Possibles

### Court Terme
- [ ] Vérification email lors de l'inscription (lien de confirmation)
- [ ] Récupération de mot de passe oublié
- [ ] Pagination dans les listes admin (membres, événements)
- [ ] Statistiques avancées (dashboard admin)

### Moyen Terme
- [ ] Système de paiement en ligne pour les adhésions
- [ ] Notifications push/email pour les événements
- [ ] Export PDF/Excel des listes de participants
- [ ] Calendrier interactif des événements

### Long Terme
- [ ] API REST pour application mobile
- [ ] Espace de discussion (forum/chat)
- [ ] Système de réservations de créneaux d'entraînement
- [ ] Gestion des cotisations annuelles

---

**Note** : Cette version 2.0.0 complète le projet avec toutes les fonctionnalités de sécurité, de gestion des adhésions, d'emails automatiques et de rôles gestionnaires demandées.
