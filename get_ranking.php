<?php
 
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

 
$sql = "SELECT name AS nom_utilisateur, score AS score_final, created_at AS date_enregistrement
        FROM scores
        ORDER BY score DESC, created_at ASC
        LIMIT 10"; 

try {
    $stmt = $pdo->query($sql);
    $ranking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($ranking_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du chargement du classement.']);
}