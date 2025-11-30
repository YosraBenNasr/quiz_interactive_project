<?php
// Fichier : manage_quizzes.php (Gestion complète des Quiz - FINAL)
require_once __DIR__ . '/auth.php'; 

// Initialisation des variables pour éviter les erreurs "Undefined variable"
$message = '';
$error = '';
$subjects_list = [];




// 0. Récupérer la liste des sujets pour la modale de création
try {
    // Supposons que $pdo est défini dans auth.php ou config.php
    $subjects_stmt = $pdo->query("SELECT id, title FROM subjects ORDER BY title ASC");
    $subjects_list = $subjects_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur de chargement des sujets: " . $e->getMessage();
    $subjects_list = [];
}


// --- 1. Gestion des actions POST (Création d'un Quiz et de ses liaisons) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['quiz_title'] ?? '');
    // Récupère le tableau de sujets sélectionnés par checkboxes
    $selected_subject_ids = $_POST['quiz_subject_ids'] ?? []; 

    if (empty($title) || empty($selected_subject_ids)) {
        // Ajout d'une vérification plus spécifique en amont pour éviter l'erreur "ID de Quiz manquant."
        header('Location: manage_quizzes.php?status=error&msg=' . urlencode("Le titre et au moins un sujet sont obligatoires."));
        exit;
    } 
    
    try {
        $pdo->beginTransaction();

        // 1. Créer le Quiz
        $stmt = $pdo->prepare("INSERT INTO quizzes (title, status) VALUES (?, 'draft')");
        $stmt->execute([$title]);
        $new_quiz_id = $pdo->lastInsertId();

        // 2. Créer les liaisons dans quiz_subjects (table pivot)
        $quiz_subjects_sql = "INSERT INTO quiz_subjects (quiz_id, subject_id) VALUES (?, ?)";
        $quiz_subjects_stmt = $pdo->prepare($quiz_subjects_sql);

        foreach ($selected_subject_ids as $subject_id) {
            $quiz_subjects_stmt->execute([$new_quiz_id, (int)$subject_id]);
        }
        
        $pdo->commit();

        header('Location: manage_quizzes.php?status=created');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: manage_quizzes.php?status=error&msg=' . urlencode("Erreur lors de la création du quiz et des liaisons: " . $e->getMessage()));
        exit;
    }
}


