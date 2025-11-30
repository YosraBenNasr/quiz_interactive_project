<?php
 
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $stmt = $pdo->query('SELECT id, question, choices, explanation FROM questions ORDER BY RAND()');
    $rows = $stmt->fetchAll();

    // decode JSON choices so JS reçoive array
    $questions = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'q' => $r['question'],
            'choices' => json_decode($r['choices'], true),
            'explanation' => $r['explanation']
        ];
    }, $rows);

    echo json_encode(['questions' => $questions]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch questions']);
}
