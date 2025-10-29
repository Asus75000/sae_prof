<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KASTA CROSSFIT</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="index.php" class="logo">KASTA CROSSFIT</a>
            <div class="nav-links">
                <?php if(isLogged()): ?>
                    <a href="membre.php">Mon Espace</a>
                    <?php if(isAdmin()): ?>
                        <a href="admin.php">Administration</a>
                    <?php endif; ?>
                    <a href="auth.php?logout=1">Déconnexion</a>
                <?php else: ?>
                    <a href="index.php">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>