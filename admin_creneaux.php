<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$id_event_sport = $_GET['id'] ?? null;
if(!$id_event_sport) redirect('admin_events.php?type=sport');

$event = getEventSport($id_event_sport);

// Vérifier que l'événement existe
if(!$event) {
    flash("Événement sportif introuvable.", "danger");
    redirect('admin_events.php?type=sport');
}

// CRÉER CRÉNEAU
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        // Conversion de la date française vers MySQL
        $date_creneau_mysql = dateToMysql($_POST['date_creneau']);

        $data = [
            'type' => $_POST['type'],
            'commentaire' => $_POST['commentaire'],
            'date_creneau' => $date_creneau_mysql,
            'heure_debut' => $_POST['heure_debut'],
            'heure_fin' => $_POST['heure_fin'],
            'id_event_sport' => $id_event_sport
        ];

        // Validation : la date de clôture doit être AVANT la date du créneau
        $datetime_creneau = $date_creneau_mysql . ' ' . $_POST['heure_debut'] . ':00';
        if(strtotime($event['date_cloture']) >= strtotime($datetime_creneau)) {
            flash("La date de clôture des inscriptions ({$event['date_cloture']}) doit être avant la date du créneau.", "danger");
            redirect("admin_creneaux.php?id=$id_event_sport");
        }

        if(createCreneau($data)) {
            flash("Créneau créé avec succès !", "success");
        } else {
            flash("Erreur lors de la création du créneau.", "error");
        }
        redirect("admin_creneaux.php?id=$id_event_sport");
    }
}

// MODIFIER CRÉNEAU
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $creneau_id = $_POST['creneau_id'];

        // Conversion de la date française vers MySQL
        $date_creneau_mysql = dateToMysql($_POST['date_creneau']);

        $data = [
            'type' => $_POST['type'],
            'commentaire' => $_POST['commentaire'],
            'date_creneau' => $date_creneau_mysql,
            'heure_debut' => $_POST['heure_debut'],
            'heure_fin' => $_POST['heure_fin']
        ];

        // Validation : la date de clôture doit être AVANT la date du créneau
        $datetime_creneau = $date_creneau_mysql . ' ' . $_POST['heure_debut'] . ':00';
        if(strtotime($event['date_cloture']) >= strtotime($datetime_creneau)) {
            flash("La date de clôture des inscriptions ({$event['date_cloture']}) doit être avant la date du créneau.", "danger");
            redirect("admin_creneaux.php?id=$id_event_sport&edit=$creneau_id");
        }

        if(updateCreneau($creneau_id, $data)) {
            flash("Créneau modifié avec succès !", "success");
        } else {
            flash("Erreur lors de la modification du créneau.", "error");
        }
        redirect("admin_creneaux.php?id=$id_event_sport");
    }
}

// SUPPRIMER CRÉNEAU
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $creneau_id = $_POST['creneau_id'];
        if(deleteCreneau($creneau_id)) {
            flash("Créneau supprimé avec succès !", "success");
        } else {
            flash("Erreur lors de la suppression du créneau.", "error");
        }
        redirect("admin_creneaux.php?id=$id_event_sport");
    }
}

// MODE ÉDITION : Charger le créneau à modifier
$edit_mode = isset($_GET['edit']);
$edit_creneau = null;
if($edit_mode) {
    $edit_id = $_GET['edit'];
    $edit_creneau = getCreneau($edit_id);
    
    // Vérifier que le créneau existe
    if(!$edit_creneau) {
        flash("Créneau introuvable.", "danger");
        redirect("admin_creneaux.php?id=$id_event_sport");
    }
}

$creneaux = getCreneaux($id_event_sport);
$csrf = generateCSRF();
include 'header.php';
?>

