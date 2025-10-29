<?php
session_start();

// Configuration BDD
define('DB_HOST', 'localhost');
define('DB_NAME', 'kasta');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connexion PDO globale
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}

define('SITE_URL', 'http://localhost/kasta-crossfit');
?>