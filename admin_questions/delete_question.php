<?php
// Fichier : delete_question.php
require_once __DIR__ . '/auth.php'; 

// Récupération de l'ID de la question (peut être 'id' ou 'question_id')
$question_id = (int)($_GET['id'] ?? $_GET['question_id'] ?? 0); 
// Récupération de l'ID du quiz pour la redirection
$quiz_id = (int)($_GET['quiz_id'] ?? 0); 

if ($question_id <= 0) {
    // Redirection vers la liste de questions ou la liste de quiz si l'ID est manquant
    $redirect_url = ($quiz_id > 0) ? 'manage_questions.php?quiz_id=' . $quiz_id : 'manage_quizzes.php';
    header('Location: ' . $redirect_url . '?status=error&msg=' . urlencode("ID de question manquant."));
    exit;
}

try {
    // Suppression de la question (et de ses liaisons dans quiz_questions grâce à ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    
    // Redirection vers la page de gestion des questions du quiz
    $redirect_url = ($quiz_id > 0) 
        ? 'manage_questions.php?quiz_id=' . $quiz_id . '&status=deleted' 
        : 'manage_quizzes.php?status=deleted';
        
    header('Location: ' . $redirect_url);
    exit;

} catch (PDOException $e) {
    $msg = "Erreur de suppression de la question: " . $e->getMessage();
    // Redirection en cas d'erreur
    $redirect_url = ($quiz_id > 0) ? 'manage_questions.php?quiz_id=' . $quiz_id : 'manage_quizzes.php';
    header('Location: ' . $redirect_url . '?status=error&msg=' . urlencode($msg));
    exit;
}