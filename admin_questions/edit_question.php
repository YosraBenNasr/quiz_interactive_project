<?php
// Fichier : edit_question.php (Modification d'une question existante)
require_once __DIR__ . '/auth.php'; 

// Récupération de l'ID de la question (peut être 'id' ou 'question_id')
$question_id = (int)($_GET['id'] ?? $_GET['question_id'] ?? 0);
// Récupération de l'ID du quiz pour la redirection
$quiz_id = (int)($_GET['quiz_id'] ?? 0); 
$error = '';
$question_data = null;
$subjects_list = [];
$current_choices = [];

if ($question_id <= 0) {
    $redirect_url = ($quiz_id > 0) ? 'manage_questions.php?quiz_id=' . $quiz_id : 'manage_quizzes.php';
    header('Location: ' . $redirect_url . '?status=error&msg=' . urlencode("ID de question manquant."));
    exit;
}

try {
    // 1. Récupérer la liste des sujets
    $subjects_stmt = $pdo->query("SELECT id, title FROM subjects ORDER BY title ASC");
    $subjects_list = $subjects_stmt->fetchAll();

    // 2. Récupérer la question existante avec les colonnes explicites
    $stmt = $pdo->prepare("
        SELECT id, subject_id, question, choices, answer_index, explanation 
        FROM questions 
        WHERE id = ?
    ");
    $stmt->execute([$question_id]);
    $question_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question_data) {
        $redirect_url = ($quiz_id > 0) ? 'manage_questions.php?quiz_id=' . $quiz_id : 'manage_quizzes.php';
        header('Location: ' . $redirect_url . '?status=error&msg=' . urlencode("Question introuvable."));
        exit;
    }
    
    // Décoder les choix pour le formulaire
    $current_choices = json_decode($question_data['choices'], true) ?? [];

} catch (PDOException $e) {
    $error = "Erreur de chargement des données : " . $e->getMessage();
}


// --- LOGIQUE DE TRAITEMENT DU FORMULAIRE POST (MISE À JOUR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    // Filtrer les choix vides
    $choices = array_filter($_POST['choices'] ?? [], function($choice) {
        return !empty(trim($choice));
    }); 
    // Attention : Assurez-vous que le nom de colonne est bien 'answer_index'
    $answer_index = (int)($_POST['answer_index'] ?? -1); 
    $explanation = trim($_POST['explanation'] ?? '');

    // Validation
    if (empty($question) || empty($choices) || $answer_index < 0 || $answer_index >= count($choices)) {
        $error = "Veuillez remplir tous les champs obligatoires et vérifier l'index de la réponse correcte.";
    } else {
        try {
            $choices_json = json_encode(array_values($choices));
            
            $stmt = $pdo->prepare("
                UPDATE questions 
                SET subject_id = ?, question = ?, choices = ?, answer_index = ?, explanation = ?
                WHERE id = ?
            ");
            // Vérifiez que la colonne 'answer_index' existe bien dans votre table 'questions'
            $stmt->execute([$subject_id, $question, $choices_json, $answer_index, $explanation, $question_id]);
            
            // Redirection vers la page des questions du quiz
            if ($quiz_id > 0) {
                header('Location: manage_questions.php?quiz_id=' . $quiz_id . '&status=updated');
            } else {
                header('Location: manage_quizzes.php?status=updated'); // Redirection par défaut si quiz_id est absent
            }
            exit;
        } catch (PDOException $e) {
            $error = "Erreur de mise à jour de la question: " . $e->getMessage();
        }
    }
    
    // Si la soumission POST échoue, mettre à jour les données affichées à partir de $_POST
    if ($error) {
        // ... (Mise à jour des variables $question_data et $current_choices pour l'affichage de l'erreur)
        $question_data['subject_id'] = $subject_id;
        $question_data['question'] = $question;
        $current_choices = $_POST['choices'] ?? []; 
        $question_data['answer_index'] = $answer_index;
        $question_data['explanation'] = $explanation;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifier une Question</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="main-nav">
            <h1>✏️ Modifier la Question </h1>
            <div>
                <?php if ($quiz_id > 0): ?>
                    <a href="manage_questions.php?quiz_id=<?= $quiz_id ?>" class="btn secondary">Retour aux Questions du Quiz</a>
                <?php endif; ?>
                <a href="manage_quizzes.php" class="btn secondary">Retour à la Gestion des Quiz</a>
                <a href="logout.php" class="btn secondary">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container" style="max-width: 800px;">
        
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <?php if ($question_data): ?>
            <form method="POST" action="edit_question.php?id=<?= $question_id ?>&quiz_id=<?= $quiz_id ?>">
                
                <div class="form-group">
                    <label for="subject_id">Sujet (pour classement)</label>
                    <select id="subject_id" name="subject_id">
                        <option value="0">Non classé</option>
                        <?php foreach ($subjects_list as $s): ?>
                            <?php 
                            $selected = ((int)$s['id'] === (int)($question_data['subject_id'] ?? 0)) ? 'selected' : ''; 
                            ?>
                            <option value="<?= $s['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($s['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="question">Question (Obligatoire)</label>
                    <textarea id="question" name="question" rows="4" required><?= htmlspecialchars($question_data['question']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Choix de Réponses (Index 0 à 3)</label>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <label for="choice_<?= $i ?>">Choix <?= $i ?></label>
                        <?php 
                        // Utilise l'index $i pour pré-remplir les 4 champs
                        $choice_value = $current_choices[$i] ?? '';
                        ?>
                        <input type="text" id="choice_<?= $i ?>" name="choices[]" value="<?= htmlspecialchars($choice_value) ?>">
                    <?php endfor; ?>
                </div>

                <div class="form-group">
                    <label for="answer_index">Index de la Réponse Correcte (0, 1, 2, ou 3) (Obligatoire)</label>
                    <input type="number" id="answer_index" name="answer_index" min="0" max="3" required value="<?= htmlspecialchars($question_data['answer_index'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="explanation">Explication de la Réponse (Optionnel)</label>
                    <textarea id="explanation" name="explanation" rows="3"><?= htmlspecialchars($question_data['explanation'] ?? '') ?></textarea>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn primary">Enregistrer les Modifications</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>