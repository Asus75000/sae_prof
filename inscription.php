<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$type = $_GET['type'] ?? 'sport';
$event_id = $_GET['id'] ?? null;

if(!$event_id) redirect('evenements.php');

if($type === 'sport') {
    $event = getEventSport($event_id);
    $creneaux = getCreneaux($event_id);
} else {
    $event = getEventAsso($event_id);

    // Vérifier l'accès à l'événement (privé = adhérents uniquement)
    checkEventAccess($event, 'evenements.php?type=asso');
}

// Vérifier que l'événement existe
if(!$event) {
    flash("Événement introuvable.", "danger");
    redirect('evenements.php?type=' . $type);
}

// Vérifier que les inscriptions sont encore ouvertes
if(strtotime($event['date_cloture']) < time()) {
    flash("Les inscriptions pour cet événement sont closes.", "warning");
    redirect('evenements.php?type=' . $type . '&id=' . $event_id);
}

// TRAITEMENT INSCRIPTION
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(validateCSRF($_POST['csrf_token'])) {

        if($type === 'sport') {
            // Vérifier si déjà inscrit à cet événement sportif
            if(isUserRegisteredToEventSport($_SESSION['user_id'], $event_id)) {
                flash("Vous êtes déjà inscrit à cet événement.", "warning");
                redirect('membre.php');
            }
            
            // Inscription aux créneaux sélectionnés
            $selected = $_POST['creneaux'] ?? [];
            foreach($selected as $id_creneau) {
                inscrireCreneau($id_creneau, $_SESSION['user_id']);
            }
            flash("Inscription réussie aux créneaux sélectionnés", "success");
        } else {
            // Inscription événement associatif
            $nb_invites = (int)($_POST['nb_invites'] ?? 0);
            
            // Validation du nombre d'invités
            if($nb_invites < 0 || $nb_invites > 10) {
                flash("Le nombre d'invités doit être entre 0 et 10.", "danger");
                redirect('inscription.php?type=' . $type . '&id=' . $event_id);
            }

            // Vérifier si déjà inscrit
            if(isUserRegisteredToEventAsso($_SESSION['user_id'], $event_id)) {
                flash("Vous êtes déjà inscrit à cet événement.", "warning");
                redirect('membre.php');
            }

            // Inscrire le membre
            if(inscrireEventAsso($_SESSION['user_id'], $event_id, $nb_invites)) {
                if($nb_invites > 0) {
                    flash("Inscription réussie ! Vous et vos {$nb_invites} invité(s) devrez payer sur place.", "success");
                } else {
                    flash("Inscription réussie ! Le paiement s'effectuera sur place.", "success");
                }
            }
        }

        redirect('membre.php');
    }
}

$csrf = generateCSRF();
include 'header.php';
?>

<div class="container">
    <h1>Inscription - <?= sanitize($event['titre']) ?></h1>
    
    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <?php if($type === 'sport'): ?>
                <h3>Sélectionnez vos créneaux</h3>
                <div class="creneaux-list">
                    <?php foreach($creneaux as $c): ?>
                        <div class="creneau-item">
                            <label class="checkbox-label">
                                <input type="checkbox" name="creneaux[]" value="<?= $c['id_creneau'] ?>">
                                <span class="creneau-text">
                                    <strong><?= $c['type'] ?></strong> - <?= formatDate($c['date_creneau']) ?>
                                    de <?= substr($c['heure_debut'],0,5) ?> à <?= substr($c['heure_fin'],0,5) ?>
                                    <?php if($c['commentaire']): ?>
                                        <small class="color-gray">(<?= sanitize($c['commentaire']) ?>)</small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <p><strong>Date de l'événement :</strong> <?= formatDateTime($event['date_event_asso']) ?></p>
                <p><strong>Tarif :</strong> <?= $event['tarif'] ?> € par personne - Paiement sur place</p>

                <div class="form-group">
                    <label for="nb_invites">Nombre d'invités (optionnel) :</label>
                    <input type="number" id="nb_invites" name="nb_invites" value="0" min="0" max="10">
                    <small>Vous pouvez amener jusqu'à 10 invités (chaque invité paiera <?= $event['tarif'] ?> € sur place)</small>
                </div>

                <div class="alert alert-info">
                    <strong>💳 Modalités de paiement :</strong><br>
                    Le paiement s'effectuera sur place le jour de l'événement.
                    <?php if(isset($_POST['nb_invites']) && (int)$_POST['nb_invites'] > 0): ?>
                        <br>Montant total estimé : <?= $event['tarif'] * (1 + (int)$_POST['nb_invites']) ?> € (vous + invités)
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <button type="submit" class="btn">Confirmer l'inscription</button>
            <a href="evenements.php?type=<?= $type ?>&id=<?= $event_id ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>