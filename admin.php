<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

// Stats
$stats_membres = getStatsMembers();
$stats_events = getStatsEvents();

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
        <h1>Dashboard Administrateur</h1>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <h3><?= $stats_membres['total'] ?></h3>
                    <p>Membres Total</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <h3><?= $stats_membres['en_attente'] ?></h3>
                    <p>En attente</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <h3><?= $stats_membres['adherents'] ?></h3>
                    <p>Adhérents</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <h3><?= $stats_events['sport'] + $stats_events['asso'] ?></h3>
                    <p>Événements</p>
                </div>
            </div>
        </div>

        <?php if($stats_membres['en_attente'] > 0): ?>
            <div class="alert alert-warning">
                <?= $stats_membres['en_attente'] ?> compte(s) en attente de validation
                <a href="admin_membres.php?statut=ATTENTE" class="btn btn-sm">Voir</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>