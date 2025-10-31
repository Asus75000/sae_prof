<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$statut_filter = $_GET['statut'] ?? null;

// VALIDER COMPTE
if(isset($_GET['valider']) && isset($_GET['csrf'])) {
    if(validateCSRF($_GET['csrf'])) {
        $membre = getMembre($_GET['valider']);

        if($membre) {
            updateMembre($_GET['valider'], ['statut' => 'VALIDE', 'date_statut' => date('Y-m-d')]);

            // Envoyer un email de validation
            $emailContent = getEmailTemplateValidation($membre['prenom'], $membre['nom']);
            sendEmail($membre['mail'], 'Votre compte KASTA CROSSFIT a été validé', $emailContent);

            flash("Compte validé et email envoyé à {$membre['prenom']} {$membre['nom']}", "success");
        } else {
            flash("Membre introuvable", "danger");
        }
    }
    redirect('admin_membres.php');
}

// REFUSER COMPTE (POST avec motif)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refuser_membre'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $id_membre = (int)$_POST['id_membre'];
        $motif = trim($_POST['motif_refus']);

        if(empty($motif)) {
            flash("Le motif de refus est obligatoire", "danger");
            redirect('admin_membres.php');
        }

        $membre = getMembre($id_membre);

        if($membre) {
            updateMembre($id_membre, [
                'statut' => 'REFUS',
                'date_statut' => date('Y-m-d'),
                'motif_refus' => $motif
            ]);

            // Envoyer un email de refus avec le motif
            $emailContent = getEmailTemplateRefus($membre['prenom'], $membre['nom'], $motif);
            sendEmail($membre['mail'], 'Votre demande d\'inscription KASTA CROSSFIT', $emailContent);

            flash("Compte refusé et email envoyé à {$membre['prenom']} {$membre['nom']}", "warning");
        } else {
            flash("Membre introuvable", "danger");
        }
    }
    redirect('admin_membres.php');
}

// TOGGLE STATUT GESTIONNAIRE
if(isset($_GET['toggle_gestionnaire']) && isset($_GET['csrf'])) {
    if(validateCSRF($_GET['csrf'])) {
        $id_membre = (int)$_GET['toggle_gestionnaire'];
        $membre = getMembre($id_membre);

        if($membre && $membre['statut'] === 'VALIDE') {
            // Inverser le statut gestionnaire (0 -> 1 ou 1 -> 0)
            $nouveau_statut = $membre['gestionnaire_o_n_'] ? 0 : 1;
            updateMembre($id_membre, ['gestionnaire_o_n_' => $nouveau_statut]);

            $action = $nouveau_statut ? "promu gestionnaire" : "retiré du rôle gestionnaire";
            flash("{$membre['prenom']} {$membre['nom']} a été {$action}", "success");
        } else {
            flash("Seuls les membres validés peuvent être gestionnaires", "danger");
        }
    }
    redirect('admin_membres.php');
}

$filters = $statut_filter ? ['statut' => $statut_filter] : [];
$membres = getAllMembres($filters);
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
        <h1>Gestion des Membres</h1>
        
        <!-- FILTRES -->
        <div>
            <a href="admin_membres.php" class="btn btn-sm">Tous</a>
            <a href="admin_membres.php?statut=ATTENTE" class="btn btn-sm">En attente</a>
            <a href="admin_membres.php?statut=VALIDE" class="btn btn-sm">Validés</a>
            <a href="admin_membres.php?statut=REFUS" class="btn btn-sm">Refusés</a>
        </div>
        
        <!-- TABLEAU -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Adhérent</th>
                        <th>Gestionnaire</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($membres as $m): ?>
                        <tr>
                            <td><?= sanitize($m['prenom'] . ' ' . $m['nom']) ?></td>
                            <td><?= sanitize($m['mail']) ?></td>
                            <td>
                                <?php if($m['statut'] === 'VALIDE'): ?>
                                    <span class="badge badge-success">Validé</span>
                                <?php elseif($m['statut'] === 'ATTENTE'): ?>
                                    <span class="badge badge-warning">Attente</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Refusé</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $m['adherent'] ? 'Oui' : 'Non' ?></td>
                            <td>
                                <?php if($m['gestionnaire_o_n_']): ?>
                                    <span class="badge badge-primary">Gestionnaire</span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: #6c757d;">Non</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($m['statut'] === 'ATTENTE'): ?>
                                    <a href="?valider=<?= $m['id_membre'] ?>&csrf=<?= $csrf ?>" class="btn btn-sm btn-success" onclick="return confirm('Valider le compte de <?= sanitize($m['prenom']) ?> <?= sanitize($m['nom']) ?> et envoyer un email ?')">Valider</a>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="showRefusModal(<?= $m['id_membre'] ?>, '<?= sanitize($m['prenom']) ?>', '<?= sanitize($m['nom']) ?>')">Refuser</button>
                                <?php elseif($m['statut'] === 'VALIDE'): ?>
                                    <?php
                                    $action_text = $m['gestionnaire_o_n_'] ? 'Retirer' : 'Nommer';
                                    $btn_class = $m['gestionnaire_o_n_'] ? 'btn-warning' : 'btn-primary';
                                    ?>
                                    <a href="?toggle_gestionnaire=<?= $m['id_membre'] ?>&csrf=<?= $csrf ?>"
                                       class="btn btn-sm <?= $btn_class ?>"
                                       onclick="return confirm('<?= $action_text ?> <?= sanitize($m['prenom']) ?> <?= sanitize($m['nom']) ?> comme gestionnaire ?')">
                                        <?= $action_text ?> gestionnaire
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de refus avec motif -->
<div id="refus_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; margin: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); width: 90%;">
        <h2 style="margin-top: 0; color: #dc3545;">Refuser l'inscription</h2>
        <p id="refus_membre_info" style="font-size: 1.05em; color: #333;"></p>
        <form method="POST" onsubmit="return validateRefusForm()">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id_membre" id="refus_id_membre">

            <label for="motif_refus" style="display: block; margin-bottom: 10px; font-weight: 600;">
                Motif du refus * <span style="color: #dc3545;">(obligatoire)</span>
            </label>
            <textarea
                name="motif_refus"
                id="motif_refus"
                rows="5"
                required
                placeholder="Veuillez indiquer la raison du refus de cette inscription..."
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; resize: vertical;"
            ></textarea>
            <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                Un email sera automatiquement envoyé au membre avec ce motif.
            </p>

            <div style="margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" name="refuser_membre" class="btn btn-danger" style="flex: 1; min-width: 150px;">
                    Confirmer le refus
                </button>
                <button type="button" onclick="hideRefusModal()" class="btn btn-secondary" style="flex: 1; min-width: 150px;">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRefusModal(idMembre, prenom, nom) {
    document.getElementById('refus_id_membre').value = idMembre;
    document.getElementById('refus_membre_info').textContent =
        'Vous êtes sur le point de refuser l\'inscription de ' + prenom + ' ' + nom + '.';
    document.getElementById('motif_refus').value = '';
    document.getElementById('refus_modal').style.display = 'flex';
}

function hideRefusModal() {
    document.getElementById('refus_modal').style.display = 'none';
}

function validateRefusForm() {
    const motif = document.getElementById('motif_refus').value.trim();
    if (motif.length < 10) {
        alert('Le motif doit contenir au moins 10 caractères.');
        return false;
    }
    return confirm('Confirmer le refus et envoyer l\'email au membre ?');
}

// Fermer le modal en cliquant en dehors
document.getElementById('refus_modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideRefusModal();
    }
});
</script>

<?php include 'footer.php'; ?>