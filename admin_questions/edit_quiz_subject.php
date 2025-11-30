// Fichier : edit_quiz_subject.php
<?php
require_once __DIR__ . '/auth.php'; 

$subject_file = __DIR__ . '/quiz_subject.txt';
$message = '';
$error = '';

// Lecture du contenu actuel
$current_subject = file_exists($subject_file) ? file_get_contents($subject_file) : 'Testez vos compétences en Dev Web !';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_subject = trim($_POST['quiz_subject'] ?? '');

    if (empty($new_subject)) {
        $error = "La description du sujet ne peut pas être vide.";
    } else {
        try {
            // Écriture du nouveau sujet dans le fichier
            if (file_put_contents($subject_file, $new_subject) === false) {
                throw new Exception("Impossible d'écrire dans le fichier.");
            }
            $current_subject = $new_subject;
            $message = "Le sujet du quiz a été mis à jour avec succès !";
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifier Sujet Quiz - Admin</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <header class="admin-header">
        <nav class="main-nav">
            <h1>✏️ Modifier le Sujet du Quiz</h1>
            <div>
                <a href="manage_questions.php" class="btn secondary">Retour à la gestion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($message): ?><p class="success-message" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 6px;"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <form method="POST" action="edit_quiz_subject.php">
            <div class="form-group">
                <label for="quiz_subject">Description ou Sujet Principal du Quiz</label>
                <textarea id="quiz_subject" name="quiz_subject" rows="4" required><?= htmlspecialchars($current_subject) ?></textarea>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn primary">Sauvegarder</button>
            </div>
        </form>
    </div>
</body>
</html>