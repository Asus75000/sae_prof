<?php
require_once 'config.php';
require_once 'functions.php';

// Si déjà connecté, rediriger
if(isLogged()) {
    if(isAdmin()) {
        redirect('admin.php');
    } else {
        redirect('membre.php');
    }
}

// TRAITEMENT LOGIN
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Vérifier le rate limiting (5 tentatives max en 5 minutes)
    $rateLimit = checkRateLimit('login', 5, 300);

    if(!$rateLimit['allowed']) {
        $minutes = ceil($rateLimit['wait_time'] / 60);
        $error = "Trop de tentatives de connexion. Veuillez réessayer dans {$minutes} minute(s).";
    } elseif(validateCSRF($_POST['csrf_token'])) {
        $email = $_POST['email'];
        $mdp = $_POST['mdp'];

        $membre = getMembreByEmail($email);

        if($membre && password_verify($mdp, $membre['mdp'])) {
            if($membre['statut'] === 'VALIDE') {
                // Connexion réussie : réinitialiser le rate limiting
                resetRateLimit('login');

                $_SESSION['user_id'] = $membre['id_membre'];
                $_SESSION['user_name'] = $membre['prenom'] . ' ' . $membre['nom'];
                $_SESSION['is_admin'] = $membre['gestionnaire_o_n_'];

                redirect($membre['gestionnaire_o_n_'] ? 'admin.php' : 'membre.php');
            } else {
                recordAttempt('login');
                $error = "Votre compte n'est pas encore validé";
            }
        } else {
            recordAttempt('login');
            $error = "Email ou mot de passe incorrect";
        }
    }
}

$csrf = generateCSRF();
include 'header.php';
?>

<div class="container">
    <div class="login-container">
        <div class="card login-card">
            <h1>Connexion</h1>
            <p class="subtitle">Bienvenue sur KASTA CROSSFIT</p>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <?php $flash = getFlash(); if($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                
                <label>Email</label>
                <input type="email" name="email" placeholder="votre@email.fr" required autofocus>
                
                <label>Mot de passe</label>
                <input type="password" name="mdp" placeholder="••••••••" required>
                
                <button type="submit" name="login" class="btn btn-primary btn-block">Se connecter</button>
            </form>
            
            <div class="login-footer">
                <p>Pas encore de compte ?</p>
                <a href="auth.php?register=1" class="btn btn-secondary btn-block">Créer un compte</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>