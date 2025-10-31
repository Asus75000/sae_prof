<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$membre = getMembre($_SESSION['user_id']);

// Si c'est un admin, rediriger vers l'espace admin membre
if(isAdmin()) {
    // UPDATE PROFIL ADMIN
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
        if(validateCSRF($_POST['csrf_token'])) {
            $data = [
                'prenom' => trim($_POST['prenom']),
                'nom' => trim($_POST['nom']),
                'telephone' => trim($_POST['telephone']),
                'taille_teeshirt' => $_POST['taille_teeshirt'],
                'taille_pull' => $_POST['taille_pull'],
                'mail' => $membre['mail'] // Nécessaire pour la validation
            ];

            // Validation des données (sans mot de passe)
            $validation = validateMembreData($data);

            if(!$validation['valid']) {
                flash(implode('<br>', $validation['errors']), "danger");
                redirect('membre.php');
            }

            // Retirer le mail du tableau (non modifiable)
            unset($data['mail']);

            if(updateMembre($_SESSION['user_id'], $data)) {
                flash("Profil mis à jour", "success");
                redirect('membre.php');
            }
        }
    }

    // Récupérer tous les événements sportifs publiés
    $events_sport = getAllEventsSport();
    
    // Récupérer tous les événements associatifs publiés
    $events_asso = getAllEventsAsso(true);

    $csrf = generateCSRF();
    include 'header.php';
?>