<div class="admin-layout">
    <div class="admin-sidebar">
        <h3>Administration</h3>
        <ul>
            <li><a href="admin.php">Dashboard</a></li>
            <li><a href="admin_membres.php">Membres</a></li>
            <li><a href="admin_events.php?type=sport">Events Sportifs</a></li>
            <li><a href="admin_events.php?type=asso">Events Asso</a></li>
            <li><a href="admin_categories.php">Catégories</a></li>
        </ul>
    </div>
    
    <div class="admin-content">
        <h1>Créneaux - <?= sanitize($event['titre']) ?></h1>

        <?php $flash = getFlash(); if($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
        <?php endif; ?>

        <!-- FORMULAIRE -->
        <div class="card">
            <?php if($edit_mode): ?>
                <h3>Modifier le créneau</h3>
            <?php else: ?>
                <h3>Ajouter un créneau</h3>
            <?php endif; ?>

            <form method="POST" class="form-grid" data-form="creneau">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="creneau_id" value="<?= $edit_creneau['id_creneau'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="type">Type *</label>
                    <select name="type" id="type" required>
                        <option value="INSTALLATION" <?= ($edit_mode && $edit_creneau['type'] === 'INSTALLATION') ? 'selected' : '' ?>>Installation</option>
                        <option value="EVENEMENT" <?= ($edit_mode && $edit_creneau['type'] === 'EVENEMENT') ? 'selected' : '' ?>>Événement</option>
                        <option value="RANGEMENT" <?= ($edit_mode && $edit_creneau['type'] === 'RANGEMENT') ? 'selected' : '' ?>>Rangement</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_creneau">Date *</label>
                    <input type="text" name="date_creneau" id="date_creneau" value="<?= $edit_mode ? date('d/m/Y', strtotime($edit_creneau['date_creneau'])) : '' ?>" required placeholder="jj/mm/aaaa" pattern="\d{2}/\d{2}/\d{4}">
                </div>

                <div class="grid-2col">
                    <div class="form-group">
                        <label for="heure_debut">Heure début *</label>
                        <input type="text" name="heure_debut" id="heure_debut" value="<?= $edit_mode ? substr($edit_creneau['heure_debut'], 0, 5) : '' ?>" required placeholder="HH:MM" pattern="[0-2][0-9]:[0-5][0-9]">
                    </div>
                    <div class="form-group">
                        <label for="heure_fin">Heure fin *</label>
                        <input type="text" name="heure_fin" id="heure_fin" value="<?= $edit_mode ? substr($edit_creneau['heure_fin'], 0, 5) : '' ?>" required placeholder="HH:MM" pattern="[0-2][0-9]:[0-5][0-9]">
                    </div>
                </div>

                <div class="form-group">
                    <label for="commentaire">Commentaire</label>
                    <input type="text" name="commentaire" id="commentaire" value="<?= $edit_mode ? sanitize($edit_creneau['commentaire']) : '' ?>" placeholder="Ex: Balisage du parcours">
                </div>

                <div class="d-flex gap-10">
                    <?php if($edit_mode): ?>
                        <button type="submit" name="update" class="btn flex-1">Enregistrer les modifications</button>
                        <a href="admin_creneaux.php?id=<?= $id_event_sport ?>" class="btn btn-secondary flex-1">Annuler</a>
                    <?php else: ?>
                        <button type="submit" name="create" class="btn flex-1">Ajouter le créneau</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- LISTE -->
        <div class="card">
            <h3>Liste des créneaux</h3>
            <?php if(empty($creneaux)): ?>
                <p class="centered-content">Aucun créneau pour le moment. Ajoutez-en un ci-dessus !</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Horaires</th>
                            <th>Commentaire</th>
                            <th class="table-centered table-actions-width">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($creneaux as $c): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?= strtolower($c['type']) ?>">
                                        <?= $c['type'] ?>
                                    </span>
                                </td>
                                <td><?= formatDate($c['date_creneau']) ?></td>
                                <td class="font-weight-500"><?= substr($c['heure_debut'],0,5) ?> - <?= substr($c['heure_fin'],0,5) ?></td>
                                <td class="color-gray"><?= sanitize($c['commentaire']) ?: '-' ?></td>
                                <td class="table-centered">
                                    <div class="action-buttons">
                                        <a href="admin_creneaux.php?id=<?= $id_event_sport ?>&edit=<?= $c['id_creneau'] ?>"
                                           class="btn btn-sm btn-warning"
                                           title="Modifier le créneau">
                                            Modifier
                                        </a>

                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="creneau_id" value="<?= $c['id_creneau'] ?>">
                                            <button type="submit" name="delete"
                                                    class="btn btn-sm btn-danger"
                                                    title="Supprimer le créneau">
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>