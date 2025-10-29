<?php
require_once 'config.php';
require_once 'functions.php';

// LOGOUT
if(isset($_GET['logout'])) {
    session_destroy();
    redirect('index.php');
}

// Si pas de paramètre register, rediriger vers index
if(!isset($_GET['register'])) {
    redirect('index.php');
}

// REGISTER
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $data = [
            'prenom' => trim($_POST['prenom']),
            'nom' => trim($_POST['nom']),
            'mail' => trim($_POST['mail']),
            'mdp' => $_POST['mdp'],
            'telephone' => trim($_POST['telephone'] ?? ''),
            'taille_teeshirt' => $_POST['taille_teeshirt'] ?? '',
            'taille_pull' => $_POST['taille_pull'] ?? ''
        ];

        // Validation des données
        $validation = validateMembreData($data);

        if(!$validation['valid']) {
            $error = implode('<br>', $validation['errors']);
        } elseif(createMembre($data)) {
            flash("Inscription réussie. En attente de validation par l'administrateur.", "success");
            redirect('index.php');
        } else {
            $error = "Erreur lors de l'inscription. L'email existe peut-être déjà.";
        }
    }
}

$csrf = generateCSRF();
include 'header.php';
?>

<div class="container">
    <div class="login-container">
        <div class="card login-card">
            <h1>Créer un compte</h1>
            <p class="subtitle">Rejoignez KASTA CROSSFIT</p>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                
                <label>Prénom *</label>
                <input type="text" name="prenom" placeholder="Prénom" required>
                
                <label>Nom *</label>
                <input type="text" name="nom" placeholder="Nom" required>
                
                <label>Email *</label>
                <input type="email" name="mail" placeholder="votre@email.fr" required>
                
                <label>Mot de passe * (minimum 8 caractères)</label>
                <input type="password" name="mdp" placeholder="••••••••" required minlength="8">
                
                <label>Téléphone</label>
                <input type="tel" name="telephone" placeholder="0612345678">
                
                <label>Taille T-shirt</label>
                <select name="taille_teeshirt">
                    <option value="">-- Sélectionner --</option>
                    <option>XS</option>
                    <option>S</option>
                    <option>M</option>
                    <option>L</option>
                    <option>XL</option>
                    <option>XXL</option>
                </select>
                
                <label>Taille Pull</label>
                <select name="taille_pull">
                    <option value="">-- Sélectionner --</option>
                    <option>XS</option>
                    <option>S</option>
                    <option>M</option>
                    <option>L</option>
                    <option>XL</option>
                    <option>XXL</option>
                </select>
                
                <button type="submit" name="register" class="btn btn-primary btn-block">S'inscrire</button>
            </form>
            
            <div class="login-footer">
                <p>Vous avez déjà un compte ?</p>
                <a href="index.php" class="btn btn-secondary btn-block">Se connecter</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>