// Gestion de la PUBLICATION/DRAFT/SUPPRESSION
if (isset($_GET['action']) && isset($_GET['id'])) {
    $quiz_id = (int)$_GET['id'];
    
    if ($_GET['action'] === 'publish' || $_GET['action'] === 'draft') {
        $new_status = ($_GET['action'] === 'publish') ? 'published' : 'draft';
        $status_msg = ($new_status === 'published') ? 'published' : 'drafted';
        
        try {
            $stmt = $pdo->prepare("UPDATE quizzes SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $quiz_id]);
            header('Location: manage_quizzes.php?status=' . $status_msg);
            exit;
        } catch (PDOException $e) { $error = "Erreur de statut: " . $e->getMessage(); }
    
    // LOGIQUE DE SUPPRESSION AVEC NETTOYAGE DES QUESTIONS ORPHELINES
    } elseif ($_GET['action'] === 'delete') {
        try {
            $pdo->beginTransaction();

            // 1. Récupérer les ID des questions liées au quiz que nous allons supprimer
            $questions_to_check_stmt = $pdo->prepare("SELECT question_id FROM quiz_questions WHERE quiz_id = ?");
            $questions_to_check_stmt->execute([$quiz_id]);
            $question_ids_to_check = $questions_to_check_stmt->fetchAll(PDO::FETCH_COLUMN);

            // 2. Suppression du Quiz (supprime automatiquement les liaisons dans quiz_questions grâce à ON DELETE CASCADE)
            $delete_quiz_stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
            $delete_quiz_stmt->execute([$quiz_id]);
            
            // 3. Vérifier et supprimer les questions devenues orphelines
            if (!empty($question_ids_to_check)) {
                
                $placeholders = implode(',', array_fill(0, count($question_ids_to_check), '?'));
                
                // Supprimer les questions dont l'ID est dans notre liste et qui N'ONT PLUS de liaison
                $delete_orphans_stmt = $pdo->prepare("
                    DELETE FROM questions 
                    WHERE id IN ($placeholders) 
                    AND id NOT IN (SELECT question_id FROM quiz_questions)
                ");
                $delete_orphans_stmt->execute($question_ids_to_check);
            }

            $pdo->commit();
            header('Location: manage_quizzes.php?status=deleted_quiz');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur de suppression du quiz et des questions liées: " . $e->getMessage();
        }
    }
}


// --- 3. Affichage de la liste des quiz ---
try {
    // 1. Récupérer les Quiz et compter les questions via la table quiz_questions
    $stmt = $pdo->query("
        SELECT 
            q.id, q.title, q.status,
            (SELECT COUNT(qq.question_id) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
        FROM quizzes q
        ORDER BY q.id DESC
    ");
    $quizzes = $stmt->fetchAll();
    
    // 2. Récupérer les sujets associés à chaque quiz pour l'affichage (via quiz_subjects)
    $quiz_subjects_map = [];
    if (!empty($quizzes)) {
        $quiz_ids = array_column($quizzes, 'id');
        // Utilisation de la fonction pour garantir que les IDs sont des entiers pour la requête SQL
        $in_clause = implode(',', array_map('intval', $quiz_ids)); 

        $subjects_stmt = $pdo->query("
            SELECT qs.quiz_id, s.title AS subject_title
            FROM quiz_subjects qs
            JOIN subjects s ON qs.subject_id = s.id
            WHERE qs.quiz_id IN ($in_clause)
            ORDER BY s.title ASC
        ");
        $all_quiz_subjects = $subjects_stmt->fetchAll();

        foreach ($all_quiz_subjects as $row) {
            $quiz_subjects_map[$row['quiz_id']][] = $row['subject_title'];
        }
    }

} catch (PDOException $e) {
    $error = "Erreur de chargement des quiz: " . $e->getMessage();
    $quizzes = [];
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des Quiz</title>
    <link rel="stylesheet" href="admin_style.css"> 
    <style>
        /* Styles de base pour les messages et la modale */
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        
        /* Modale */
        .modal { display: none; position: fixed; z-index: 10; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; cursor: pointer; }
        
        /* Tableau */
        .question-table { width: 100%; border-collapse: collapse; }
        .question-table th, .question-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
        
        /* Actions/Boutons */
        .actions-cell a { margin-right: 5px; margin-bottom: 5px; display: inline-block; }
        
        /* FIX D'ALIGNEMENT DES CHECKBOXES DANS LA MODALE */
        .subject-checkbox-list input[type="checkbox"] { margin-right: 5px; }

        .checkbox-item {
            margin-bottom: 5px;
            display: flex; /* Utilise Flexbox */
            align-items: center; /* Centre verticalement le contenu */
        }
        
        .checkbox-item label {
            /* Retirer les styles inline et les appliquer ici pour la propreté */
            font-weight: normal; 
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="main-nav">
            <h1>📝 Gestion des Quiz</h1>
            <div>
                <a href="manage_subjects.php" class="btn secondary" style="margin-right: 10px;">Sujets du quiz</a>
                <a href="logout.php" class="btn secondary">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        
        <?php if ($message): ?><p class="success-message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <button id="addQuizBtn" class="btn primary" style="margin-bottom: 20px;">+ Nouveau Quiz</button>

        <?php if (empty($quizzes)): ?>
            <p>Aucun quiz n'est encore créé. Cliquez sur "+ Créer un nouveau Quiz" pour commencer.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="question-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titre du Quiz</th>
                            <th>Sujets Liés</th>
                            <th>Questions</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $q): ?>
                            <tr>
                                <td><?= htmlspecialchars($q['id']) ?></td>
                                <td><strong><?= htmlspecialchars($q['title']) ?></strong></td>
                                <td>
                                    <?php 
                                    // Affiche les sujets séparés par des virgules
                                    if (isset($quiz_subjects_map[$q['id']])) {
                                        echo implode(', ', $quiz_subjects_map[$q['id']]);
                                    } else {
                                        echo 'Aucun sujet lié';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($q['question_count']) ?></td>
                                <td>
                                    <span style="color: <?= $q['status'] === 'published' ? 'green' : 'orange' ?>; font-weight: bold;">
                                        <?= $q['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <a href="manage_questions.php?quiz_id=<?= $q['id'] ?>" class="btn primary" style="background-color: #28a745;">
                                         Questions
                                    </a>
                                    
                                    <?php if ($q['status'] === 'draft'): ?>
                                        <a href="manage_quizzes.php?action=publish&id=<?= $q['id'] ?>" class="btn primary">Publier</a>
                                    <?php else: ?>
                                        <a href="manage_quizzes.php?action=draft&id=<?= $q['id'] ?>" class="btn secondary" style="background-color: #3473b6ff;color:white;">Désactiver</a>
                                    <?php endif; ?>
                                    
                                    <a href="manage_quizzes.php?action=delete&id=<?= $q['id'] ?>" 
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer COMPLÈTEMENT ce Quiz (ID: <?= $q['id'] ?>) ? Ceci supprimera le quiz, toutes ses liaisons, ET toutes les questions qui ne sont plus utilisées par aucun autre quiz.')" 
                                        class="btn danger">
                                        Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="createQuizModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeCreateBtn">&times;</span>
            <h2>Nouveau Quiz</h2> 
            
            <form method="POST" action="manage_quizzes.php">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="quiz_title">Titre du Quiz</label>
                    <input type="text" id="quiz_title" name="quiz_title" required>
                </div>

                <div class="form-group">
                    <label>Sélectionner un ou plusieurs Sujets (Obligatoire)</label>
                    <div class="subject-checkbox-list" style="border: 1px solid #ccc; padding: 10px; border-radius: 4px; max-height: 150px; overflow-y: auto;">
                        <?php if (empty($subjects_list)): ?>
                            <p style="color: red;">⚠️ <a href="manage_subjects.php">Créez des sujets</a> avant de créer un quiz.</p>
                        <?php else: ?>
                            <?php foreach ($subjects_list as $s): ?>
                                <div class="checkbox-item">
                                    <input 
                                        type="checkbox" 
                                        id="subject_<?= $s['id'] ?>" 
                                        name="quiz_subject_ids[]" 
                                        value="<?= $s['id'] ?>"
                                        class="subject-checkbox"
                                    >
                                    <label for="subject_<?= $s['id'] ?>">
                                        <?= htmlspecialchars($s['title']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn primary">Créer le Quiz</button>
                </div>
            </form>
        </div>
    </div>

<script>
    // Script pour afficher/cacher la modale et la validation
    const createModal = document.getElementById('createQuizModal');
    const createBtn = document.getElementById('addQuizBtn');
    const closeCreateBtn = document.getElementById('closeCreateBtn');
    const createQuizForm = createModal.querySelector('form'); 

    createBtn.onclick = function() { createModal.style.display = 'block'; }
    closeCreateBtn.onclick = function() { createModal.style.display = 'none'; }
    
    window.onclick = function(event) {
        if (event.target == createModal) {
            createModal.style.display = 'none';
        }
    }

    // LOGIQUE DE VALIDATION POUR LES CASES À COCHER (au moins un sujet doit être coché)
    createQuizForm.addEventListener('submit', function(e) {
        const checkboxes = createQuizForm.querySelectorAll('.subject-checkbox');
        let checkedCount = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                checkedCount++;
            }
        });

        if (checkedCount === 0) {
            e.preventDefault();
            alert("Veuillez sélectionner au moins un sujet pour créer le Quiz.");
        }
    });
</script>
</body>
</html>