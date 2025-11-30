<?php
// Fichier : manage_questions.php (Affichage UNIQUEMENT des Questions Liées - FINAL FONCTIONNEL)
require_once __DIR__ . '/auth.php'; 

// Initialisation des variables pour éviter les warnings Undefined variable
$quiz_id = (int)($_GET['quiz_id'] ?? 0);
$message = ''; 
$error = '';
$quiz_title = '';
$all_questions = []; 

// Gestion des messages de statut
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'updated') { $message = "Question mise à jour avec succès."; } // Message corrigé
    elseif ($_GET['status'] === 'added') { $message = "Nouvelle Question ajoutée avec succès."; } 
    elseif ($_GET['status'] === 'deleted') { $message = "Question supprimée avec succès."; }
    elseif ($_GET['status'] === 'error') { $error = htmlspecialchars($_GET['msg'] ?? 'Une erreur est survenue.'); }
}

if ($quiz_id <= 0) {
    header('Location: manage_quizzes.php?status=error&msg=' . urlencode("ID de Quiz manquant."));
    exit;
}

try {
    // 1. Récupérer le titre du quiz
    $quiz_stmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
    $quiz_stmt->execute([$quiz_id]);
    $quiz_title = $quiz_stmt->fetchColumn();

    if (!$quiz_title) {
        header('Location: manage_quizzes.php?status=error&msg=' . urlencode("Quiz introuvable."));
        exit;
    }
    
    // 2. Récupérer UNIQUEMENT les questions liées pour l'affichage
    // CORRECTION SQL: Retrait de 'correct_answer_index' et ajout de 'explanation'
    $questions_stmt = $pdo->prepare("
        SELECT 
            q.id, 
            q.question, 
            q.explanation, /* Ajout de la colonne 'explanation' comme alternative */
            s.title AS subject_title
        FROM questions q
        JOIN quiz_questions qq ON q.id = qq.question_id 
        LEFT JOIN subjects s ON q.subject_id = s.id
        WHERE qq.quiz_id = ?
        ORDER BY q.id DESC
    ");
    $questions_stmt->execute([$quiz_id]);
    $all_questions = $questions_stmt->fetchAll();


} catch (PDOException $e) {
    $error = "Erreur de chargement des données : " . $e->getMessage();
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gérer les Questions du Quiz: <?= htmlspecialchars($quiz_title) ?></title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        .question-list-table { width: 100%; border-collapse: collapse; }
        .question-list-table th, .question-list-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="main-nav">
            <h1>🔗 Questions du Quiz : <?= htmlspecialchars($quiz_title) ?></h1>
            <div>
                <a href="manage_quizzes.php" class="btn secondary">Retour aux Quiz</a>
            </div>
        </nav>
    </header>

    <div class="container">
        
        <?php if ($message): ?><p class="success-message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        
        <a href="add_question.php?quiz_id=<?= $quiz_id ?>" class="btn primary" style="margin-bottom: 20px;">+ Créer une nouvelle Question</a> 
        
       
        <?php if (empty($all_questions)): ?>
            <p>Ce quiz ne contient actuellement aucune question.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="question-list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Question (Sujet)</th>
                            <th>Explication</th> 
                            <th>Actions sur la Question</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_questions as $q): ?>
                            <tr>
                                <td><?= htmlspecialchars($q['id']) ?></td>
                                
                                <td>
                                    <?= htmlspecialchars($q['question']) ?>
                                    <br><small style="color: #666;">(Sujet : <strong><?= htmlspecialchars($q['subject_title'] ?? 'Non classé') ?></strong>)</small>
                                </td>
                                
                                <td>
                                    <?= htmlspecialchars($q['explanation'] ?? 'N/A') ?>
                                </td>
                                
                                <td style="white-space: nowrap;">
                                    <a href="edit_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>" class="btn primary small">Modifier</a>
                                    <a href="delete_question.php?question_id=<?= $q['id'] ?>&quiz_id=<?= $quiz_id ?>" 
                                       onclick="return confirm('Voulez-vous vraiment supprimer cette question de la banque de questions ?')" 
                                       class="btn danger small">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>