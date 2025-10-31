<?php

// AUTHENTIFICATION

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool True si l'utilisateur a un user_id en session, False sinon
 */
function isLogged() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur connecté est un administrateur
 * @return bool True si l'utilisateur est admin, False sinon
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

/**
 * Vérifie si l'utilisateur connecté est un gestionnaire
 * @return bool True si l'utilisateur est gestionnaire, False sinon
 */
function isGestionnaire() {
    if(!isLogged()) {
        return false;
    }
    $membre = getMembre($_SESSION['user_id']);
    return $membre && $membre['gestionnaire_o_n_'];
}

/**
 * Vérifie si l'utilisateur connecté est un gestionnaire ou un administrateur
 * @return bool True si l'utilisateur est gestionnaire ou admin, False sinon
 */
function isGestionnaireOrAdmin() {
    return isAdmin() || isGestionnaire();
}

/**
 * Exige que l'utilisateur soit connecté pour accéder à la page
 * Redirige vers index.php si l'utilisateur n'est pas connecté
 */
function requireLogin() {
    if(!isLogged()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Exige que l'utilisateur soit administrateur pour accéder à la page
 * Redirige vers membre.php si l'utilisateur n'est pas admin, ou vers index.php si non connecté
 */
function requireAdmin() {
    requireLogin();
    if(!isAdmin()) {
        header('Location: membre.php');
        exit;
    }
}

/**
 * Exige que l'utilisateur soit gestionnaire ou administrateur pour accéder à la page
 * Redirige vers membre.php si non autorisé, ou vers index.php si non connecté
 */
function requireGestionnaireOrAdmin() {
    requireLogin();
    if(!isGestionnaireOrAdmin()) {
        flash("Accès réservé aux gestionnaires et administrateurs", "danger");
        header('Location: membre.php');
        exit;
    }
}

// SECURITE

/**
 * Nettoie les données pour éviter les attaques XSS
 * @param string $data Les données à nettoyer
 * @return string Les données nettoyées avec htmlspecialchars
 */
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifie et applique une limitation de taux pour prévenir les attaques par force brute et DDoS
 * @param string $action L'action à limiter (ex: 'login', 'register')
 * @param int $max_attempts Nombre maximum de tentatives autorisées
 * @param int $time_window Fenêtre de temps en secondes
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function checkRateLimit($action, $max_attempts = 5, $time_window = 300) {
    $key = 'rate_limit_' . $action;
    $now = time();

    // Initialiser ou récupérer les données de limitation
    if(!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => $now,
            'reset_time' => $now + $time_window
        ];
    }

    $data = $_SESSION[$key];

    // Si la fenêtre de temps est expirée, réinitialiser
    if($now > $data['reset_time']) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => $now,
            'reset_time' => $now + $time_window
        ];
        $data = $_SESSION[$key];
    }

    // Vérifier si la limite est atteinte
    if($data['attempts'] >= $max_attempts) {
        $wait_time = $data['reset_time'] - $now;
        return [
            'allowed' => false,
            'remaining' => 0,
            'wait_time' => $wait_time
        ];
    }

    return [
        'allowed' => true,
        'remaining' => $max_attempts - $data['attempts'],
        'wait_time' => 0
    ];
}

/**
 * Enregistre une tentative pour le rate limiting
 * @param string $action L'action à enregistrer
 */
function recordAttempt($action) {
    $key = 'rate_limit_' . $action;
    if(isset($_SESSION[$key])) {
        $_SESSION[$key]['attempts']++;
    }
}

/**
 * Réinitialise le compteur de rate limiting pour une action donnée
 * @param string $action L'action à réinitialiser
 */
function resetRateLimit($action) {
    $key = 'rate_limit_' . $action;
    unset($_SESSION[$key]);
}

/**
 * Génère un token CSRF pour sécuriser les formulaires
 * Crée un nouveau token s'il n'existe pas déjà en session
 * @return string Le token CSRF
 */
