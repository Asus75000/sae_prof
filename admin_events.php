<?php
require_once 'config.php';
require_once 'functions.php';
requireGestionnaireOrAdmin();

$type = $_GET['type'] ?? 'sport';

// CRÉER EVENT
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $event_type = $_POST['event_type'];

        $data = [
            'titre' => trim($_POST['titre']),
            'descriptif' => trim($_POST['descriptif']),
            'lieu_texte' => trim($_POST['lieu_texte']),
            'lieu_maps' => trim($_POST['lieu_maps']) ?: null,
            'date_visible' => dateToMysql($_POST['date_visible']),
            'date_cloture' => dateToMysql($_POST['date_cloture'])
        ];

        if($event_type === 'sport') {
            $data['id_cat_event'] = $_POST['id_cat_event'];
        } else {
            $data['tarif'] = $_POST['tarif'];
            $data['prive'] = isset($_POST['prive']) ? 1 : 0;
            $data['date_event_asso'] = dateToMysql($_POST['date_event_asso']);
        }

        // Validation des données
        $validation = validateEventData($data, $event_type);
        if(!$validation['valid']) {
            flash(implode('<br>', $validation['errors']), "danger");
            redirect("admin_events.php?type=$event_type");
        }

        // Validation : la date de visibilité doit être AVANT la date de clôture
        if(strtotime($data['date_visible']) >= strtotime($data['date_cloture'])) {
            flash("La date de visibilité doit être avant la date de clôture des inscriptions.", "danger");
            redirect("admin_events.php?type=$event_type");
        }

        if($event_type === 'sport') {
            createEventSport($data);
            flash("Événement sportif créé avec succès !", "success");
            redirect("admin_events.php?type=sport");
        } else {
            // Validation : la date de clôture doit être AVANT la date de l'événement
            if(strtotime($data['date_cloture']) >= strtotime($data['date_event_asso'])) {
                flash("La date de clôture des inscriptions doit être avant la date de l'événement.", "danger");
                redirect("admin_events.php?type=asso");
            }

            createEventAsso($data);
            flash("Événement associatif créé avec succès !", "success");
            redirect("admin_events.php?type=asso");
        }
    }
}

// MODIFIER EVENT
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $event_id = $_POST['event_id'];
        $event_type = $_POST['event_type'];

        $data = [
            'titre' => trim($_POST['titre']),
            'descriptif' => trim($_POST['descriptif']),
            'lieu_texte' => trim($_POST['lieu_texte']),
            'lieu_maps' => trim($_POST['lieu_maps']) ?: null,
            'date_visible' => dateToMysql($_POST['date_visible']),
            'date_cloture' => dateToMysql($_POST['date_cloture'])
        ];

        if($event_type === 'sport') {
            $data['id_cat_event'] = $_POST['id_cat_event'];
        } else {
            $data['tarif'] = $_POST['tarif'];
            $data['prive'] = isset($_POST['prive']) ? 1 : 0;
            $data['date_event_asso'] = dateToMysql($_POST['date_event_asso']);
        }

        // Validation des données
        $validation = validateEventData($data, $event_type);
        if(!$validation['valid']) {
            flash(implode('<br>', $validation['errors']), "danger");
            redirect("admin_events.php?type=$event_type&edit=$event_id");
        }

        // Validation : la date de visibilité doit être AVANT la date de clôture
        if(strtotime($data['date_visible']) >= strtotime($data['date_cloture'])) {
            flash("La date de visibilité doit être avant la date de clôture des inscriptions.", "danger");
            redirect("admin_events.php?type=$event_type&edit=$event_id");
        }

        if($event_type === 'sport') {
            if(updateEventSport($event_id, $data)) {
                flash("Événement sportif modifié avec succès !", "success");
            } else {
                flash("Erreur lors de la modification de l'événement.", "error");
            }
            redirect("admin_events.php?type=sport");
        } else {
            // Validation : la date de clôture doit être AVANT la date de l'événement
            if(strtotime($data['date_cloture']) >= strtotime($data['date_event_asso'])) {
                flash("La date de clôture des inscriptions doit être avant la date de l'événement.", "danger");
                redirect("admin_events.php?type=asso&edit=" . $event_id);
            }

            if(updateEventAsso($event_id, $data)) {
                flash("Événement associatif modifié avec succès !", "success");
            } else {
                flash("Erreur lors de la modification de l'événement.", "error");
            }
            redirect("admin_events.php?type=asso");
        }
    }
}

