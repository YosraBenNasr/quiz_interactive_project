<?php
 
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || !isset($body['answers']) || !is_array($body['answers'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payload invalide']);
        exit;
    }

    $answers = $body['answers'];
    $timePerQuestion = isset($body['timePerQuestion']) ? (float)$body['timePerQuestion'] : 10.0;
    $penaltyPerSec = isset($body['penaltyPerSec']) ? (float)$body['penaltyPerSec'] : 0.05;

    // Récupérer toutes les questions concernées
    $ids = array_unique(array_map(fn($a)=> (int)$a['question_id'], $answers));
    if (count($ids) === 0) {
        echo json_encode(['error' => 'Aucune question fournie']);
        exit;
    }

    // Préparer la requête SQL avec placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, answer_index FROM questions WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows || count($rows) === 0) {
        echo json_encode(['error' => 'Questions introuvables en base']);
        exit;
    }

    // Mapper les bonnes réponses
    $answerMap = [];
    foreach ($rows as $r) {
        $answerMap[(int)$r['id']] = (int)$r['answer_index'];
    }

    $totalQuestions = count($answers);
    $score = 0.0;
    $details = [];

    foreach ($answers as $a) {
        $qid = (int)$a['question_id'];
        $chosen = isset($a['chosen']) ? (int)$a['chosen'] : null;
        $timeRemaining = isset($a['timeRemaining']) ? (float)$a['timeRemaining'] : 0.0;

        $correctIndex = $answerMap[$qid] ?? null;
        $isCorrect = ($chosen !== null && $correctIndex !== null && $chosen === $correctIndex);

        $elapsed = max(0, $timePerQuestion - $timeRemaining);
        $penalty = round($elapsed * $penaltyPerSec, 3);

        $points = 0.0;
        if ($isCorrect) {
            $points = max(0, 1 - $penalty);
            $score += $points;
        }

        $details[] = [
            'question_id' => $qid,
            'chosen' => $chosen,
            'correct' => $correctIndex,
            'isCorrect' => $isCorrect,
            'timeRemaining' => $timeRemaining,
            'penalty' => $penalty,
            'pointsGained' => round($points, 3)
        ];
    }

    $score = round($score, 3);

    // Optionnel : sauvegarder le score si demandé
    if (!empty($body['saveScore']) && !empty($body['name'])) {
        $name = mb_substr(trim($body['name']), 0, 100) ?: 'Anonyme';
        $stmt = $pdo->prepare('INSERT INTO scores (name, score, total_questions, details) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $score, $totalQuestions, json_encode($details)]);
    }

    echo json_encode([
        'score' => $score,
        'totalQuestions' => $totalQuestions,
        'details' => $details
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $e->getMessage()
    ]);
}
