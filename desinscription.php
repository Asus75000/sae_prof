<?php
require_once 'config.php';
require_once 'functions.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

// Vérifier que la requête est en POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash("Méthode non autorisée", "error");
    redirect('membre.php');
}

// Valider le token CSRF
if(!validateCSRF($_POST['csrf_token'])) {
    flash("Token CSRF invalide", "error");
    redirect('membre.php');
}

// Récupérer l'ID du membre connecté
$id_membre = $_SESSION['user_id'];

// Récupérer le type de désinscription
$type = $_POST['type'] ?? '';

try {
    switch($type) {
        case 'event_sport':
            // Désinscription de tous les créneaux d'un événement sportif
            if(!isset($_POST['id_event_sport'])) {
                flash("ID de l'événement manquant", "error");
                redirect('membre.php');
            }

            $id_event_sport = (int)$_POST['id_event_sport'];

            if(desinscrireEventSport($id_event_sport, $id_membre)) {
                flash("Vous avez été désinscrit de l'événement sportif avec succès", "success");
            } else {
                flash("Erreur lors de la désinscription de l'événement sportif", "error");
            }
            break;

        case 'creneau':
            // Désinscription d'un créneau spécifique
            if(!isset($_POST['id_creneau'])) {
                flash("ID du créneau manquant", "error");
                redirect('membre.php');
            }

            $id_creneau = (int)$_POST['id_creneau'];

            if(desinscrireCreneau($id_creneau, $id_membre)) {
                flash("Vous avez été désinscrit du créneau avec succès", "success");
            } else {
                flash("Erreur lors de la désinscription du créneau", "error");
            }
            break;

        case 'event_asso':
            // Désinscription d'un événement associatif
            if(!isset($_POST['id_event_asso'])) {
                flash("ID de l'événement manquant", "error");
                redirect('membre.php');
            }

            $id_event_asso = (int)$_POST['id_event_asso'];

            if(desinscrireEventAsso($id_membre, $id_event_asso)) {
                flash("Vous avez été désinscrit de l'événement associatif avec succès", "success");
            } else {
                flash("Erreur lors de la désinscription de l'événement associatif", "error");
            }
            break;

        default:
            flash("Type de désinscription invalide", "error");
            break;
    }
} catch(Exception $e) {
    flash("Erreur lors de la désinscription : " . $e->getMessage(), "error");
}

// Rediriger vers la page membre
redirect('membre.php');
?>