<div class="container">
    <h1>Mon Espace Administrateur</h1>
    
    <?php $flash = getFlash(); if($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>
    
    <!-- DASHBOARD ADMIN -->
    <div class="card">
        <h2>Tableau de bord</h2>
        <p>Bienvenue <strong><?= sanitize($membre['prenom']) ?> <?= sanitize($membre['nom']) ?></strong></p>
        <p>Statut : <span class="badge badge-danger">Administrateur</span></p>
        <div class="mt-20">
            <a href="admin.php" class="btn">Panneau d'administration</a>
            <a href="admin_events.php?type=sport" class="btn btn-secondary">Gérer les événements</a>
        </div>
    </div>

    <!-- ÉVÉNEMENTS SPORTIFS PUBLIÉS -->
    <div class="card">
        <h2>Événements Sportifs Publiés (<?= count($events_sport) ?>)</h2>
        <?php if(empty($events_sport)): ?>
            <p class="centered-content">Aucun événement sportif publié.</p>
        <?php else: ?>
            <?php foreach($events_sport as $event): ?>
                <?php
                // Récupérer les créneaux de cet événement
                $creneaux = getCreneaux($event['id_event_sport']);
                $inscrits_par_creneau = [];
                $personnes_uniques = [];
                
                foreach($creneaux as $creneau) {
                    $inscrits = getInscritsCreneaux($creneau['id_creneau']);
                    $inscrits_par_creneau[$creneau['id_creneau']] = $inscrits;
                    
                    // Compter les personnes uniques
                    foreach($inscrits as $inscrit) {
                        $personnes_uniques[$inscrit['id_membre']] = true;
                    }
                }
                
                $total_inscrits = count($personnes_uniques);
                ?>
                <div class="event-admin-box">
                    <h3><?= sanitize($event['titre']) ?> 
                        <span class="badge"><?= sanitize($event['categorie']) ?></span>
                    </h3>
                    <p><strong>Lieu :</strong> <?= sanitize($event['lieu_texte']) ?></p>
                    <p><strong>Clôture inscriptions :</strong> <?= formatDateTime($event['date_cloture']) ?></p>
                    <p><strong>Total inscrits :</strong> <span class="badge badge-success"><?= $total_inscrits ?> personne(s)</span></p>
                    
                    <?php if(!empty($creneaux)): ?>
                        <h4>Créneaux et inscrits :</h4>
                        <?php foreach($creneaux as $creneau): ?>
                            <div class="creneau-admin-detail">
                                <p><strong><?= sanitize($creneau['type']) ?></strong> - <?= formatDate($creneau['date_creneau']) ?> 
                                   de <?= substr($creneau['heure_debut'],0,5) ?> à <?= substr($creneau['heure_fin'],0,5) ?>
                                   (<?= count($inscrits_par_creneau[$creneau['id_creneau']]) ?> inscrit(s))
                                </p>
                                <?php if(!empty($inscrits_par_creneau[$creneau['id_creneau']])): ?>
                                    <ul class="liste-inscrits">
                                        <?php foreach($inscrits_par_creneau[$creneau['id_creneau']] as $inscrit): ?>
                                            <li>
                                                <?= sanitize($inscrit['prenom']) ?> <?= sanitize($inscrit['nom']) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ÉVÉNEMENTS ASSOCIATIFS PUBLIÉS -->
    <div class="card">
        <h2>Événements Associatifs Publiés (<?= count($events_asso) ?>)</h2>
        <?php if(empty($events_asso)): ?>
            <p class="centered-content">Aucun événement associatif publié.</p>
        <?php else: ?>
            <?php foreach($events_asso as $event): ?>
                <?php
                $participants = getParticipants($event['id_event_asso']);
                $total_invites = array_sum(array_column($participants, 'nb_invites'));
                ?>
                <div class="event-admin-box">
                    <h3><?= sanitize($event['titre']) ?>
                        <?php if($event['prive']): ?>
                            <span class="badge badge-warning">Privé</span>
                        <?php endif; ?>
                    </h3>
                    <p><strong>Date :</strong> <?= formatDateTime($event['date_event_asso']) ?></p>
                    <p><strong>Lieu :</strong> <?= sanitize($event['lieu_texte']) ?></p>
                    <p><strong>Tarif :</strong> <?= $event['tarif'] ?> € par personne - Paiement sur place</p>
                    <p><strong>Total inscrits :</strong> <span class="badge badge-success"><?= count($participants) ?> personne(s)</span></p>
                    <?php if($total_invites > 0): ?>
                        <p><strong>Total invités :</strong> <span class="badge badge-info"><?= $total_invites ?> invité(s)</span></p>
                    <?php endif; ?>
                    
                    <?php if(!empty($participants)): ?>
                        <h4>Liste des participants :</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Invités</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($participants as $p): ?>
                                    <tr>
                                        <td><?= sanitize($p['prenom']) ?> <?= sanitize($p['nom']) ?></td>
                                        <td><?= sanitize($p['mail']) ?></td>
                                        <td><?= sanitize($p['telephone']) ?></td>
                                        <td class="table-centered"><?= $p['nb_invites'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PROFIL ADMIN -->
    <div class="card">
        <h2>Mon Profil</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <label>Prénom</label>
            <input type="text" name="prenom" value="<?= sanitize($membre['prenom']) ?>" required>
            
            <label>Nom</label>
            <input type="text" name="nom" value="<?= sanitize($membre['nom']) ?>" required>
            
            <label>Email</label>
            <input type="email" value="<?= sanitize($membre['mail']) ?>" disabled>
            
            <label>Téléphone</label>
            <input type="tel" name="telephone" value="<?= sanitize($membre['telephone']) ?>">
            
            <label>Taille T-shirt</label>
            <select name="taille_teeshirt">
                <option value="">-- Sélectionner --</option>
                <?php foreach(['XS','S','M','L','XL','XXL'] as $taille): ?>
                    <option <?= $membre['taille_teeshirt'] === $taille ? 'selected' : '' ?>><?= $taille ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>Taille Pull</label>
            <select name="taille_pull">
                <option value="">-- Sélectionner --</option>
                <?php foreach(['XS','S','M','L','XL','XXL'] as $taille): ?>
                    <option <?= $membre['taille_pull'] === $taille ? 'selected' : '' ?>><?= $taille ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="update" class="btn">Mettre à jour</button>
        </form>
    </div>
</div>

<?php 
    include 'footer.php';
    exit; // Arrêter l'exécution pour l'admin
}

// CODE POUR LES MEMBRES NON-ADMIN

// UPDATE PROFIL
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $data = [
            'prenom' => trim($_POST['prenom']),
            'nom' => trim($_POST['nom']),
            'telephone' => trim($_POST['telephone']),
            'taille_teeshirt' => $_POST['taille_teeshirt'],
            'taille_pull' => $_POST['taille_pull'],
            'mail' => $membre['mail'] // Nécessaire pour la validation
        ];

        // Validation des données (sans mot de passe)
        $validation = validateMembreData($data);

        if(!$validation['valid']) {
            flash(implode('<br>', $validation['errors']), "danger");
            redirect('membre.php');
        }

        // Retirer le mail du tableau (non modifiable)
        unset($data['mail']);

        // Gestion adhésion : une fois adhérent, toujours adhérent (à vie)
        if(!$membre['adherent'] && isset($_POST['adherent'])) {
            // Nouveau adhérent : on met à jour le statut
            $data['adherent'] = 1;
        }
        // Si déjà adhérent, on ne peut plus changer (pas dans $data)

        if(updateMembre($_SESSION['user_id'], $data)) {
            $message = "Profil mis à jour";
            if(!$membre['adherent'] && isset($data['adherent'])) {
                $message .= " - Bienvenue parmi les adhérents !";
            }
            flash($message, "success");
            redirect('membre.php');
        }
    }
}

// Récupérer les inscriptions
$inscriptions_sport = getInscriptionsEventsSport($_SESSION['user_id']);
$inscriptions_asso = getInscriptionsEventsAsso($_SESSION['user_id']);

