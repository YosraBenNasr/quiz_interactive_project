<?php
// Fichier : index.php (Page d'accueil de l'étudiant)
// Assurez-vous que config.php inclut la connexion $pdo et démarre la session si nécessaire
require_once __DIR__ . '/config.php'; 

$published_quizzes = [];
$error_message = '';

try {
    // Récupérer uniquement les Quiz qui sont au statut 'published'
    $stmt = $pdo->query("
        SELECT 
            q.id, q.title,
            (SELECT COUNT(qq.question_id) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
        FROM quizzes q
        WHERE q.status = 'published'
        ORDER BY q.title ASC
    ");
    $published_quizzes = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Erreur de chargement des quiz : " . $e->getMessage();
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quiz Interactif - quiz_project</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 18px;
    box-shadow: 0 6px 18px rgba(16, 32, 39, 0.06);
    margin-bottom: 18px;
    /* Hauteur fixe (HACK pour éviter le saut de page lors du chargement des questions) */
    height: auto; 
    display: flex;
    flex-direction: column;
}
        /* Liste des quiz */
        .quiz-list-selector {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .quiz-list-selector .quiz-selectable {
            cursor: pointer;
            transition: background-color 0.2s;
            background: var(--bg-light);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: var(--border-radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd;
        }

        .quiz-list-selector .quiz-selectable:hover {
            background-color: var(--bg-hover, #f5f5f5);
        }

        .quiz-list-selector .quiz-details {
            display: flex;
            flex-direction: column;
        }

        .quiz-list-selector .quiz-details strong {
            font-size: 1.1em;
            color: var(--text-dark);
        }

        /* Meilleurs scores */
        .best-scores {
            margin-top: 0;
            padding-top: 20px;
            text-align: center;
        }

        #best-scores-list {
            list-style: none;
            padding: 0;
            margin: 15px 0 20px;
            text-align: left;
            font-size: 1.1em;
        }

        #best-scores-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            border-bottom: 1px dashed #eee;
            background-color: #f9f9f9;
        }

        #best-scores-list li:nth-child(even) {
            background-color: #fff;
        }

        #best-scores-list li:last-child {
            border-bottom: none;
        }

        .best-scores h3 {
            color: var(--text-dark, #2c3e50);
            margin-bottom: 10px;
            font-size: 1.4em;
            text-align: center;
        }

        .best-scores .rank {
            font-weight: bold;
            width: 30px;
            flex-shrink: 0;
        }

        .best-scores .name {
            flex-grow: 1;
            margin-left: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .best-scores .score {
            font-weight: bold;
            color: var(--accent, #2b8aef);
            flex-shrink: 0;
            text-align: right;
        }

        #clear-scores {
            margin-top: 10px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-overlay.hidden {
            display: none;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .modal-actions {
            display: flex;
            justify-content: space-around;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <header class="site-header">
        <h1>Testez vos compétences en Dev Web !</h1>
        <p class="subtitle">Feedback immédiat • Chrono par question</p>
    </header>

    <main class="container" id="app">

        <!-- Écran de sélection de quiz -->
        <section id="start-screen" class="card" aria-labelledby="start-title">
            <div class="start-row">
                <h2 id="start-title">Sélectionnez votre Quiz</h2>
            </div>

            <?php if ($error_message): ?>
                <p class="error-message" style="color: #dc3545;"><?= htmlspecialchars($error_message) ?></p>
            <?php elseif (empty($published_quizzes)): ?>
                <p>Aucun quiz n'est actuellement disponible. Veuillez revenir plus tard !</p>
            <?php else: ?>
                <ul class="quiz-list-selector">
                    <?php foreach ($published_quizzes as $quiz): ?>
                        <li class="quiz-selectable"
                            data-quiz-id="<?= $quiz['id'] ?>"
                            data-total-questions="<?= $quiz['question_count'] ?>"
                            data-quiz-title="<?= htmlspecialchars($quiz['title']) ?>"
                            data-disabled="<?= ($quiz['question_count'] == 0) ? 'true' : 'false' ?>">
                            <div class="quiz-details">
                                <strong><?= htmlspecialchars($quiz['title']) ?></strong>
                                <span><?= $quiz['question_count'] ?> questions</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <aside class="best-scores small" aria-live="polite">
                <h3>🏆 Meilleurs scores</h3>
                <ol id="best-scores-list"></ol>
                <button id="clear-scores" class="btn secondary ghost small" type="button">Effacer les scores</button>
            </aside>
        </section>

        <!-- Écran de quiz -->
        <section id="quiz-screen" class="card hidden" aria-live="polite" aria-labelledby="question-title">
            <div class="top-row">
                <p class="progress">Question <span id="q-index">0</span> / <span id="q-total">0</span></p>
                <div class="timer">
                    <svg class="ring-timer" viewBox="0 0 104 104" width="30" height="30" aria-hidden="true">
                        <circle class="ring-bg" cx="52" cy="52" r="50" />
                        <circle class="ring-fg" cx="52" cy="52" r="50" fill="none" stroke-width="4" stroke="var(--accent)" />
                    </svg>
                    <span id="time-display" aria-live="polite">10s</span>
                </div>
            </div>

            <div class="progress-container">
                <span class="progress-label" id="progressLabel">0%</span>
                <div class="progress-bar-outer">
                    <div id="progressBar" class="progress-bar"></div>
                </div>
            </div>

            <article id="question-card" class="question-card fade show" role="group" aria-labelledby="question-title">
                <h3 id="question-title">Question en attente...</h3>
                <div id="choices" class="choices" role="radiogroup" aria-label="Choix de réponses"></div>
            </article>

            <div class="controls">
                <button id="quit-btn" class="btn secondary ghost" type="button">Quitter</button>
                <button id="next-btn" class="btn primary" type="button" disabled>Suivant</button>
            </div>
        </section>

        <!-- Écran des résultats -->
        <section id="result-screen" class="hidden" aria-live="polite" aria-labelledby="result-title">
            <h2 id="result-title">🎉 Résultats</h2>
            <p id="final-score" class="score-summary"></p>

            <div id="progress-bar-result" class="progress-bar-result" role="progressbar" aria-valuemin="0"
                 aria-valuemax="100" aria-valuenow="0">
                <span></span>
            </div>

            <div id="result-details" class="result-details"></div>

            <div class="controls">
                <button id="retry-btn" class="btn primary" type="button">Rejouer</button>
                <button id="save-score" class="btn secondary" type="button">Sauvegarder mon score</button>
                <a id="download-report" class="btn secondary ghost" href="#" download="rapport_quiz.txt">Télécharger rapport</a>
            </div>
        </section>

    </main>

    <!-- Modal de confirmation -->
    <div id="confirmation-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h4>Êtes-vous sûr de vouloir commencer ce quiz ?</h4>
            <p>Vous êtes sur le point de démarrer le quiz : <strong><span id="quiz-title-placeholder"></span></strong>.</p>
            <p>Il contient <strong id="question-count-placeholder"></strong> questions.</p>
            <div class="modal-actions">
                <button id="modal-cancel-btn" class="btn secondary" type="button">Annuler</button>
                <button id="modal-confirm-btn" class="btn primary" type="button">Oui, Démarrer</button>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <p>Projet Web — quiz_project • FIA3 • 2025/2026</p>
    </footer>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>

</html>