function generateCSRF() {
    if(!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide un token CSRF envoyé par un formulaire
 * @param string $token Le token à valider
 * @return bool True si le token est valide, False sinon
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// VALIDATION DES DONNEES

/**
 * Valide une adresse email
 * @param string $email L'adresse email à valider
 * @return bool True si l'email est valide, False sinon
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un mot de passe selon les critères de sécurité
 * Minimum 8 caractères, au moins une majuscule et un chiffre
 * @param string $password Le mot de passe à valider
 * @return bool True si le mot de passe est valide, False sinon
 */
function validatePassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

/**
 * Valide un numéro de téléphone français (format 0XXXXXXXXX)
 * @param string $phone Le numéro de téléphone à valider
 * @return bool True si le téléphone est valide, False sinon
 */
function validatePhone($phone) {
    // Optionnel : vide = valide
    if(empty($phone)) return true;
    // Nettoyer les espaces
    $phone = str_replace(' ', '', $phone);
    // Format français : 10 chiffres commençant par 0
    return preg_match('/^0[1-9][0-9]{8}$/', $phone);
}

/**
 * Valide les données d'inscription d'un membre
 * @param array $data Les données à valider
 * @return array Tableau avec 'valid' (bool) et 'errors' (array de messages)
 */
function validateMembreData($data) {
    $errors = [];

    // Validation prénom (2-50 caractères, lettres, espaces, tirets)
    if(empty($data['prenom'])) {
        $errors[] = "Le prénom est obligatoire.";
    } elseif(strlen($data['prenom']) < 2) {
        $errors[] = "Le prénom doit contenir au moins 2 caractères.";
    } elseif(strlen($data['prenom']) > 50) {
        $errors[] = "Le prénom ne peut pas dépasser 50 caractères.";
    } elseif(!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/u', $data['prenom'])) {
        $errors[] = "Le prénom ne peut contenir que des lettres, espaces et tirets.";
    }

    // Validation nom (2-50 caractères, lettres, espaces, tirets)
    if(empty($data['nom'])) {
        $errors[] = "Le nom est obligatoire.";
    } elseif(strlen($data['nom']) < 2) {
        $errors[] = "Le nom doit contenir au moins 2 caractères.";
    } elseif(strlen($data['nom']) > 50) {
        $errors[] = "Le nom ne peut pas dépasser 50 caractères.";
    } elseif(!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/u', $data['nom'])) {
        $errors[] = "Le nom ne peut contenir que des lettres, espaces et tirets.";
    }

    // Validation email (max 100 caractères)
    if(empty($data['mail'])) {
        $errors[] = "L'adresse email est obligatoire.";
    } elseif(strlen($data['mail']) > 100) {
        $errors[] = "L'adresse email ne peut pas dépasser 100 caractères.";
    } elseif(!validateEmail($data['mail'])) {
        $errors[] = "L'adresse email n'est pas valide.";
    }

    // Validation mot de passe (uniquement pour création)
    if(isset($data['mdp'])) {
        if(empty($data['mdp'])) {
            $errors[] = "Le mot de passe est obligatoire.";
        } elseif(!validatePassword($data['mdp'])) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre.";
        } elseif(strlen($data['mdp']) > 255) {
            $errors[] = "Le mot de passe ne peut pas dépasser 255 caractères.";
        }
    }

    // Validation téléphone (optionnel mais vérifié si fourni)
    if(!validatePhone($data['telephone'])) {
        $errors[] = "Le numéro de téléphone n'est pas valide (format attendu : 0XXXXXXXXX).";
    }

    // Validation tailles (optionnel mais limité si fourni)
    $tailles_valides = ['', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];

    if(isset($data['taille_teeshirt']) && !in_array($data['taille_teeshirt'], $tailles_valides)) {
        $errors[] = "Taille de t-shirt invalide.";
    }

    if(isset($data['taille_pull']) && !in_array($data['taille_pull'], $tailles_valides)) {
        $errors[] = "Taille de pull invalide.";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// MEMBRES

/**
 * Récupère les informations d'un membre par son ID
 * @param int $id L'ID du membre
 * @return array|false Les données du membre ou false si non trouvé
 */
function getMembre($id) {
    global $pdo;
    $requete = $pdo->prepare("select * from membre where id_membre = ?");
    $requete->execute([$id]);
    return $requete->fetch();
}

/**
 * Récupère les informations d'un membre par son email
 * @param string $email L'adresse email du membre
 * @return array|false Les données du membre ou false si non trouvé
 */
function getMembreByEmail($email) {
    global $pdo;
    $requete = $pdo->prepare("select * from membre where mail = ?");
    $requete->execute([$email]);
    return $requete->fetch();
}

/**
 * Récupère tous les membres avec des filtres optionnels
 * @param array $filters Tableau de filtres (statut, adherent)
 * @return array Liste de tous les membres correspondant aux filtres
 */
function getAllMembres($filters = []) {
    global $pdo;
    $sql = "select * from membre where 1=1";
    $params = [];

    if(isset($filters['statut'])) {
        $sql .= " and statut = ?";
        $params[] = $filters['statut'];
    }
    if(isset($filters['adherent'])) {
        $sql .= " and adherent = ?";
        $params[] = $filters['adherent'];
    }

    $requete = $pdo->prepare($sql);
    $requete->execute($params);
    return $requete->fetchAll();
}

/**
 * Crée un nouveau membre dans la base de données
 * Le mot de passe est hashé automatiquement et le statut est défini à 'ATTENTE'
 * @param array $data Tableau contenant les données du membre (prenom, nom, mail, mdp, telephone, taille_teeshirt, taille_pull, adherent)
 * @return bool True si la création réussit, False sinon
 */
function createMembre($data) {
    global $pdo;
    $adherent = isset($data['adherent']) ? (int)$data['adherent'] : 0;
    $requete = $pdo->prepare("
        insert into membre (prenom, nom, mail, mdp, telephone, taille_teeshirt, taille_pull, statut, adherent)
        values (?, ?, ?, ?, ?, ?, ?, 'ATTENTE', ?)
    ");
    return $requete->execute([
        $data['prenom'],
        $data['nom'],
        $data['mail'],
        password_hash($data['mdp'], PASSWORD_DEFAULT),
        $data['telephone'],
        $data['taille_teeshirt'],
        $data['taille_pull'],
        $adherent
    ]);
}

/**
 * Met à jour les informations d'un membre
 * Permet de modifier dynamiquement n'importe quel champ passé dans $data
 * @param int $id L'ID du membre à modifier
 * @param array $data Tableau associatif des champs à modifier (clé = nom du champ, valeur = nouvelle valeur)
 * @return bool True si la mise à jour réussit, False sinon
 */
function updateMembre($id, $data) {
    global $pdo;
    $sql = "update membre set ";
    $params = [];
    $sets = [];

    foreach($data as $key => $value) {
        $sets[] = "$key = ?";
        $params[] = $value;
    }

    $sql .= implode(', ', $sets) . " where id_membre = ?";
    $params[] = $id;

    $requete = $pdo->prepare($sql);
    return $requete->execute($params);
}

// EVENEMENTS

/**
 * Récupère tous les événements sportifs visibles à ce jour
 * Inclut le nom de la catégorie pour chaque événement
 * @return array Liste de tous les événements sportifs avec leur catégorie, triés par date de clôture décroissante
 */
function getAllEventsSport() {
    global $pdo;
    $requete = $pdo->query("
        select es.*, ce.libelle as categorie
        from event_sport es
        left join cat_event ce on es.id_cat_event = ce.id_cat_event
        where date_visible <= curdate()
        order by date_cloture desc
    ");
    return $requete->fetchAll();
}

/**
 * Récupère tous les événements associatifs selon les droits de l'utilisateur
 * - En mode admin : tous les événements visibles
 * - Non connecté : uniquement les événements publics
 * - Connecté adhérent : tous les événements
 * - Connecté non-adhérent : uniquement les événements publics
 * @param bool $admin_mode Si true, affiche tous les événements (mode administrateur)
 * @return array Liste des événements associatifs autorisés, triés par date décroissante
 */
function getAllEventsAsso($admin_mode = false) {
    global $pdo;

    // En mode admin, voir tous les événements
    if($admin_mode) {
        $requete = $pdo->query("
            select * from event_asso
            where date_visible <= curdate()
            order by date_event_asso desc
        ");
        return $requete->fetchAll();
    }

    // Si l'utilisateur n'est pas connecté, ne montrer que les événements publics
    if(!isLogged()) {
        $requete = $pdo->query("
            select * from event_asso
            where date_visible <= curdate() and prive = 0
            order by date_event_asso desc
        ");
        return $requete->fetchAll();
    }

    // Si l'utilisateur est connecté, vérifier s'il est adhérent
    $membre = getMembre($_SESSION['user_id']);

    if($membre['adherent']) {
        // Adhérent : voir tous les événements
        $requete = $pdo->query("
            select * from event_asso
            where date_visible <= curdate()
            order by date_event_asso desc
        ");
    } else {
        // Non-adhérent : voir uniquement les événements publics
        $requete = $pdo->query("
            select * from event_asso
            where date_visible <= curdate() and prive = 0
            order by date_event_asso desc
        ");
    }

    return $requete->fetchAll();
}

/**
 * Récupère les détails d'un événement sportif par son ID
 * Inclut le nom de la catégorie
 * @param int $id L'ID de l'événement sportif
 * @return array|false Les données de l'événement sportif avec sa catégorie ou false si non trouvé
 */
function getEventSport($id) {
    global $pdo;
    $requete = $pdo->prepare("
        select es.*, ce.libelle as categorie
        from event_sport es
        left join cat_event ce on es.id_cat_event = ce.id_cat_event
        where id_event_sport = ?
    ");
    $requete->execute([$id]);
    return $requete->fetch();
}

/**
 * Récupère les détails d'un événement associatif par son ID
 * @param int $id L'ID de l'événement associatif
 * @return array|false Les données de l'événement associatif ou false si non trouvé
 */
function getEventAsso($id) {
    global $pdo;
    $requete = $pdo->prepare("select * from event_asso where id_event_asso = ?");
    $requete->execute([$id]);
    return $requete->fetch();
}

/**
 * Crée un nouvel événement sportif dans la base de données
 * @param array $data Tableau contenant les données de l'événement (titre, descriptif, lieu_texte, lieu_maps, date_visible, date_cloture, id_cat_event)
 * @return bool True si la création réussit, False sinon
 */
function createEventSport($data) {
    global $pdo;
    $requete = $pdo->prepare("
        insert into event_sport (titre, descriptif, lieu_texte, lieu_maps, date_visible, date_cloture, id_cat_event)
        values (?, ?, ?, ?, ?, ?, ?)
    ");
    return $requete->execute([
        $data['titre'], $data['descriptif'],
        $data['lieu_texte'], $data['lieu_maps'],
        $data['date_visible'], $data['date_cloture'], $data['id_cat_event']
    ]);
}

/**
 * Crée un nouvel événement associatif dans la base de données
 * @param array $data Tableau contenant les données de l'événement (titre, descriptif, lieu_texte, lieu_maps, date_visible, date_cloture, tarif, prive, date_event_asso)
 * @return bool True si la création réussit, False sinon
 */
function createEventAsso($data) {
    global $pdo;
    $requete = $pdo->prepare("
        insert into event_asso (titre, descriptif, lieu_texte, lieu_maps, date_visible, date_cloture, tarif, prive, date_event_asso)
        values (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    return $requete->execute([
        $data['titre'], $data['descriptif'],
        $data['lieu_texte'], $data['lieu_maps'],
        $data['date_visible'], $data['date_cloture'],
        $data['tarif'], $data['prive'], $data['date_event_asso']
    ]);
}

/**
 * Met à jour un événement sportif existant
 * @param int $id L'ID de l'événement sportif à modifier
 * @param array $data Tableau contenant les nouvelles données de l'événement
 * @return bool True si la mise à jour réussit, False sinon
 */
function updateEventSport($id, $data) {
    global $pdo;
    $requete = $pdo->prepare("
        update event_sport
        set titre = ?, descriptif = ?, lieu_texte = ?, lieu_maps = ?,
            date_visible = ?, date_cloture = ?, id_cat_event = ?
        where id_event_sport = ?
    ");
    return $requete->execute([
        $data['titre'], $data['descriptif'],
        $data['lieu_texte'], $data['lieu_maps'],
        $data['date_visible'], $data['date_cloture'], $data['id_cat_event'],
        $id
    ]);
}

/**
 * Met à jour un événement associatif existant
 * @param int $id L'ID de l'événement associatif à modifier
 * @param array $data Tableau contenant les nouvelles données de l'événement
 * @return bool True si la mise à jour réussit, False sinon
 */
function updateEventAsso($id, $data) {
    global $pdo;
    $requete = $pdo->prepare("
        update event_asso
        set titre = ?, descriptif = ?, lieu_texte = ?, lieu_maps = ?,
            date_visible = ?, date_cloture = ?, tarif = ?,
            prive = ?, date_event_asso = ?
        where id_event_asso = ?
    ");
    return $requete->execute([
        $data['titre'], $data['descriptif'],
        $data['lieu_texte'], $data['lieu_maps'],
        $data['date_visible'], $data['date_cloture'],
        $data['tarif'], $data['prive'], $data['date_event_asso'],
        $id
    ]);
}

/**
 * Supprime un événement sportif de la base de données
 * Les créneaux et inscriptions associés seront supprimés automatiquement (CASCADE)
 * @param int $id L'ID de l'événement sportif à supprimer
 * @return bool True si la suppression réussit, False sinon
 */
function deleteEventSport($id) {
    global $pdo;
    // Les créneaux et inscriptions seront supprimés automatiquement grâce aux contraintes CASCADE
    $requete = $pdo->prepare("delete from event_sport where id_event_sport = ?");
    return $requete->execute([$id]);
}

/**
 * Supprime un événement associatif de la base de données
 * Les participations associées seront supprimées automatiquement (CASCADE)
 * @param int $id L'ID de l'événement associatif à supprimer
 * @return bool True si la suppression réussit, False sinon
 */
function deleteEventAsso($id) {
    global $pdo;
    // Les participations seront supprimées automatiquement grâce aux contraintes CASCADE
    $requete = $pdo->prepare("delete from event_asso where id_event_asso = ?");
    return $requete->execute([$id]);
}

// CRENEAUX

/**
 * Récupère tous les créneaux d'un événement sportif
 * @param int $id_event_sport L'ID de l'événement sportif
 * @return array Liste des créneaux triés par date et heure de début
 */
function getCreneaux($id_event_sport) {
    global $pdo;
    $requete = $pdo->prepare("select * from creneau_event where id_event_sport = ? order by date_creneau, heure_debut");
    $requete->execute([$id_event_sport]);
    return $requete->fetchAll();
}

/**
 * Crée un nouveau créneau pour un événement sportif
 * @param array $data Tableau contenant les données du créneau (type, commentaire, date_creneau, heure_debut, heure_fin, id_event_sport)
 * @return bool True si la création réussit, False sinon
 */
function createCreneau($data) {
    global $pdo;
    $requete = $pdo->prepare("
        insert into creneau_event (type, commentaire, date_creneau, heure_debut, heure_fin, id_event_sport)
        values (?, ?, ?, ?, ?, ?)
    ");
    return $requete->execute([
        $data['type'], $data['commentaire'], $data['date_creneau'],
        $data['heure_debut'], $data['heure_fin'], $data['id_event_sport']
    ]);
}

/**
 * Récupère les détails d'un créneau par son ID
 * @param int $id L'ID du créneau
 * @return array|false Les données du créneau ou false si non trouvé
 */
function getCreneau($id) {
    global $pdo;
    $requete = $pdo->prepare("select * from creneau_event where id_creneau = ?");
    $requete->execute([$id]);
    return $requete->fetch();
}

/**
 * Récupère un créneau par ses détails (event_sport, date, heure)
 * @param int $id_event_sport L'ID de l'événement sportif
 * @param string $date_creneau La date du créneau
 * @param string $heure_debut L'heure de début
 * @param string $heure_fin L'heure de fin
 * @return array|false Les données du créneau ou false si non trouvé
 */
function getCreneauByDetails($id_event_sport, $date_creneau, $heure_debut, $heure_fin) {
    global $pdo;
    $requete = $pdo->prepare("select * from creneau_event where id_event_sport = ? and date_creneau = ? and heure_debut = ? and heure_fin = ?");
    $requete->execute([$id_event_sport, $date_creneau, $heure_debut, $heure_fin]);
    return $requete->fetch();
}

/**
 * Met à jour un créneau existant
 * @param int $id L'ID du créneau à modifier
 * @param array $data Tableau contenant les nouvelles données du créneau
 * @return bool True si la mise à jour réussit, False sinon
 */
function updateCreneau($id, $data) {
    global $pdo;
    $requete = $pdo->prepare("
        update creneau_event
        set type = ?, commentaire = ?, date_creneau = ?, heure_debut = ?, heure_fin = ?
        where id_creneau = ?
    ");
    return $requete->execute([
        $data['type'], $data['commentaire'], $data['date_creneau'],
        $data['heure_debut'], $data['heure_fin'],
        $id
    ]);
}

/**
 * Supprime un créneau de la base de données
 * Les inscriptions bénévoles associées seront supprimées automatiquement (CASCADE)
 * @param int $id L'ID du créneau à supprimer
 * @return bool True si la suppression réussit, False sinon
 */
function deleteCreneau($id) {
    global $pdo;
    // Les inscriptions (aide_benevole) seront supprimées automatiquement grâce aux contraintes CASCADE
    $requete = $pdo->prepare("delete from creneau_event where id_creneau = ?");
    return $requete->execute([$id]);
}

// CATEGORIES

/**
 * Récupère toutes les catégories d'événements sportifs
 * @return array Liste de toutes les catégories triées par libellé alphabétique
 */
function getAllCategories() {
    global $pdo;
    $requete = $pdo->query("select * from cat_event order by libelle asc");
    return $requete->fetchAll();
}

/**
 * Récupère une catégorie par son ID
 * @param int $id L'ID de la catégorie
 * @return array|false Les données de la catégorie ou false si non trouvée
 */
function getCategorie($id) {
    global $pdo;
    $requete = $pdo->prepare("select * from cat_event where id_cat_event = ?");
    $requete->execute([$id]);
    return $requete->fetch();
}

/**
 * Crée une nouvelle catégorie d'événements sportifs
 * @param string $libelle Le nom de la catégorie
 * @return bool True si la création réussit, False sinon
 */
function createCategorie($libelle) {
    global $pdo;
    $requete = $pdo->prepare("insert into cat_event (libelle) values (?)");
    return $requete->execute([$libelle]);
}

/**
 * Met à jour le libellé d'une catégorie existante
 * @param int $id L'ID de la catégorie à modifier
 * @param string $libelle Le nouveau libellé de la catégorie
 * @return bool True si la mise à jour réussit, False sinon
 */
function updateCategorie($id, $libelle) {
    global $pdo;
    $requete = $pdo->prepare("update cat_event set libelle = ? where id_cat_event = ?");
    return $requete->execute([$libelle, $id]);
}

/**
 * Compte le nombre d'événements sportifs utilisant une catégorie
 * @param int $id_cat_event L'ID de la catégorie
 * @return int Le nombre d'événements utilisant cette catégorie
 */
function countEventsByCategory($id_cat_event) {
    global $pdo;
    $requete = $pdo->prepare("select count(*) from event_sport where id_cat_event = ?");
    $requete->execute([$id_cat_event]);
    return $requete->fetchColumn();
}

/**
 * Supprime une catégorie d'événements sportifs
 * Vérifie d'abord si la catégorie est utilisée par des événements
 * @param int $id L'ID de la catégorie à supprimer
 * @return bool True si la suppression réussit, False si la catégorie est utilisée ou en cas d'erreur
 */
function deleteCategorie($id) {
    global $pdo;
    // Vérifier si la catégorie est utilisée
    $count = countEventsByCategory($id);

    if($count > 0) {
        return false; // Catégorie utilisée, impossible de supprimer
    }

    $requete = $pdo->prepare("delete from cat_event where id_cat_event = ?");
    return $requete->execute([$id]);
}

/**
 * Inscrit un membre comme bénévole sur un créneau
 * Utilise INSERT IGNORE pour éviter les doublons (si déjà inscrit)
 * @param int $id_creneau L'ID du créneau
 * @param int $id_membre L'ID du membre
 * @return bool True si l'inscription réussit, False sinon
 */
function inscrireCreneau($id_creneau, $id_membre) {
    global $pdo;
    $requete = $pdo->prepare("insert ignore into aide_benevole (id_creneau, id_membre, presence) values (?, ?, 0)");
    return $requete->execute([$id_creneau, $id_membre]);
}

/**
 * Désinscrit un membre d'un créneau bénévole spécifique
 * @param int $id_creneau L'ID du créneau
 * @param int $id_membre L'ID du membre à désinscrire
 * @return bool True si la désinscription réussit, False sinon
 */
function desinscrireCreneau($id_creneau, $id_membre) {
    global $pdo;
    $requete = $pdo->prepare("delete from aide_benevole where id_creneau = ? and id_membre = ?");
    return $requete->execute([$id_creneau, $id_membre]);
}

/**
 * Désinscrit un membre de tous les créneaux d'un événement sportif
 * @param int $id_event_sport L'ID de l'événement sportif
 * @param int $id_membre L'ID du membre à désinscrire
 * @return bool True si la désinscription réussit, False sinon
 */
function desinscrireEventSport($id_event_sport, $id_membre) {
    global $pdo;
    $requete = $pdo->prepare("
        delete from aide_benevole
        where id_membre = ?
        and id_creneau in (select id_creneau from creneau_event where id_event_sport = ?)
    ");
    return $requete->execute([$id_membre, $id_event_sport]);
}

// PARTICIPATIONS

/**
 * Inscrit un membre à un événement associatif
 * Récupère automatiquement la date de l'événement pour l'enregistrer comme date de participation
 * @param int $id_membre L'ID du membre qui s'inscrit
 * @param int $id_event_asso L'ID de l'événement associatif
 * @param int $nb_invites Le nombre d'invités accompagnant le membre (par défaut 0)
 * @return bool True si l'inscription réussit, False sinon
 */
function inscrireEventAsso($id_membre, $id_event_asso, $nb_invites = 0) {
    global $pdo;

    // Récupérer la date de l'événement pour la date_participation
    $requete = $pdo->prepare("select date_event_asso from event_asso where id_event_asso = ?");
    $requete->execute([$id_event_asso]);
    $event = $requete->fetch();

    $requete = $pdo->prepare("insert into participer (id_membre, id_event_asso, paiement_ok, nb_invites, date_participation) values (?, ?, 0, ?, ?)");
    return $requete->execute([$id_membre, $id_event_asso, $nb_invites, $event['date_event_asso']]);
}

/**
 * Récupère la liste des participants à un événement associatif
 * Inclut les informations du membre et le statut de paiement
 * @param int $id_event_asso L'ID de l'événement associatif
 * @return array Liste des participants avec leurs informations et statut de paiement
 */
function getParticipants($id_event_asso) {
    global $pdo;
    $requete = $pdo->prepare("
        select m.*, p.paiement_ok, p.nb_invites
        from participer p
        join membre m on p.id_membre = m.id_membre
        where p.id_event_asso = ?
    ");
    $requete->execute([$id_event_asso]);
    return $requete->fetchAll();
}

/**
 * Désinscrit un membre d'un événement associatif
 * @param int $id_membre L'ID du membre à désinscrire
 * @param int $id_event_asso L'ID de l'événement associatif
 * @return bool True si la désinscription réussit, False sinon
 */
function desinscrireEventAsso($id_membre, $id_event_asso) {
    global $pdo;
    $requete = $pdo->prepare("delete from participer where id_membre = ? and id_event_asso = ?");
    return $requete->execute([$id_membre, $id_event_asso]);
}

// INSCRIPTIONS MEMBRE

/**
 * Récupère toutes les inscriptions d'un membre aux événements sportifs (créneaux bénévoles)
 * Inclut les informations sur l'événement, la catégorie, le créneau et la présence
 * @param int $id_membre L'ID du membre
 * @return array Liste des inscriptions aux événements sportifs avec détails des créneaux
 */
function getInscriptionsEventsSport($id_membre) {
    global $pdo;
    $requete = $pdo->prepare("
        select distinct es.*, ce.libelle as categorie, c.type as type_creneau,
               c.date_creneau, c.heure_debut, c.heure_fin, ab.presence
        from aide_benevole ab
        join creneau_event c on ab.id_creneau = c.id_creneau
        join event_sport es on c.id_event_sport = es.id_event_sport
        left join cat_event ce on es.id_cat_event = ce.id_cat_event
        where ab.id_membre = ?
        order by c.date_creneau desc, c.heure_debut desc
    ");
    $requete->execute([$id_membre]);
    return $requete->fetchAll();
}

/**
 * Récupère toutes les participations d'un membre aux événements associatifs
 * Inclut les informations sur l'événement, le statut de paiement et le nombre d'invités
 * @param int $id_membre L'ID du membre
 * @return array Liste des participations aux événements associatifs avec leurs détails
 */
function getInscriptionsEventsAsso($id_membre) {
    global $pdo;
    $requete = $pdo->prepare("
        select ea.*, p.paiement_ok, p.nb_invites
        from participer p
        join event_asso ea on p.id_event_asso = ea.id_event_asso
        where p.id_membre = ?
        order by ea.date_event_asso desc
    ");
    $requete->execute([$id_membre]);
    return $requete->fetchAll();
}
/**
 * Récupère la liste des membres inscrits à un créneau bénévole spécifique
 * Inclut les informations du membre et son statut de présence
 * @param int $id_creneau L'ID du créneau
 * @return array Liste des membres inscrits avec leur statut de présence
 */
function getInscritsCreneaux($id_creneau) {
    global $pdo;
    $requete = $pdo->prepare("
        select m.*, ab.presence
        from aide_benevole ab
        join membre m on ab.id_membre = m.id_membre
        where ab.id_creneau = ?
        order by m.nom, m.prenom
    ");
    $requete->execute([$id_creneau]);
    return $requete->fetchAll();
}

// UTILS

/**
 * Redirige l'utilisateur vers une URL et arrête l'exécution du script
 * @param string $url L'URL de destination
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Envoie un email sécurisé à un destinataire
 * @param string $to Email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $message Corps du message (peut contenir du HTML)
 * @param bool $is_html Si true, envoie un email HTML (défaut: true)
 * @return bool True si l'envoi réussit, False sinon
 */
function sendEmail($to, $subject, $message, $is_html = true) {
    // Valider l'email
    if(!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Email invalide: $to");
        return false;
    }

    // Headers pour l'email
    $headers = [];
    $headers[] = 'From: KASTA CROSSFIT <noreply@kasta-crossfit.fr>';
    $headers[] = 'Reply-To: contact@kasta-crossfit.fr';
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if($is_html) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }

    // Nettoyer le sujet (pas de retour à la ligne)
    $subject = str_replace(["\r", "\n"], '', $subject);

    // Envoi de l'email
    try {
        $result = mail($to, $subject, $message, implode("\r\n", $headers));

        if($result) {
            error_log("Email envoyé avec succès à: $to");
        } else {
            error_log("Échec de l'envoi d'email à: $to");
        }

        return $result;
    } catch(Exception $e) {
        error_log("Erreur lors de l'envoi d'email: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère le template HTML pour l'email de validation de compte
 * @param string $prenom Prénom du membre
 * @param string $nom Nom du membre
 * @return string Le contenu HTML de l'email
 */
function getEmailTemplateValidation($prenom, $nom) {
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; padding: 12px 24px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 0.9em; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>KASTA CROSSFIT</h1>
            </div>
            <div class='content'>
                <h2>✓ Votre compte a été validé !</h2>
                <p>Bonjour <strong>" . htmlspecialchars($prenom) . " " . htmlspecialchars($nom) . "</strong>,</p>
                <p>Nous sommes ravis de vous annoncer que votre compte KASTA CROSSFIT a été <strong>validé avec succès</strong> par notre équipe.</p>
                <p>Vous pouvez dès maintenant vous connecter à votre espace membre et :</p>
                <ul>
                    <li>Consulter et vous inscrire aux événements sportifs</li>
                    <li>Participer aux événements associatifs</li>
                    <li>Gérer votre profil</li>
                    <li>Devenir adhérent pour bénéficier de l'assurance et d'avantages exclusifs</li>
                </ul>
                <p style='text-align: center;'>
                    <a href='" . SITE_URL . "/index.php' class='button' style='color: white;'>Se connecter</a>
                </p>
                <p>À bientôt sur KASTA CROSSFIT !</p>
            </div>
            <div class='footer'>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                <p>&copy; " . date('Y') . " KASTA CROSSFIT - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Génère le template HTML pour l'email de refus de compte
 * @param string $prenom Prénom du membre
 * @param string $nom Nom du membre
 * @param string $motif Motif du refus
 * @return string Le contenu HTML de l'email
 */
function getEmailTemplateRefus($prenom, $nom, $motif) {
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .motif-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
            .footer { text-align: center; margin-top: 20px; font-size: 0.9em; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>KASTA CROSSFIT</h1>
            </div>
            <div class='content'>
                <h2>⚠ Votre demande d'inscription</h2>
                <p>Bonjour <strong>" . htmlspecialchars($prenom) . " " . htmlspecialchars($nom) . "</strong>,</p>
                <p>Nous vous remercions de l'intérêt que vous portez à l'association KASTA CROSSFIT.</p>
                <p>Malheureusement, nous ne pouvons pas donner suite à votre demande d'inscription pour le motif suivant :</p>
                <div class='motif-box'>
                    <strong>Motif :</strong><br>
                    " . nl2br(htmlspecialchars($motif)) . "
                </div>
                <p>Si vous pensez qu'il s'agit d'une erreur ou si vous souhaitez plus d'informations, n'hésitez pas à nous contacter.</p>
                <p>Cordialement,<br>L'équipe KASTA CROSSFIT</p>
            </div>
            <div class='footer'>
                <p>Contact : contact@kasta-crossfit.fr</p>
                <p>&copy; " . date('Y') . " KASTA CROSSFIT - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Enregistre un message flash en session pour affichage après redirection
 * @param string $message Le message à afficher
 * @param string $type Le type de message (info, success, warning, error, etc.)
 */
function flash($message, $type = 'info') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Récupère et supprime le message flash de la session
 * @return array|null Le message flash avec son type, ou null si aucun message
 */
function getFlash() {
    if(isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Formate une date au format français (jj/mm/aaaa)
 * @param string $date La date à formater (format SQL: YYYY-MM-DD)
 * @return string La date formatée au format jj/mm/aaaa
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Formate une date et heure au format français (jj/mm/aaaa à HH:MM)
 * @param string $datetime La date et heure à formater (format SQL: YYYY-MM-DD HH:MM:SS)
 * @return string La date et heure formatées au format jj/mm/aaaa à HH:MM
 */
function formatDateTime($datetime) {
    return date('d/m/Y à H:i', strtotime($datetime));
}

/**
 * Convertit une date au format JJ/MM/AAAA vers un format MySQL
 * @param string $date Date au format JJ/MM/AAAA HH:MM ou JJ/MM/AAAA
 * @return string|null Date au format MySQL (YYYY-MM-DD HH:MM:SS ou YYYY-MM-DD) ou null si invalide
 */
function dateToMysql($date) {
    if(empty($date)) return null;
    // Format attendu : JJ/MM/AAAA HH:MM ou JJ/MM/AAAA
    $parts = explode(' ', $date);
    $dateParts = explode('/', $parts[0]);

    if(count($dateParts) !== 3) return null;

    $day = str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
    $month = str_pad($dateParts[1], 2, '0', STR_PAD_LEFT);
    $year = $dateParts[2];

    $mysqlDate = "$year-$month-$day";

    // Si il y a une heure
    if(isset($parts[1])) {
        $mysqlDate .= ' ' . $parts[1] . ':00';
    }

    return $mysqlDate;
}

/**
 * Convertit une date MySQL vers le format JJ/MM/AAAA
 * @param string $mysqlDate Date au format MySQL (YYYY-MM-DD HH:MM:SS ou YYYY-MM-DD)
 * @return string Date au format JJ/MM/AAAA HH:MM ou JJ/MM/AAAA
 */
function mysqlToDate($mysqlDate) {
    if(empty($mysqlDate)) return '';
    // Format MySQL : YYYY-MM-DD HH:MM:SS ou YYYY-MM-DD
    $datetime = new DateTime($mysqlDate);
    $hasTime = (strpos($mysqlDate, ':') !== false);

    if($hasTime) {
        return $datetime->format('d/m/Y H:i');
    } else {
        return $datetime->format('d/m/Y');
    }
}

// STATISTIQUES

/**
 * Récupère les statistiques des membres
 * @return array Tableau associatif avec total, en_attente et adherents
 */
function getStatsMembers() {
    global $pdo;
    return [
        'total' => $pdo->query("select count(*) from membre")->fetchColumn(),
        'en_attente' => $pdo->query("select count(*) from membre where statut='ATTENTE'")->fetchColumn(),
        'adherents' => $pdo->query("select count(*) from membre where adherent=1")->fetchColumn()
    ];
}

/**
 * Récupère les statistiques des événements
 * @return array Tableau associatif avec sport et asso
 */
function getStatsEvents() {
    global $pdo;
    return [
        'sport' => $pdo->query("select count(*) from event_sport")->fetchColumn(),
        'asso' => $pdo->query("select count(*) from event_asso")->fetchColumn()
    ];
}

/**
 * Vérifie l'accès à un événement associatif privé
 * Redirige automatiquement si l'accès est refusé
 * @param array $event L'événement à vérifier
 * @param string $redirect_url URL de redirection en cas de refus (par défaut: evenements.php?type=asso)
 * @return void
 */
function checkEventAccess($event, $redirect_url = 'evenements.php?type=asso') {
    // Si l'événement n'est pas privé, accès autorisé
    if(!$event || !$event['prive']) {
        return;
    }

    // Vérifier si l'utilisateur est connecté
    if(!isLogged()) {
        flash("Cet événement est réservé aux adhérents. Veuillez vous connecter.", "warning");
        redirect('index.php');
    }

    // Vérifier si l'utilisateur est adhérent
    $membre = getMembre($_SESSION['user_id']);
    if(!$membre['adherent']) {
        flash("Cet événement est réservé aux adhérents de l'association.", "danger");
        redirect($redirect_url);
    }
}

/**
 * Vérifie si un utilisateur est inscrit à un événement sportif
 * @param int $id_membre L'ID du membre
 * @param int $id_event_sport L'ID de l'événement sportif
 * @return bool True si inscrit, False sinon
 */
function isUserRegisteredToEventSport($id_membre, $id_event_sport) {
    global $pdo;
    $requete = $pdo->prepare("
        select count(*) from aide_benevole ab
        join creneau_event c on ab.id_creneau = c.id_creneau
        where ab.id_membre = ? and c.id_event_sport = ?
    ");
    $requete->execute([$id_membre, $id_event_sport]);
    return $requete->fetchColumn() > 0;
}

/**
 * Vérifie si un utilisateur est inscrit à un événement associatif
 * @param int $id_membre L'ID du membre
 * @param int $id_event_asso L'ID de l'événement associatif
 * @return bool True si inscrit, False sinon
 */
function isUserRegisteredToEventAsso($id_membre, $id_event_asso) {
    global $pdo;
    $requete = $pdo->prepare("select count(*) from participer where id_membre = ? and id_event_asso = ?");
    $requete->execute([$id_membre, $id_event_asso]);
    return $requete->fetchColumn() > 0;
}

?>
