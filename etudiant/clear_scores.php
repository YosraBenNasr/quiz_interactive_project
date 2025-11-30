<?php
 
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Méthode non autorisée.']));
}

try {
     $sql = "DELETE FROM scores";
    
      
    $pdo->exec($sql);
    
    echo json_encode(['success' => true, 'message' => 'Tous les scores du serveur ont été effacés.']);

} catch (PDOException $e) {
    http_response_code(500);
    // Affichage de l'erreur SQL pour le débogage
    die(json_encode(['error' => 'Erreur de base de données lors de l\'effacement des scores : ' . $e->getMessage()]));
}
?>