<?php
 
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

 
$input = json_decode(file_get_contents('php://input'), true);
$q_id = $input['question_id'] ?? null;
$chosen_index = $input['chosen_index'] ?? null; // Index 0-based

if ($q_id === null || $chosen_index === null) {
    http_response_code(400);
    die(json_encode(['error' => 'Paramètres manquants.']));
}

 
try {
     
    $stmt = $pdo->prepare("
        SELECT answer_index
        FROM questions
        WHERE id = ? 
    ");
    
    
    $stmt->execute([$q_id]);
    $question_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question_data) {
        http_response_code(404);
        die(json_encode(['error' => 'Question non trouvée.']));
    }
    
     
    $correct_index_from_db = (int)$question_data['answer_index'];
    
  
    
    $is_correct = ($chosen_index == $correct_index_from_db);

    echo json_encode([
        'success' => true,
        'isCorrect' => $is_correct,
        'correctIndex' => $correct_index_from_db
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur SQL lors de la vérification : ' . $e->getMessage()]));
}

?>