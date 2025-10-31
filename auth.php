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
    // Vérifier le rate limiting (3 tentatives max en 10 minutes)
    $rateLimit = checkRateLimit('register', 3, 600);

    if(!$rateLimit['allowed']) {
        $minutes = ceil($rateLimit['wait_time'] / 60);
        $error = "Trop de tentatives d'inscription. Veuillez réessayer dans {$minutes} minute(s).";
    } elseif(validateCSRF($_POST['csrf_token'])) {
        $data = [
            'prenom' => trim($_POST['prenom']),
            'nom' => trim($_POST['nom']),
            'mail' => trim($_POST['mail']),
            'mdp' => $_POST['mdp'],
            'telephone' => trim($_POST['telephone'] ?? ''),
            'taille_teeshirt' => $_POST['taille_teeshirt'] ?? '',
            'taille_pull' => $_POST['taille_pull'] ?? '',
            'adherent' => isset($_POST['adherent']) ? 1 : 0
        ];

        // Validation des données
        $validation = validateMembreData($data);

        if(!$validation['valid']) {
            recordAttempt('register');
            $error = implode('<br>', $validation['errors']);
        } elseif(createMembre($data)) {
            resetRateLimit('register');
            flash("Inscription réussie. En attente de validation par l'administrateur.", "success");
            redirect('index.php');
        } else {
            recordAttempt('register');
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
                <input type="text" name="prenom" placeholder="Prénom" required minlength="2" maxlength="50" pattern="[a-zA-ZÀ-ÿ\s\-]+" title="Lettres, espaces et tirets uniquement">

                <label>Nom *</label>
                <input type="text" name="nom" placeholder="Nom" required minlength="2" maxlength="50" pattern="[a-zA-ZÀ-ÿ\s\-]+" title="Lettres, espaces et tirets uniquement">

                <label>Email *</label>
                <input type="email" name="mail" placeholder="votre@email.fr" required maxlength="100">

                <label>Mot de passe * (8+ caractères, 1 majuscule, 1 chiffre)</label>
                <input type="password" name="mdp" placeholder="••••••••" required minlength="8" maxlength="255">
                
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

                <div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
                    <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="adherent" id="adherent_checkbox" value="1" style="margin-right: 10px; width: 20px; height: 20px; cursor: pointer;">
                        <span style="font-weight: 600; color: #333;">Je souhaite devenir adhérent de l'association</span>
                    </label>
                    <p style="margin: 10px 0 0 30px; font-size: 0.9em; color: #666;">
                        Les adhérents bénéficient de l'assurance de l'association et d'un accès privilégié aux événements privés.
                    </p>
                </div>

                <button type="submit" name="register" id="submit_register" class="btn btn-primary btn-block">S'inscrire</button>
            </form>
            
            <div class="login-footer">
                <p>Vous avez déjà un compte ?</p>
                <a href="index.php" class="btn btn-secondary btn-block">Se connecter</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'avertissement adhésion -->
<div id="adherent_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; margin: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top: 0; color: #dc3545;">⚠️ Attention</h2>
        <p style="line-height: 1.6; color: #333; font-size: 1.05em;">
            Vous avez choisi de ne pas devenir adhérent. <strong>Vous ne bénéficierez pas de l'assurance de l'association</strong>
            et n'aurez pas accès aux événements réservés aux adhérents.
        </p>
        <div style="margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" id="btn_become_adherent" class="btn btn-primary" style="flex: 1; min-width: 200px;">
                Je souhaite devenir adhérent
            </button>
            <button type="button" id="btn_continue_without" class="btn btn-secondary" style="flex: 1; min-width: 200px;">
                Continuer sans souscrire
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const adherentCheckbox = document.getElementById('adherent_checkbox');
    const modal = document.getElementById('adherent_modal');
    const btnBecomeAdherent = document.getElementById('btn_become_adherent');
    const btnContinueWithout = document.getElementById('btn_continue_without');
    let formSubmitAllowed = false;

    // Intercepter la soumission du formulaire
    form.addEventListener('submit', function(e) {
        // Si l'utilisateur n'a pas coché "adhérent" et n'a pas encore confirmé
        if (!adherentCheckbox.checked && !formSubmitAllowed) {
            e.preventDefault();
            modal.style.display = 'flex';
        }
    });

    // Bouton "Je souhaite devenir adhérent"
    btnBecomeAdherent.addEventListener('click', function() {
        adherentCheckbox.checked = true;
        modal.style.display = 'none';
        formSubmitAllowed = true;
        form.submit();
    });

    // Bouton "Continuer sans souscrire"
    btnContinueWithout.addEventListener('click', function() {
        adherentCheckbox.checked = false;
        modal.style.display = 'none';
        formSubmitAllowed = true;
        form.submit();
    });

    // Fermer le modal en cliquant en dehors
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php include 'footer.php'; ?>