$csrf = generateCSRF();
include 'header.php';
?>

<div class="container">
    <h1>Mon Espace Membre</h1>
    
    <?php $flash = getFlash(); if($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>
    
    <!-- DASHBOARD -->
    <div class="card">
        <h2>Tableau de bord</h2>
        <p>Bienvenue <strong><?= sanitize($membre['prenom']) ?> <?= sanitize($membre['nom']) ?></strong></p>
        <p>Statut :
            <?php if($membre['adherent']): ?>
                <span class="badge badge-success">Adhérent</span>
            <?php else: ?>
                <span class="badge badge-warning">Non-adhérent</span>
            <?php endif; ?>
        </p>

        <?php if(!$membre['adherent']): ?>
        <div style="margin: 20px 0; padding: 20px; background-color: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
            <h3 style="margin-top: 0; color: #856404;">⚠️ Vous n'êtes pas encore adhérent</h3>
            <p style="color: #856404; margin-bottom: 15px;">
                En devenant adhérent, vous bénéficierez de l'assurance de l'association et d'un accès privilégié aux événements privés.
            </p>
            <button type="button" onclick="showAdherentModal()" class="btn btn-primary">
                Devenir adhérent de l'association
            </button>
        </div>
        <?php endif; ?>

        <div class="mt-20">
            <a href="evenements.php?type=sport" class="btn">Événements Sportifs</a>
            <a href="evenements.php?type=asso" class="btn btn-secondary">Événements Associatifs</a>
        </div>
    </div>
    
    <!-- MES INSCRIPTIONS SPORT -->
    <?php if(count($inscriptions_sport) > 0): ?>
    <div class="card">
        <h2>Mes Événements Sportifs</h2>
        <?php 
        $current_event = null;
        foreach($inscriptions_sport as $ins): 
            if($current_event !== $ins['id_event_sport']):
                // Fermer l'événement précédent
                if($current_event !== null): ?>
                    </div>
                </div>
                <?php endif;
                $current_event = $ins['id_event_sport'];
        ?>
            <div class="event-box">
                <div class="event-header">
                    <h3><?= sanitize($ins['titre']) ?></h3>
                    <span class="badge badge-primary"><?= sanitize($ins['categorie']) ?></span>
                </div>
                <div class="event-info">
                    <p><strong>Lieu:</strong> <?= sanitize($ins['lieu_texte']) ?></p>
                    <p><strong>Clôture inscriptions:</strong> <?= formatDateTime($ins['date_cloture']) ?></p>
                </div>

                <div class="creneaux-list">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h4>Mes créneaux:</h4>
                        <form method="POST" action="desinscription.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="type" value="event_sport">
                            <input type="hidden" name="id_event_sport" value="<?= $ins['id_event_sport'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Se désinscrire de l'événement</button>
                        </form>
                    </div>
        <?php endif; ?>
                    <div class="creneau-item-inscrit" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; margin-bottom: 10px; background: #f5f5f5; border-radius: 5px;">
                        <div class="creneau-info">
                            <span class="badge badge-type"><?= sanitize($ins['type_creneau']) ?></span>
                            <span class="creneau-date"><?= formatDate($ins['date_creneau']) ?></span>
                            <span class="creneau-horaire">de <?= substr($ins['heure_debut'],0,5) ?> à <?= substr($ins['heure_fin'],0,5) ?></span>
                        </div>
                        <form method="POST" action="desinscription.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="type" value="creneau">
                            <?php
                            // Récupérer l'ID du créneau via la fonction
                            $creneau = getCreneauByDetails($ins['id_event_sport'], $ins['date_creneau'], $ins['heure_debut'], $ins['heure_fin']);
                            ?>
                            <input type="hidden" name="id_creneau" value="<?= $creneau['id_creneau'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Annuler</button>
                        </form>
                    </div>
        <?php 
        endforeach; 
        // Fermer le dernier événement
        if($current_event !== null): ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- MES INSCRIPTIONS ASSO -->
    <?php if(count($inscriptions_asso) > 0): ?>
    <div class="card">
        <h2>Mes Événements Associatifs</h2>
        <div class="events-grid">
            <?php foreach($inscriptions_asso as $ins): ?>
                <div class="event-box-asso">
                    <div class="event-header">
                        <h3><?= sanitize($ins['titre']) ?></h3>
                    </div>
                    <div class="event-details">
                        <p><strong>Date:</strong> <?= formatDateTime($ins['date_event_asso']) ?></p>
                        <p><strong>Lieu:</strong> <?= sanitize($ins['lieu_texte']) ?></p>
                        <p><strong>Tarif:</strong> <?= $ins['tarif'] ?> € par personne</p>
                        <?php if($ins['nb_invites'] > 0): ?>
                            <p><strong>Invités:</strong> <?= $ins['nb_invites'] ?></p>
                        <?php endif; ?>
                        <p><strong>Paiement:</strong> <span class="badge badge-info">SUR PLACE</span></p>
                    </div>
                    <form method="POST" action="desinscription.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="type" value="event_asso">
                        <input type="hidden" name="id_event_asso" value="<?= $ins['id_event_asso'] ?>">
                        <button type="submit" class="btn btn-danger btn-block">Se désinscrire</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if(count($inscriptions_sport) == 0 && count($inscriptions_asso) == 0): ?>
    <div class="card">
        <h2>Mes Inscriptions</h2>
        <p class="centered-content">
            Vous n'êtes inscrit à aucun événement pour le moment.
        </p>
        <div class="text-center">
            <a href="evenements.php" class="btn">Découvrir les événements</a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- PROFIL -->
    <div class="card">
        <h2>Mon Profil</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <label>Prénom</label>
            <input type="text" name="prenom" value="<?= sanitize($membre['prenom']) ?>" required>
            
            <label>Nom</label>
            <input type="text" name="nom" value="<?= sanitize($membre['nom']) ?>" required>
            
            <label>Email</label>
            <input type="email" value="<?= sanitize($membre['mail']) ?>" disabled>
            
            <label>Téléphone</label>
            <input type="tel" name="telephone" value="<?= sanitize($membre['telephone']) ?>">
            
            <label>Taille T-shirt</label>
            <select name="taille_teeshirt">
                <option value="">-- Sélectionner --</option>
                <?php foreach(['XS','S','M','L','XL','XXL'] as $taille): ?>
                    <option <?= $membre['taille_teeshirt'] === $taille ? 'selected' : '' ?>><?= $taille ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>Taille Pull</label>
            <select name="taille_pull">
                <option value="">-- Sélectionner --</option>
                <?php foreach(['XS','S','M','L','XL','XXL'] as $taille): ?>
                    <option <?= $membre['taille_pull'] === $taille ? 'selected' : '' ?>><?= $taille ?></option>
                <?php endforeach; ?>
            </select>

            <div class="adherent-box <?= $membre['adherent'] ? 'adherent-box-active' : 'adherent-box-inactive' ?>">
                <?php if($membre['adherent']): ?>
                    <div class="adherent-box-content">
                        <div class="flex-1">
                            <strong>✓ Vous êtes adhérent de l'association</strong>
                            <br>
                            <small>Statut définitif acquis. Merci de votre soutien à Kasta CrossFit !</small>
                        </div>
                    </div>
                <?php else: ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="adherent" value="1">
                        <span class="flex-1">
                            <strong>Devenir adhérent de l'association</strong>
                            <br>
                            <small class="color-gray">En cochant cette case, vous devenez adhérent de l'association Kasta CrossFit de manière <strong>à vie</strong>. Cela vous donnera accès aux événements privés et à des avantages exclusifs.</small>
                        </span>
                    </label>
                <?php endif; ?>
            </div>

            <button type="submit" name="update" class="btn">Mettre à jour</button>
        </form>
    </div>
</div>

<!-- Modal d'adhésion depuis le dashboard -->
<?php if(!$membre['adherent']): ?>
<div id="adherent_dashboard_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; margin: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #007bff;">✓ Devenir adhérent</h2>
        <p style="line-height: 1.6; color: #333; font-size: 1.05em;">
            En devenant adhérent de l'association KASTA CROSSFIT, vous bénéficierez :
        </p>
        <ul style="line-height: 1.8; color: #333;">
            <li><strong>De l'assurance de l'association</strong></li>
            <li>D'un accès aux événements privés réservés aux adhérents</li>
            <li>De tarifs préférentiels sur certains événements</li>
            <li>Du soutien à la vie associative</li>
        </ul>
        <p style="color: #666; font-size: 0.95em; margin-top: 20px;">
            <em>Note : L'adhésion est définitive et à vie.</em>
        </p>
        <form method="POST" style="margin-top: 25px;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="adherent" value="1">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" name="update" class="btn btn-primary" style="flex: 1; min-width: 200px;">
                    Confirmer mon adhésion
                </button>
                <button type="button" onclick="hideAdherentModal()" class="btn btn-secondary" style="flex: 1; min-width: 150px;">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAdherentModal() {
    document.getElementById('adherent_dashboard_modal').style.display = 'flex';
}

function hideAdherentModal() {
    document.getElementById('adherent_dashboard_modal').style.display = 'none';
}

// Fermer le modal en cliquant en dehors
document.getElementById('adherent_dashboard_modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideAdherentModal();
    }
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>