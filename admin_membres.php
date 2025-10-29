<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$statut_filter = $_GET['statut'] ?? null;

// VALIDER COMPTE
if(isset($_GET['valider']) && isset($_GET['csrf'])) {
    if(validateCSRF($_GET['csrf'])) {
        updateMembre($_GET['valider'], ['statut' => 'VALIDE', 'date_statut' => date('Y-m-d')]);
        flash("Compte validé", "success");
    }
    redirect('admin_membres.php');
}

// REFUSER COMPTE
if(isset($_GET['refuser']) && isset($_GET['csrf'])) {
    if(validateCSRF($_GET['csrf'])) {
        updateMembre($_GET['refuser'], ['statut' => 'REFUS', 'date_statut' => date('Y-m-d')]);
        flash("Compte refusé", "warning");
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
                                <?php if($m['statut'] === 'ATTENTE'): ?>
                                    <a href="?valider=<?= $m['id_membre'] ?>&csrf=<?= $csrf ?>" class="btn btn-sm btn-success">Valider</a>
                                    <a href="?refuser=<?= $m['id_membre'] ?>&csrf=<?= $csrf ?>" class="btn btn-sm btn-danger">Refuser</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>