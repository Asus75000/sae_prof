<?php
require_once 'config.php';
require_once 'functions.php';

$type = $_GET['type'] ?? 'sport';
$detail_id = $_GET['id'] ?? null;

if($detail_id) {
    // MODE DÉTAIL
    if($type === 'sport') {
        $event = getEventSport($detail_id);
        $creneaux = getCreneaux($detail_id);
    } else {
        $event = getEventAsso($detail_id);

        // Vérifier l'accès à l'événement (privé = adhérents uniquement)
        checkEventAccess($event, 'evenements.php?type=asso');
    }
    
    // Si l'événement n'existe pas, rediriger
    if(!$event) {
        flash("Événement introuvable.", "danger");
        redirect('evenements.php?type=' . $type);
    }
}

include 'header.php';
?>

<div class="container">
    
    <?php if($detail_id && $event): ?>
        <!-- DÉTAIL ÉVÉNEMENT -->
        <h1><?= sanitize($event['titre']) ?></h1>
        <div class="card">
            <p><?= nl2br(sanitize($event['descriptif'])) ?></p>
            
            <p><strong>Lieu :</strong> <?= sanitize($event['lieu_texte']) ?>
                <?php if($event['lieu_maps']): ?>
                    <a href="<?= sanitize($event['lieu_maps']) ?>" target="_blank">Voir sur la carte</a>
                <?php endif; ?>
            </p>
            
            <p><strong>Inscriptions jusqu'au :</strong> <?= formatDateTime($event['date_cloture']) ?></p>
            
            <?php if($type === 'sport' && $creneaux): ?>
                <h3>Créneaux disponibles</h3>
                <?php foreach($creneaux as $c): ?>
                    <p>
                        <strong><?= strtoupper($c['type']) ?></strong> - <?= formatDate($c['date_creneau']) ?> 
                        de <?= substr($c['heure_debut'],0,5) ?> à <?= substr($c['heure_fin'],0,5) ?>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if($type === 'asso'): ?>
                <div class="tarif-box tarif-box-payant">
                    <p class="tarif-amount">
                        <strong>Tarif :</strong>
                        <span class="tarif-payant-text"><?= number_format($event['tarif'], 2, ',', ' ') ?> €</span>
                        <br>
                        <small class="color-gray">Paiement sur place le jour de l'événement</small>
                    </p>
                </div>

                <p><strong>Date événement :</strong> <?= formatDateTime($event['date_event_asso']) ?></p>
            <?php endif; ?>
            
            <?php if(isLogged() && strtotime($event['date_cloture']) > time()): ?>
                <a href="inscription.php?type=<?= $type ?>&id=<?= $detail_id ?>" class="btn">S'inscrire</a>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <!-- LISTE ÉVÉNEMENTS -->
        <h1>Événements</h1>
        
        <div class="tabs">
            <button class="tab <?= $type === 'sport' ? 'active' : '' ?>" onclick="window.location='evenements.php?type=sport'">Sportifs</button>
            <button class="tab <?= $type === 'asso' ? 'active' : '' ?>" onclick="window.location='evenements.php?type=asso'">Associatifs</button>
        </div>
        
        <div class="row">
            <?php 
            if($type === 'sport') {
                $events = getAllEventsSport();
            } else {
                $events = getAllEventsAsso();
            }
            
            foreach($events as $e): 
            ?>
                <div class="col-md-4">
                    <div class="card">
                        <h3><?= sanitize($e['titre']) ?></h3>
                        <p><?= sanitize(substr($e['descriptif'], 0, 100)) ?>...</p>
                        
                        <!-- Affiche la date de clôture des inscriptions au format : jj/mm/aaaa à hh:mm -->
                        <p><small>Clôture : <?= formatDateTime($e['date_cloture']) ?></small></p>
                        
                        <!-- Lien vers la page de détails de l'événement
                             - type=sport ou type=asso (conserve le type actuel)
                             - id=5 (l'ID varie selon le type : id_event_sport ou id_event_asso) -->
                        <a href="evenements.php?type=<?= $type ?>&id=<?= $e[$type === 'sport' ? 'id_event_sport' : 'id_event_asso'] ?>" class="btn">Voir détails</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>