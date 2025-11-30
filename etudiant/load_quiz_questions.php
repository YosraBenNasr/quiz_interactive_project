<?php
// Fichier : load_quiz_questions.php (API pour charger les questions d'un quiz spécifique)

header('Content-Type: application/json; charset=utf-8');
// Assurez-vous que ce chemin est correct
require_once __DIR__ . '/config.php'; 

// Récupérer le quiz_id depuis la requête GET (c'est ce que JS enverra)
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

if ($quiz_id === null || $quiz_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID du quiz manquant ou invalide.']);
    exit;
}

try {
    // 1. Vérifier si le quiz existe et est publié (sécurité)
    $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ? AND status = 'published'");
    $stmt->execute([$quiz_id]);
    $quiz_title = $stmt->fetchColumn();

    if (!$quiz_title) {
        http_response_code(404);
        echo json_encode(['error' => 'Quiz non trouvé ou non publié.']);
        exit;
    }

    // 2. Récupérer les questions liées à ce quiz (JOINTURE)
    // On sélectionne les questions qui sont liées à ce quiz_id et on les mélange (ORDER BY RAND())
    $stmt = $pdo->prepare("
        SELECT 
            q.id, q.question, q.choices, q.explanation
        FROM questions q
        JOIN quiz_questions qq ON q.id = qq.question_id
        WHERE qq.quiz_id = ?
        ORDER BY RAND()
    ");
    $stmt->execute([$quiz_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatter les données pour le JavaScript
    $questions = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'q' => $r['question'],
            'choices' => json_decode($r['choices'], true), // Décodage du JSON stocké en base
            'explanation' => $r['explanation'] ?? null
        ];
    }, $rows);

    if (empty($questions)) {
        // Optionnel : Gérer l'absence de questions pour un quiz publié
        http_response_code(404);
        echo json_encode(['error' => 'Ce quiz ne contient aucune question.']);
        exit;
    }

    // Retourner la liste des questions
    echo json_encode(['questions' => $questions, 'quizTitle' => $quiz_title]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Échec du chargement des questions.']);
}