// SUPPRIMER EVENT
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $event_id = $_POST['event_id'];
        $event_type = $_POST['event_type'];

        if($event_type === 'sport') {
            if(deleteEventSport($event_id)) {
                flash("Événement sportif supprimé avec succès !", "success");
            } else {
                flash("Erreur lors de la suppression de l'événement.", "error");
            }
            redirect("admin_events.php?type=sport");
        } else {
            if(deleteEventAsso($event_id)) {
                flash("Événement associatif supprimé avec succès !", "success");
            } else {
                flash("Erreur lors de la suppression de l'événement.", "error");
            }
            redirect("admin_events.php?type=asso");
        }
    }
}

// MODE ÉDITION : Charger l'événement à modifier
$edit_mode = isset($_GET['edit']);
$edit_event = null;
if($edit_mode) {
    $edit_id = $_GET['edit'];
    if($type === 'sport') {
        $edit_event = getEventSport($edit_id);
    } else {
        $edit_event = getEventAsso($edit_id);
    }
    
    // Si l'événement n'existe pas, rediriger
    if(!$edit_event) {
        flash("Événement introuvable.", "danger");
        redirect('admin_events.php?type=' . $type);
    }
}

// LISTE
$events = $type === 'sport' ? getAllEventsSport() : getAllEventsAsso(true); // true = mode admin
$categories = getAllCategories();

$csrf = generateCSRF();
include 'header.php';
?>

