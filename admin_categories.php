<?php
require_once 'config.php';
require_once 'functions.php';
requireGestionnaireOrAdmin();

// CRÉER CATÉGORIE
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $libelle = trim($_POST['libelle']);
        if(!empty($libelle)) {
            if(createCategorie($libelle)) {
                flash("Catégorie créée avec succès !", "success");
            } else {
                flash("Erreur lors de la création de la catégorie.", "error");
            }
        } else {
            flash("Le libellé ne peut pas être vide.", "error");
        }
        redirect("admin_categories.php");
    }
}

// MODIFIER CATÉGORIE
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $id = $_POST['categorie_id'];
        $libelle = trim($_POST['libelle']);
        if(!empty($libelle)) {
            if(updateCategorie($id, $libelle)) {
                flash("Catégorie modifiée avec succès !", "success");
            } else {
                flash("Erreur lors de la modification de la catégorie.", "error");
            }
        } else {
            flash("Le libellé ne peut pas être vide.", "error");
        }
        redirect("admin_categories.php");
    }
}

// SUPPRIMER CATÉGORIE
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if(validateCSRF($_POST['csrf_token'])) {
        $id = $_POST['categorie_id'];
        if(deleteCategorie($id)) {
            flash("Catégorie supprimée avec succès !", "success");
        } else {
            flash("Impossible de supprimer cette catégorie car elle est utilisée par des événements.", "error");
        }
        redirect("admin_categories.php");
    }
}

// MODE ÉDITION : Charger la catégorie à modifier
$edit_mode = isset($_GET['edit']);
$edit_categorie = null;
if($edit_mode) {
    $edit_id = $_GET['edit'];
    $edit_categorie = getCategorie($edit_id);
    
    // Vérifier que la catégorie existe
    if(!$edit_categorie) {
        flash("Catégorie introuvable.", "danger");
        redirect("admin_categories.php");
    }
}

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
            <li><a href="admin_events.php?type=sport">Events Sportifs</a></li>
            <li><a href="admin_events.php?type=asso">Events Asso</a></li>
            <li><a href="admin_categories.php">Catégories</a></li>
        </ul>
    </div>

    <div class="admin-content">
        <h1>Gestion des Catégories d'Événements</h1>

        <?php $flash = getFlash(); if($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
        <?php endif; ?>

        <!-- FORMULAIRE -->
        <div class="card">
            <?php if($edit_mode): ?>
                <h3>Modifier la catégorie</h3>
            <?php else: ?>
                <h3>Ajouter une catégorie</h3>
            <?php endif; ?>

            <form method="POST" class="d-flex gap-15 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="categorie_id" value="<?= $edit_categorie['id_cat_event'] ?>">
                <?php endif; ?>

                <div class="flex-1">
                    <label for="libelle">Libellé de la catégorie *</label>
                    <input type="text"
                           name="libelle"
                           id="libelle"
                           value="<?= $edit_mode ? sanitize($edit_categorie['libelle']) : '' ?>"
                           placeholder="Ex: Trail, CrossFit, Course à pied, Natation..."
                           required>
                </div>

                <div class="d-flex gap-10">
                    <?php if($edit_mode): ?>
                        <button type="submit" name="update" class="btn">Enregistrer</button>
                        <a href="admin_categories.php" class="btn btn-secondary">Annuler</a>
                    <?php else: ?>
                        <button type="submit" name="create" class="btn">Ajouter</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTE -->
        <div class="card">
            <h3>Liste des catégories</h3>
            <?php if(empty($categories)): ?>
                <p class="centered-content">Aucune catégorie pour le moment. Ajoutez-en une ci-dessus !</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th class="table-centered table-actions-width-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categories as $cat): ?>
                            <?php
                            // Vérifier si la catégorie est utilisée
                            $nb_events = countEventsByCategory($cat['id_cat_event']);
                            ?>
                            <tr>
                                <td class="font-size-large">
                                    <strong><?= sanitize($cat['libelle']) ?></strong>
                                    <?php if($nb_events > 0): ?>
                                        <span class="color-gray font-size-small ml-10">(<?= $nb_events ?> événement<?= $nb_events > 1 ? 's' : '' ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-centered">
                                    <div class="action-buttons">
                                        <a href="admin_categories.php?edit=<?= $cat['id_cat_event'] ?>"
                                           class="btn btn-sm btn-warning"
                                           title="Modifier la catégorie">
                                            Modifier
                                        </a>

                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="categorie_id" value="<?= $cat['id_cat_event'] ?>">
                                            <button type="submit"
                                                    name="delete"
                                                    class="btn btn-sm btn-danger"
                                                    title="Supprimer la catégorie"
                                                    <?= $nb_events > 0 ? 'disabled' : '' ?>>
                                                Supprimer
                                            </button>
                                            <?php if($nb_events > 0): ?>
                                                <small class="d-block color-light-gray mt-5">Impossible (utilisée)</small>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <p>
                <strong>Info :</strong> Les catégories utilisées par des événements ne peuvent pas être supprimées. Vous devez d'abord supprimer ou modifier les événements concernés.
            </p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
