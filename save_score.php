<?php
 
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['name']) || !isset($body['score']) || !isset($body['totalQuestions'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$name = mb_substr(trim($body['name']), 0, 100) ?: 'Anonyme';
$score = (float)$body['score'];
$totalQuestions = (int)$body['totalQuestions'];
$details = isset($body['details']) ? json_encode($body['details']) : null;

try {
    $stmt = $pdo->prepare('INSERT INTO scores (name, score, total_questions, details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $score, $totalQuestions, $details]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save']);
}
