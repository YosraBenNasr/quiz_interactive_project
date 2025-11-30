
<?php
require_once __DIR__ . '/config.php';

// Vérifie si la variable de session 'admin_logged_in' n'est pas définie ou est fausse
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Si l'utilisateur n'est pas authentifié, le rediriger vers la page de connexion
    header('Location: index.php');
    exit;
}
?>