<?php
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Vérification des identifiants par comparaison directe
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {

        $_SESSION['admin_logged_in'] = true;

        // Redirection vers manage_quizzes.php
        header('Location: manage_quizzes.php');
        exit;

    } else {
        $error = "Nom d'utilisateur ou mot de passe incorrect.";
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion Admin</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>Administration Quiz</h2>
        
        <?php if ($error): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn primary">Se connecter</button>
        </form>
    </div>
</body>
</html>
