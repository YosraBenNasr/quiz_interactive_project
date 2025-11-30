<?php
// Fichier : manage_subjects.php
require_once __DIR__ . '/auth.php'; 

$message = '';
$error = '';
$editing_subject = null;

// Gestion des messages de statut venant de la soumission de formulaire
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added') {
        $message = "Nouveau sujet ajouté avec succès.";
    } elseif ($_GET['status'] === 'updated') {
        $message = "Sujet modifié avec succès.";
    } elseif ($_GET['status'] === 'deleted') {
        $message = "Sujet supprimé avec succès.";
    } elseif ($_GET['status'] === 'error') {
        $error = htmlspecialchars($_GET['msg'] ?? 'Une erreur est survenue.');
    }
}


// 1. Gestion de la soumission du formulaire (Ajout ou Modification via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);

    if (empty($title)) {
        header('Location: manage_subjects.php?status=error&msg=' . urlencode("Le titre du sujet ne peut pas être vide."));
        exit;
    } 
    
    try {
        if ($subject_id > 0) {
            // MODIFICATION
            $stmt = $pdo->prepare("UPDATE subjects SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $description, $subject_id]);
            header('Location: manage_subjects.php?status=updated');
            exit;
        } else {
            // AJOUT
            $stmt = $pdo->prepare("INSERT INTO subjects (title, description) VALUES (?, ?)");
            $stmt->execute([$title, $description]);
            header('Location: manage_subjects.php?status=added');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: manage_subjects.php?status=error&msg=' . urlencode("Erreur de base de données : " . $e->getMessage()));
        exit;
    }
}

// 2. Gestion de la Suppression (via GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $subject_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        header('Location: manage_subjects.php?status=deleted');
        exit;
    } catch (PDOException $e) {
        header('Location: manage_subjects.php?status=error&msg=' . urlencode("Erreur de suppression. Assurez-vous qu'aucune question n'est liée à ce sujet."));
        exit;
    }
}

// 3. Affichage de la liste des sujets
try {
    $stmt = $pdo->query("
        SELECT s.id, s.title, s.description, COUNT(q.id) as question_count
        FROM subjects s
        LEFT JOIN questions q ON s.id = q.subject_id
        GROUP BY s.id
        ORDER BY s.id DESC
    ");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur de chargement des sujets: " . $e->getMessage();
    $subjects = [];
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des Sujets - Admin</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        /* Styles pour les messages de statut */
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        
        /* Styles pour la Modale */
        .modal {
            display: none; position: fixed; z-index: 10; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888;
            width: 80%; max-width: 600px; border-radius: 8px; position: relative;
        }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="main-nav">
            <h1>📚 Gestion des Sujets du Quiz</h1>
            <div>

         
                <a href="manage_quizzes.php" class="btn secondary">Retour aux Quiz</a>
          
               
            </div>
        </nav>
    </header>

    <div class="container">
        
        <?php if ($message): ?><p class="success-message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <button id="addSubjectBtn" class="btn primary" style="margin-bottom: 20px;">+ Nouveau sujet</button>

        <?php if (empty($subjects)): ?>
            <p>Aucun sujet n'est encore enregistré.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="question-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titre</th>
                            <th>Questions liées</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $s): ?>
                            <tr data-id="<?= htmlspecialchars($s['id']) ?>" data-title="<?= htmlspecialchars($s['title']) ?>" data-description="<?= htmlspecialchars($s['description'] ?? '') ?>">
                                <td><?= htmlspecialchars($s['id']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($s['title']) ?></strong>
                                    <small style="display: block; color: #6c757d;"><?= htmlspecialchars($s['description'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($s['question_count']) ?></td>
                                <td class="actions-cell">
                                    <button class="btn primary edit-btn" data-id="<?= $s['id'] ?>">Modifier</button>
                                    <a href="manage_subjects.php?action=delete&id=<?= $s['id'] ?>" 
                                       onclick="return confirm('ATTENTION: Voulez-vous vraiment supprimer ce sujet ? Cela pourrait affecter les questions liées.')" 
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

    <div id="subjectModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2 id="modalTitle"></h2>
            
            <form id="subjectForm" method="POST" action="manage_subjects.php">
                <input type="hidden" name="subject_id" id="subjectId">
                
                <div class="form-group">
                    <label for="title">Titre du Sujet (Ex: HTML, JavaScript, PHP)</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Description (Optionnel)</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn primary" id="submitButton">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

<script>
    const modal = document.getElementById('subjectModal');
    const form = document.getElementById('subjectForm');
    const closeBtn = document.querySelector('.close-btn');
    const addSubjectBtn = document.getElementById('addSubjectBtn');
    const editBtns = document.querySelectorAll('.edit-btn');
    
    // Champs de la modale
    const modalTitle = document.getElementById('modalTitle');
    const subjectId = document.getElementById('subjectId');
    const titleInput = document.getElementById('title');
    const descriptionInput = document.getElementById('description');
    const submitButton = document.getElementById('submitButton');

    // Fonction pour fermer la modale
    function closeModal() {
        modal.style.display = 'none';
    }

    // Gestion du clic sur le bouton d'ajout
    addSubjectBtn.onclick = function() {
        modalTitle.textContent = 'Ajouter un nouveau sujet';
        submitButton.textContent = 'Ajouter';
        subjectId.value = 0;
        titleInput.value = '';
        descriptionInput.value = '';
        modal.style.display = 'block';
    }

    // Gestion du clic sur les boutons d'édition
    editBtns.forEach(button => {
        button.onclick = function() {
            const id = this.getAttribute('data-id');
            // Trouver la ligne du tableau correspondante pour récupérer les données
            const row = document.querySelector(`tr[data-id="${id}"]`);
            
            modalTitle.textContent = 'Modifier le sujet: ' + row.getAttribute('data-title');
            submitButton.textContent = 'Modifier';
            subjectId.value = id;
            titleInput.value = row.getAttribute('data-title');
            descriptionInput.value = row.getAttribute('data-description');
            
            modal.style.display = 'block';
        }
    });

    // Fermeture par le bouton X
    closeBtn.onclick = closeModal;

    // Fermeture en cliquant en dehors
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
</script>
</body>
</html>