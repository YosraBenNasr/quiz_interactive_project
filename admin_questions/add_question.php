<?php
// Fichier : add_question.php (CORRECTION FINALE : Restriction des Sujets Affichés)
require_once __DIR__ . '/auth.php'; 

$quiz_id = (int)($_GET['quiz_id'] ?? 0);

$error = '';
$subjects_list = [];
$default_subject_id = 0; // Défaut initialisé à 0

// -----------------------------------------------------------
// CORRECTION: Afficher UNIQUEMENT les sujets liés au quiz
// -----------------------------------------------------------
try {
    if ($quiz_id > 0) {
        // 1. Récupérer UNIQUEMENT les sujets LIÉS à ce quiz via la table quiz_subjects
        $subjects_stmt = $pdo->prepare("
            SELECT s.id, s.title 
            FROM subjects s
            JOIN quiz_subjects qs ON s.id = qs.subject_id 
            WHERE qs.quiz_id = ?
            ORDER BY s.title ASC
        ");
        $subjects_stmt->execute([$quiz_id]);
        $subjects_list = $subjects_stmt->fetchAll();

        // 2. Déterminer le sujet par défaut (le premier sujet lié si un seul est lié)
        if (count($subjects_list) === 1) {
            $default_subject_id = (int)$subjects_list[0]['id'];
        }
        
    } else {
        // Si l'ID du quiz est manquant, on affiche tous les sujets (Comportement de secours)
        $subjects_stmt = $pdo->query("SELECT id, title FROM subjects ORDER BY title ASC");
        $subjects_list = $subjects_stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "Erreur de chargement des sujets: " . $e->getMessage();
}


// --- LOGIQUE DE TRAITEMENT DU FORMULAIRE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Si l'option "Non classé" (value=0) a été retirée, mais qu'aucun sujet n'est sélectionné dans le POST 
    // et qu'il y a des sujets disponibles, on peut forcer la sélection du premier sujet lié 
    // ou laisser la validation échouer si subject_id n'est pas envoyé.
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    
    $question = trim($_POST['question'] ?? '');
    // Filtrer les choix vides
    $choices = array_filter($_POST['choices'] ?? [], function($choice) {
        return !empty(trim($choice));
    }); 
    $answer_index = (int)($_POST['answer_index'] ?? -1);
    $explanation = trim($_POST['explanation'] ?? '');

    // Validation
    // Si "Non classé" est supprimé, il faut aussi vérifier que subject_id > 0 si la liste n'est pas vide.
    if (empty($subjects_list) && $quiz_id > 0) {
         $error = "Ce quiz n'est lié à aucun sujet, impossible d'ajouter une question classée.";
    } elseif (empty($question) || empty($choices) || $answer_index < 0 || $answer_index >= count($choices)) {
        $error = "Veuillez remplir la question, fournir des choix et indiquer l'index de la réponse correcte.";
    } elseif ($subject_id <= 0 && !empty($subjects_list)) { 
        // Cette vérification est nécessaire si l'utilisateur a pu désélectionner l'option par défaut
        // et qu'on exige une classification. Le HTML ci-dessous force une sélection.
        $error = "Veuillez sélectionner un sujet pour la question.";
    } else {
        try {
            // Démarrer une transaction pour garantir les deux insertions
            $pdo->beginTransaction();

            // S'assurer que les choix sont réindexés pour éviter des clés non consécutives dans le JSON
            $choices_json = json_encode(array_values($choices)); 
            
            // 1. Insertion dans la table questions
            $stmt = $pdo->prepare("
                INSERT INTO questions (subject_id, question, choices, answer_index, explanation) 
                VALUES (?, ?, ?, ?, ?)
            ");
            // Utiliser $subject_id (qui est > 0 si la validation a réussi)
            $stmt->execute([$subject_id, $question, $choices_json, $answer_index, $explanation]);
            $new_question_id = $pdo->lastInsertId(); // Récupère l'ID de la nouvelle question

            // 2. Insertion dans la table quiz_questions (LIAISON)
            if ($new_question_id > 0 && $quiz_id > 0) {
                $link_stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_id) VALUES (?, ?)");
                $link_stmt->execute([$quiz_id, $new_question_id]);
            }
            
            $pdo->commit();
            
            // Redirection vers la page de gestion des questions du quiz
            if ($quiz_id > 0) {
                header("Location: manage_questions.php?quiz_id=$quiz_id&status=added");
            } else {
                header('Location: manage_quizzes.php?status=added');
            }
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur d'ajout et de liaison de la question: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajouter une Nouvelle Question</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="main-nav">
            <h1>➕ Ajouter une Question</h1>
            <div>
                <?php if ($quiz_id > 0): ?>
                    <a href="manage_questions.php?quiz_id=<?= $quiz_id ?>" class="btn secondary">Retour au Quiz</a>
                <?php else: ?>
                    <a href="manage_quizzes.php" class="btn secondary">Retour à la Gestion des Quiz</a>
                <?php endif; ?>
                <a href="logout.php" class="btn secondary">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container" style="max-width: 800px;">
        
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="POST" action="add_question.php?quiz_id=<?= $quiz_id ?>">
            
            <div class="form-group">
                <label for="subject_id">Sujet (pour classement)</label>
                <select id="subject_id" name="subject_id" <?= !empty($subjects_list) ? 'required' : 'disabled' ?>>
                    <?php $selected_subject_id = $_POST['subject_id'] ?? $default_subject_id; ?>
                    
                    <?php if (empty($subjects_list)): ?>
                        <option value="">-- Aucun sujet lié au quiz --</option>
                    <?php endif; ?>

                    <?php foreach ($subjects_list as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ((int)$s['id'] === (int)$selected_subject_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($subjects_list) && $quiz_id > 0): ?>
                    <p style="color: red; margin-top: 5px;">Attention : Aucun sujet n'est lié à ce quiz. Veuillez modifier le quiz pour ajouter un sujet.</p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="question">Question (Obligatoire)</label>
                <textarea id="question" name="question" rows="4" required><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Choix de Réponses (Index 0 à 3)</label>
                <?php $choices = $_POST['choices'] ?? ['', '', '', '']; ?>
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <label for="choice_<?= $i ?>">Choix <?= $i ?></label>
                    <input type="text" id="choice_<?= $i ?>" name="choices[]" value="<?= htmlspecialchars($choices[$i] ?? '') ?>">
                <?php endfor; ?>
            </div>

            <div class="form-group">
                <label for="answer_index">Index de la Réponse Correcte (0, 1, 2, ou 3) (Obligatoire)</label>
                <input type="number" id="answer_index" name="answer_index" min="0" max="3" required value="<?= htmlspecialchars($_POST['answer_index'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="explanation">Explication de la Réponse (Optionnel)</label>
                <textarea id="explanation" name="explanation" rows="3"><?= htmlspecialchars($_POST['explanation'] ?? '') ?></textarea>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn primary">Enregistrer la Question</button>
            </div>
        </form>
    </div>
</body>
</html>