<div class="admin-layout">
    <div class="admin-sidebar">
        <h3>Administration</h3>
        <ul>
            <li><a href="admin.php">Dashboard</a></li>
            <li><a href="admin_membres.php">Membres</a></li>
            <li><a href="admin_events.php?type=sport" class="<?= $type === 'sport' ? 'active' : '' ?>">Events Sportifs</a></li>
            <li><a href="admin_events.php?type=asso" class="<?= $type === 'asso' ? 'active' : '' ?>">Events Asso</a></li>
            <li><a href="admin_categories.php">Catégories</a></li>
        </ul>
    </div>

    <div class="admin-content">
        <h1>Gestion des Événements</h1>

        <?php $flash = getFlash(); if($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
        <?php endif; ?>

        <!-- FORMULAIRE CRÉATION/ÉDITION -->
        <div class="card">
            <?php if($edit_mode): ?>
                <h2 class="color-primary mb-10">Modifier l'événement</h2>
                <p class="color-gray mb-20">Modifiez les informations de l'événement "<?= sanitize($edit_event['titre']) ?>"</p>
            <?php else: ?>
                <h2 class="color-primary mb-10">Créer un nouvel événement <?= $type === 'sport' ? 'sportif' : 'associatif' ?></h2>
                <p class="color-gray mb-20">Remplissez les informations ci-dessous pour créer un <?= $type === 'sport' ? 'événement sportif' : 'événement associatif' ?></p>
            <?php endif; ?>

            <form method="POST" id="eventForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="event_type" value="<?= $type ?>">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="event_id" value="<?= $edit_event[$type === 'sport' ? 'id_event_sport' : 'id_event_asso'] ?>">
                <?php endif; ?>

                <!-- TYPE D'ÉVÉNEMENT -->
                <div class="form-section">
                    <h3>Type d'événement</h3>
                    <p class="color-gray">
                        <?= $type === 'sport' ? '<strong>Événement Sportif</strong>' : '<strong>Événement Associatif</strong>' ?>
                        <?php if($edit_mode): ?>
                            <small class="d-block mt-5 color-gray">Le type d'événement ne peut pas être modifié</small>
                        <?php else: ?>
                            <small class="d-block mt-5 color-gray">Utilisez le menu de gauche pour changer de type d'événement</small>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- INFORMATIONS GÉNÉRALES -->
                <div class="form-section">
                    <h3>Informations générales</h3>

                    <div class="form-group">
                        <label for="titre">Titre de l'événement *</label>
                        <input type="text" id="titre" name="titre" placeholder="Ex: Hyrox Challenge 2025" value="<?= $edit_mode ? sanitize($edit_event['titre']) : '' ?>" required>
                        <small>Donnez un nom accrocheur à votre événement</small>
                    </div>

                    <div class="form-group">
                        <label for="descriptif">Description complète *</label>
                        <textarea id="descriptif" name="descriptif" rows="4" placeholder="Décrivez l'événement en détail : objectifs, déroulement, public visé..." required><?= $edit_mode ? sanitize($edit_event['descriptif']) : '' ?></textarea>
                        <small>Soyez précis pour informer au mieux les participants</small>
                    </div>
                </div>

                <!-- LOCALISATION -->
                <div class="form-section">
                    <h3>Localisation</h3>

                    <div class="form-group">
                        <label for="lieu_texte">Adresse complète *</label>
                        <input type="text" id="lieu_texte" name="lieu_texte" placeholder="Ex: Halle des Sports, 10 Avenue de la Gloire, 31000 Toulouse" value="<?= $edit_mode ? sanitize($edit_event['lieu_texte']) : '' ?>" required>
                        <small>Indiquez l'adresse exacte du lieu</small>
                    </div>

                    <div class="form-group">
                        <label for="lieu_maps">Lien Google Maps (optionnel)</label>
                        <input type="url" id="lieu_maps" name="lieu_maps" placeholder="https://maps.google.com/?q=..." value="<?= $edit_mode ? sanitize($edit_event['lieu_maps']) : '' ?>">
                        <small>Pour faciliter l'accès au lieu</small>
                    </div>
                </div>

                <!-- DATES -->
                <div class="form-section">
                    <h3>Dates importantes</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_visible">Date de publication *</label>
                            <input type="text" id="date_visible" name="date_visible" placeholder="JJ/MM/AAAA" pattern="\d{2}/\d{2}/\d{4}" value="<?= $edit_mode ? mysqlToDate($edit_event['date_visible']) : '' ?>" required>
                            <small>À partir de quand l'événement sera visible (ex: 25/12/2025)</small>
                        </div>

                        <div class="form-group">
                            <label for="date_cloture">Date limite d'inscription *</label>
                            <input type="text" id="date_cloture" name="date_cloture" placeholder="JJ/MM/AAAA HH:MM" pattern="\d{2}/\d{2}/\d{4}\s\d{2}:\d{2}" value="<?= $edit_mode ? mysqlToDate($edit_event['date_cloture']) : '' ?>" required>
                            <small>Date et heure de clôture (ex: 31/12/2025 23:59)</small>
                        </div>
                    </div>
                </div>

                <!-- CHAMPS SPÉCIFIQUES SPORT -->
                <div class="form-section event-sport-fields <?= $type === 'sport' ? '' : 'hidden' ?>">
                    <h3>Spécifique aux événements sportifs</h3>

                    <div class="form-group">
                        <label for="id_cat_event">Catégorie sportive *</label>
                        <select id="id_cat_event" name="id_cat_event">
                            <option value="">-- Sélectionnez une catégorie --</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id_cat_event'] ?>" <?= ($edit_mode && $edit_event['id_cat_event'] == $cat['id_cat_event']) ? 'selected' : '' ?>><?= sanitize($cat['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Type de sport ou discipline concernée</small>
                    </div>
                </div>

                <!-- CHAMPS SPÉCIFIQUES ASSO -->
                <div class="form-section event-asso-fields <?= $type === 'asso' ? '' : 'hidden' ?>">
                    <h3>Spécifique aux événements associatifs</h3>

                    <div class="form-group">
                        <label for="date_event_asso">Date et heure de l'événement *</label>
                        <input type="text" id="date_event_asso" name="date_event_asso" placeholder="JJ/MM/AAAA HH:MM" pattern="\d{2}/\d{2}/\d{4}\s\d{2}:\d{2}" value="<?= ($edit_mode && $type === 'asso') ? mysqlToDate($edit_event['date_event_asso']) : '' ?>">
                        <small>Quand aura lieu l'événement (ex: 15/01/2026 19:00)</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tarif">Tarif de participation (€) *</label>
                            <input type="number" id="tarif" name="tarif" step="0.01" min="0" placeholder="25.00" value="<?= ($edit_mode && $type === 'asso') ? $edit_event['tarif'] : '' ?>">
                            <small>Prix par personne - Le paiement s'effectuera sur place</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="prive" id="prive" <?= ($edit_mode && $type === 'asso' && $edit_event['prive']) ? 'checked' : '' ?>>
                            <span>Événement privé (réservé aux adhérents)</span>
                        </label>
                        <small class="d-block ml-28 mt-5">Les non-adhérents ne pourront pas voir cet événement</small>
                    </div>
                </div>

                <!-- BOUTONS -->
                <div class="form-actions">
                    <?php if($edit_mode): ?>
                        <button type="submit" name="update" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="admin_events.php?type=<?= $type ?>" class="btn btn-secondary">Annuler</a>
                    <?php else: ?>
                        <button type="submit" name="create" class="btn btn-primary">Créer l'événement</button>
                        <button type="reset" class="btn btn-secondary">Réinitialiser</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- LISTE ÉVÉNEMENTS -->
        <div class="card">
            <h3>Liste des événements <?= $type === 'sport' ? 'sportifs' : 'associatifs' ?></h3>

            <?php if(count($events) === 0): ?>
                <p class="centered-content">
                    Aucun événement <?= $type === 'sport' ? 'sportif' : 'associatif' ?> pour le moment.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Date clôture</th>
                            <?php if($type === 'sport'): ?>
                                <th>Catégorie</th>
                            <?php else: ?>
                                <th>Tarif</th>
                                <th>Privé</th>
                            <?php endif; ?>
                            <th class="table-centered">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($events as $e): ?>
                            <tr>
                                <td><strong><?= sanitize($e['titre']) ?></strong></td>
                                <td><?= formatDateTime($e['date_cloture']) ?></td>
                                <?php if($type === 'sport'): ?>
                                    <td><?= sanitize($e['categorie']) ?></td>
                                <?php else: ?>
                                    <td><?= number_format($e['tarif'], 2, ',', ' ') ?> €</td>
                                    <td><?= $e['prive'] ? 'Oui' : 'Non' ?></td>
                                <?php endif; ?>
                                <td class="table-centered">
                                    <div class="action-buttons">
                                        <?php if($type === 'sport'): ?>
                                            <a href="admin_creneaux.php?id=<?= $e['id_event_sport'] ?>" class="btn btn-sm" title="Gérer les créneaux">
                                                Créneaux
                                            </a>
                                        <?php endif; ?>

                                        <a href="admin_events.php?type=<?= $type ?>&edit=<?= $type === 'sport' ? $e['id_event_sport'] : $e['id_event_asso'] ?>" class="btn btn-sm btn-warning" title="Modifier l'événement">
                                            Modifier
                                        </a>

                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="event_id" value="<?= $type === 'sport' ? $e['id_event_sport'] : $e['id_event_asso'] ?>">
                                            <input type="hidden" name="event_type" value="<?= $type ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger" title="Supprimer l'événement">
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