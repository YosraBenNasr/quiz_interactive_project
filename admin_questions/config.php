
<?php
$host = "localhost";
$dbname = "quizdb";
$user = "root";
$pass = "root";

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Démarrer la session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (Exception $e) {
    die("Erreur connexion à la base de données : " . $e->getMessage());
}

// 🚨 ADMIN EN CLAIR (NON SÉCURISÉ)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', '1234'); 
?>