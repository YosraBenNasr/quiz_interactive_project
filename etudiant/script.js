// Fichier : script.js
// Version complète intégrant : ring timer SVG, sélection de quiz (quiz_id), 
// modal de confirmation (déclenché par le clic sur la zone entière), gestion du quiz et classement.

/* =========================
   CONFIG & CONST
   ========================= */
const LOCAL_STORAGE_KEY = 'bestQuizScore';
const TIME_PER_QUESTION = 10; // secondes
const PENALTY_PER_SEC = 0.05; // pour envoi serveur

// Ring timer constants — selon ton SVG (r = 50)
const RING_RADIUS = 50;
const CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

/* =========================
   STATE
   ========================= */
let QUESTIONS = [];
let QUIZ_ID = null;
let state = { current: 0, answers: [] }; // score final est calculé côté serveur
let timer = null;
let timeLeft = 0;
let tempQuizId = null; // Pour stocker l'ID entre le clic et la confirmation du modal
let currentQuizId = null;
/* =========================
   REFS (DOM)
   ========================= */
const refs = {
    startScreen: document.getElementById("start-screen"),
    quizScreen: document.getElementById("quiz-screen"),
    resultScreen: document.getElementById("result-screen"),
    qIndexEl: document.getElementById("q-index"),
    qTotalEl: document.getElementById("q-total"),
    qTitle: document.getElementById("question-title"),
    choicesEl: document.getElementById("choices"),
    nextBtn: document.getElementById("next-btn"),
    quitBtn: document.getElementById("quit-btn"),
    retryBtn: document.getElementById("retry-btn"),
    finalScore: document.getElementById("final-score"),
    resultDetails: document.getElementById("result-details"),
    downloadReport: document.getElementById("download-report"),
    progressBar: document.getElementById("progressBar"),
    progressLabel: document.getElementById("progressLabel"),
    timeDisplay: document.getElementById("time-display"),
    ringFg: document.querySelector('.ring-fg'),
    bestScoresList: document.getElementById("best-scores-list"),
    saveScoreBtn: document.getElementById("save-score"),
    clearScoresBtn: document.getElementById("clear-scores"),
    bestLocalScore: document.getElementById("best-local-score"),
    
    // RÉFÉRENCES DU MODAL :
    modalOverlay: document.getElementById("confirmation-modal"),
    quizTitlePlaceholder: document.getElementById("quiz-title-placeholder"),
    questionCountPlaceholder: document.getElementById("question-count-placeholder"),
    modalConfirmBtn: document.getElementById("modal-confirm-btn"),
    modalCancelBtn: document.getElementById("modal-cancel-btn"),
};

/* =========================
   UTILITAIRES
   ========================= */
function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/* =========================
   RING TIMER SETUP
   ========================= */
function initRingTimer() {
    if (!refs.ringFg) return;
    refs.ringFg.style.strokeDasharray = `${CIRCUMFERENCE}px`;
    refs.ringFg.style.strokeDashoffset = '0px';
    refs.ringFg.style.transition = 'stroke-dashoffset 0.2s linear, stroke 0.2s linear';
}

function updateRingTimer() {
    if (!refs.ringFg) return;
    const timeElapsed = TIME_PER_QUESTION - timeLeft;
    const progress = Math.max(0, Math.min(1, timeElapsed / TIME_PER_QUESTION)); 
    const offset = progress * CIRCUMFERENCE;
    refs.ringFg.style.strokeDashoffset = `${offset}px`;

    if (timeLeft <= 3) {
        refs.ringFg.style.stroke = 'var(--danger, #e74c3c)';
    } else {
        refs.ringFg.style.stroke = 'var(--accent, #2b8aef)';
    }
}

/* =========================
   TIMER (start/stop/expire)
   ========================= */
function startTimer() {
    stopTimer();
    timeLeft = TIME_PER_QUESTION;
    updateTimeUI();

    timer = setInterval(() => {
        timeLeft--;
        if (timeLeft < 0) timeLeft = 0;
        updateTimeUI();
        if (timeLeft <= 0) {
            stopTimer();
            timeExpired();
        }
    }, 1000);
}

function stopTimer() {
    if (timer) clearInterval(timer);
    timer = null;
}

function updateTimeUI() {
    if (refs.timeDisplay) refs.timeDisplay.textContent = `${Math.ceil(timeLeft)}s`;
    updateRingTimer();
}

function timeExpired() {
    document.querySelectorAll('.choice').forEach(b => {
        b.disabled = true;
        b.style.pointerEvents = 'none';
    });

    const qid = QUESTIONS[state.current].id;
    state.answers.push({
        question_id: qid,
        chosen: null,
        timeRemaining: 0,
        isCorrect: false
    });

    if (refs.nextBtn) refs.nextBtn.disabled = false;
}

/* =========================
   CHARGEMENT DES QUESTIONS (par quiz_id)
   ========================= */
async function loadQuestionsForQuiz(quizId) {
    try {
        const res = await fetch(`load_quiz_questions.php?quiz_id=${encodeURIComponent(quizId)}`, { cache: "no-store" });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error || !Array.isArray(data.questions)) {
            console.error('Erreur data questions:', data);
            alert('Erreur serveur lors du chargement des questions.');
            return null;
        }

        const loaded = data.questions.map(q => ({
            id: q.id,
            q: q.q,
            choices: q.choices || [],
            explanation: q.explanation || 'Explication non disponible.'
        }));

        return shuffle(loaded);
    } catch (e) {
        console.error('Erreur loadQuestionsForQuiz:', e);
        alert('Impossible de charger les questions. Voir console.');
        return null;
    }
}

/* =========================
   RENDER / NAVIGATION
   ========================= */
function startQuiz(questions, quizId = null) {
    if (!questions || questions.length === 0) {
        alert('Aucune question chargée pour ce quiz.');
        return;
    }
    currentQuizId = quizId;
    QUESTIONS = questions;
    QUIZ_ID = quizId;
    state = { current: 0, answers: [] };

    if (refs.startScreen) refs.startScreen.classList.add('hidden');
    if (refs.resultScreen) refs.resultScreen.classList.add('hidden');
    if (refs.quizScreen) refs.quizScreen.classList.remove('hidden');

    if (refs.qTotalEl) refs.qTotalEl.textContent = QUESTIONS.length;
    updateProgressUI();
    renderQuestion();
}

function renderQuestion() {
    const q = QUESTIONS[state.current];
    if (!q) return console.warn('Question introuvable', state.current);

    if (refs.qIndexEl) refs.qIndexEl.textContent = state.current + 1;
    if (refs.qTitle) refs.qTitle.textContent = q.q;
    if (refs.choicesEl) refs.choicesEl.innerHTML = '';

    if (refs.nextBtn) refs.nextBtn.disabled = true;

    q.choices.forEach((text, idx) => {
        const btn = document.createElement('button');
        btn.className = 'choice btn-plain';
        btn.dataset.index = idx;
        btn.innerHTML = `<span class="label">${escapeHtml(text)}</span>`;
        btn.type = 'button';
        btn.onclick = () => selectChoice(btn, idx);
        refs.choicesEl.appendChild(btn);
    });

    if (refs.ringFg) refs.ringFg.style.strokeDashoffset = '0px';

    startTimer();
}

async function selectChoice(btn, chosenIndex) {
    stopTimer();
    
    document.querySelectorAll('.choice').forEach(b => {
        b.disabled = true;
        b.style.pointerEvents = 'none';
    });

    const q = QUESTIONS[state.current];
    let isCorrect = false;
    let correctIdx = null;

    try {
        const payload = {
            question_id: q.id,
            chosen_index: chosenIndex
        };
        const res = await fetch('check_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error || !data.success) {
            console.error('Erreur vérification:', data);
        } else {
            isCorrect = !!data.isCorrect;
            correctIdx = data.correctIndex;

            const correctBtn = document.querySelector(`.choice[data-index="${correctIdx}"]`);
            if (correctBtn) correctBtn.classList.add('correct');

            if (!isCorrect) {
                btn.classList.add('wrong');
            } else {
                btn.classList.add('chosen');
            }
        }
    } catch (e) {
        console.error('Erreur selectChoice:', e);
    }

    state.answers.push({
        question_id: q.id,
        chosen: chosenIndex,
        timeRemaining: timeLeft,
        isCorrect: isCorrect
    });

    if (refs.nextBtn) refs.nextBtn.disabled = false;
}

async function gotoNext() {
    if (refs.nextBtn) refs.nextBtn.disabled = true;
    state.current++;

    if (state.current >= QUESTIONS.length) {
        await submitToServer();
        return;
    }

    if (refs.choicesEl) {
        refs.choicesEl.classList.add('fade-out');
        setTimeout(() => {
            updateProgressUI();
            renderQuestion();
            refs.choicesEl.classList.remove('fade-out');
            refs.choicesEl.classList.add('fade-in');
            setTimeout(() => refs.choicesEl.classList.remove('fade-in'), 400);
        }, 300);
    } else {
        updateProgressUI();
        renderQuestion();
    }
}

function updateProgressUI() {
    const pct = QUESTIONS.length ? Math.round((state.current / QUESTIONS.length) * 100) : 0;
    if (refs.progressBar) refs.progressBar.style.width = pct + '%';
    if (refs.progressLabel) refs.progressLabel.textContent = pct + '%';
}

/* =========================
   SUBMIT & RESULTS
   ========================= */
async function submitToServer() {
    stopTimer();
    if (refs.quizScreen) refs.quizScreen.classList.add('hidden');

    try {
        const payload = { answers: state.answers, timePerQuestion: TIME_PER_QUESTION, penaltyPerSec: PENALTY_PER_SEC, quiz_id: QUIZ_ID };
        const res = await fetch('submit_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        showResultsFromServer(data);
    } catch (e) {
        console.error('Erreur validation:', e);
        alert('Erreur serveur lors de la validation du quiz. Voir console.');
        resetToStart();
    }
}

function showResultsFromServer(data) {
    if (refs.resultScreen) refs.resultScreen.classList.remove('hidden');
    if (refs.resultDetails) refs.resultDetails.innerHTML = '';

    const scoreValue = +(data.score).toFixed(2);
    if (refs.finalScore) refs.finalScore.textContent = `${scoreValue.toFixed(2)} / ${data.totalQuestions}`;

    // progress bar result
    const percent = Math.round((scoreValue / data.totalQuestions) * 100);
    const resultBarSpan = document.querySelector('#progress-bar-result > span');
    if (resultBarSpan) resultBarSpan.style.width = percent + '%';

    // Détails
    (data.details || []).forEach((ans, i) => {
        const q = QUESTIONS.find(qx => qx.id === ans.question_id) || { q: 'Question inconnue', choices: [], explanation: 'N/A' };
        const chosenText = ans.chosen === null ? 'Temps écoulé' : (q.choices[ans.chosen] || '—');

        const div = document.createElement('div');
        div.className = 'result-item ' + (ans.isCorrect ? 'correct' : 'wrong');

        div.innerHTML = `
            <p class="question-title-summary"><strong>Q${i+1}.</strong> ${escapeHtml(q.q)}</p>
            <div class="answer-summary">Ta réponse: <strong>${escapeHtml(chosenText)}</strong> — <strong>${ans.isCorrect ? 'Correct' : 'Incorrect'}</strong></div>
            <div class="explanation-box">${escapeHtml(q.explanation)}</div>
            <div class="stats-row">
                <span>⚠️ Pénalité : ${Number(ans.penalty || 0).toFixed(2)}</span>
                <span>⭐ Points : ${Number(ans.pointsGained || 0).toFixed(2)}</span>
            </div>
        `;
        refs.resultDetails.appendChild(div);
    });

    updateDownloadLink(data);
    updateLocalBestScore(scoreValue);

    if (refs.saveScoreBtn) refs.saveScoreBtn.classList.remove('hidden');
    loadRankingFromServer();
}

/* =========================
   LOCAL STORAGE : meilleur score local
   ========================= */
function updateLocalBestScore(newScore) {
    const currentBest = parseFloat(localStorage.getItem(LOCAL_STORAGE_KEY) || '0');
    if (newScore > currentBest) localStorage.setItem(LOCAL_STORAGE_KEY, newScore);
    displayLocalBestScore();
}

function displayLocalBestScore() {
    const bestScore = parseFloat(localStorage.getItem(LOCAL_STORAGE_KEY) || '0');
    // Si l'élément refs.bestLocalScore existe, décommentez la ligne ci-dessous :
    // if (refs.bestLocalScore) refs.bestLocalScore.textContent = `${bestScore.toFixed(2)} pts`;
}

/* =========================
   CLASSEMENT / RANKING (GET)
   ========================= */
async function loadRankingFromServer() {
    if (!refs.bestScoresList) return;
    refs.bestScoresList.innerHTML = ''; 

    try {
        const res = await fetch('get_ranking.php', { cache: "no-store" });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const ranking = await res.json();
        if (ranking.error) throw new Error(ranking.error);

        (ranking || []).forEach((entry, idx) => {
            const li = document.createElement('li');
            const scoreDisplay = parseFloat(entry.score_final || 0).toFixed(2);
            li.innerHTML = `<span class="rank">${idx + 1}.</span>
                            <span class="name">${escapeHtml(entry.nom_utilisateur || 'Anonyme')}</span>
                            <span class="score">${scoreDisplay} pts</span>`;
            refs.bestScoresList.appendChild(li);
        });
    } catch (e) {
        console.warn('Erreur loadRankingFromServer:', e);
    }
}

/* =========================
   SAUVEGARDE SCORE (POST)
   ========================= */
async function promptSaveScore() {
    const name = prompt('Entrez votre nom pour le classement :') || 'Anonyme';
    const scoreText = refs.finalScore ? refs.finalScore.textContent : '';
    const scoreMatch = scoreText.match(/([\d\.]+)/);
    const scoreToSave = scoreMatch ? parseFloat(scoreMatch[1]) : null;

    if (scoreToSave === null || QUIZ_ID === null) {
        alert('Score ou Quiz ID manquant, impossible de sauvegarder.');
        return;
    }

    const payload = {
        name,
        score: scoreToSave,
        totalQuestions: QUESTIONS.length,
        quiz_id: QUIZ_ID
    };

    try {
        const res = await fetch('save_score.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data.ok) {
            alert('Score sauvegardé avec succès !');
            loadRankingFromServer();
            if (refs.saveScoreBtn) refs.saveScoreBtn.classList.add('hidden');
        } else {
            alert('Échec de la sauvegarde : ' + (data.error || 'Erreur serveur interne.'));
        }
    } catch (e) {
        console.error('Erreur promptSaveScore:', e);
        alert('Erreur lors de l\'envoi du score.');
    }
}

function saveUserScore(finalScore) {
    // Vérification critique
    if (currentQuizId === null) {
        alert("Erreur critique: ID du Quiz non défini. Impossible de sauvegarder.");
        return;
    }

    // 1. Demander le nom de l'utilisateur
    userName = prompt("Bravo ! Veuillez entrer votre nom pour sauvegarder votre score :", "Anonyme");
    
    if (userName === null || userName.trim() === "") {
        alert("Sauvegarde annulée. Veuillez réessayer en entrant un nom.");
        return;
    }

    const dataToSend = {
        // ⬅️ AJOUT CRITIQUE POUR LA VÉRIFICATION PHP
        quizId: currentQuizId, 
        name: userName.trim(),
        score: finalScore.points,
        totalQuestions: finalScore.total,
        // Les détails des réponses pour la colonne 'details' BDD
        details: finalScore.results 
    };

    fetch('save_score.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dataToSend),
    })
    .then(response => {
        // Vérifie si la réponse HTTP est OK (statut 200-299)
        if (!response.ok) {
            // Tente de lire l'erreur détaillée du PHP
            return response.json().then(err => {
                throw new Error(err.error || `Erreur HTTP ${response.status} sans détail.`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.ok) {
            alert("Score sauvegardé avec succès !");
            
            // Mettre à jour les scores locaux et affichage après sauvegarde BDD réussie
            saveLocalScore(finalScore.points, finalScore.total); 
            
                // Événement de sauvegarde de score
    document.getElementById('save-score').addEventListener('click', () => {
        saveUserScore(finalScore);
    });

        } else {
            // Gérer une réponse 'ok: false' du PHP
            throw new Error(data.error || "Erreur lors de l'enregistrement (réponse non 'ok').");
        }

    

    })
    .catch(error => {
        console.error('Erreur lors de l\'envoi du score:', error);
        alert(`Erreur lors de l'envoi du score. Détail: ${error.message}`); 
    });
}

/* =========================
   CLEAR SERVER SCORES
   ========================= */
async function clearServerScores() {
    if (!confirm("ATTENTION : Êtes-vous sûr de vouloir effacer TOUS les scores enregistrés sur le serveur ? Cette action est irréversible.")) {
        return;
    }
    try {
        const res = await fetch('clear_scores.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data.success) {
            alert('Scores serveur effacés avec succès !');
            loadRankingFromServer();
        } else {
            alert('Échec de l\'effacement : ' + (data.error || 'Erreur serveur interne.'));
        }
    } catch (e) {
        console.error('Erreur clearServerScores:', e);
        alert('Erreur de communication avec le serveur.');
    }
}

/* =========================
   RAPPORT (download)
   ========================= */
function buildReportText(serverData) {
    let text = `Rapport Quiz\nScore final: ${serverData.score.toFixed(2)} / ${serverData.totalQuestions}\n\n`;
    (serverData.details || []).forEach((a, i) => {
        const q = QUESTIONS.find(x => x.id === a.question_id) || { q: 'Question inconnue', choices: [], explanation: 'N/A' };
        const chosenText = a.chosen === null ? "Temps écoulé" : (q.choices[a.chosen] || "—");
        text += `Q${i + 1}. ${q.q}\n`;
        text += `Réponse choisie: ${chosenText} (${a.isCorrect ? 'Correct' : 'Incorrect'})\n`;
        text += `Points : ${Number(a.pointsGained || 0).toFixed(2)}\n`;
        text += `Explication: ${q.explanation}\n\n`;
    });
    return text;
}

function updateDownloadLink(serverData) {
    const report = buildReportText(serverData);
    const blob = new Blob([report], { type: 'text/plain' });
    if (refs.downloadReport) {
        refs.downloadReport.href = URL.createObjectURL(blob);
        refs.downloadReport.download = 'rapport_quiz_' + QUIZ_ID + '_' + new Date().getTime() + '.txt';
    }
}

/* =========================
   RESET / REJOUER
   ========================= */
function resetToStart() {
    stopTimer();
    if (refs.resultScreen) refs.resultScreen.classList.add('hidden');
    if (refs.quizScreen) refs.quizScreen.classList.add('hidden');
    if (refs.startScreen) refs.startScreen.classList.remove('hidden');
    state = { current: 0, answers: [] };
    if (refs.ringFg) refs.ringFg.style.strokeDashoffset = '0px';
    
    // Fermer le modal si ouvert
    if (refs.modalOverlay) refs.modalOverlay.classList.add('hidden');
    tempQuizId = null;

    loadRankingFromServer(); 
}

/* =========================
   INIT (event listeners)
   ========================= */
async function init() {
    initRingTimer();

    // Buttons globaux
    if (refs.nextBtn) refs.nextBtn.addEventListener('click', gotoNext);
    if (refs.quitBtn) refs.quitBtn.addEventListener('click', resetToStart);
    if (refs.retryBtn) refs.retryBtn.addEventListener('click', resetToStart);
    if (refs.saveScoreBtn) refs.saveScoreBtn.addEventListener('click', promptSaveScore);
    if (refs.clearScoresBtn) refs.clearScoresBtn.addEventListener('click', clearServerScores);

    // 1. Délégation : clic sur la zone entière (.quiz-selectable) OUVRE LE MODAL
    if (refs.startScreen) {
        refs.startScreen.addEventListener('click', (event) => {
            // Cible le LI avec la classe quiz-selectable
            const listItem = event.target.closest('.quiz-selectable');
            if (!listItem) return; 
            
            const quizId = listItem.dataset.quizId;
            const quizTitle = listItem.dataset.quizTitle; 
            const questionCount = listItem.dataset.totalQuestions;
            const isDisabled = listItem.dataset.disabled === 'true'; // Vérifie l'état désactivé

            if (isDisabled) {
                alert('Ce quiz ne contient aucune question pour le moment.');
                return;
            }

            if (!quizId) return alert('QuizId manquant.');
            
            // Stocke l'ID et les infos temporairement
            tempQuizId = quizId;
            
            // Met à jour le contenu du modal
            if (refs.quizTitlePlaceholder) refs.quizTitlePlaceholder.textContent = quizTitle;
            if (refs.questionCountPlaceholder) refs.questionCountPlaceholder.textContent = questionCount;

            // Affiche le modal
            if (refs.modalOverlay) refs.modalOverlay.classList.remove('hidden');
        });
    }

    // 2. GESTIONNAIRE DU BOUTON ANNULER (FERME LE MODAL)
    if (refs.modalCancelBtn) {
        refs.modalCancelBtn.addEventListener('click', () => {
            if (refs.modalOverlay) refs.modalOverlay.classList.add('hidden');
            tempQuizId = null; // Réinitialise l'ID
        });
    }

    // 3. GESTIONNAIRE DU BOUTON CONFIRMER (DÉMARRE LE QUIZ)
    if (refs.modalConfirmBtn) {
        refs.modalConfirmBtn.addEventListener('click', async () => {
            const idToStart = tempQuizId;
            if (!idToStart) {
                if (refs.modalOverlay) refs.modalOverlay.classList.add('hidden');
                return;
            }
            
            // UI feedback
            refs.modalConfirmBtn.disabled = true;
            const originalText = refs.modalConfirmBtn.textContent;
            refs.modalConfirmBtn.textContent = 'Chargement...';
            
            if (refs.modalOverlay) refs.modalOverlay.classList.add('hidden');

            const questions = await loadQuestionsForQuiz(idToStart);

            if (questions && questions.length > 0) {
                startQuiz(questions, idToStart);
            } else {
                alert("Erreur de chargement ou le quiz ne contient pas de questions.");
                resetToStart(); // Retour à l'écran de départ si échec
            }

            // Réinitialisation après le chargement
            refs.modalConfirmBtn.textContent = originalText;
            refs.modalConfirmBtn.disabled = false;
            tempQuizId = null; 
        });
    }

    // Affichage scores/localStorage au chargement de la page
    displayLocalBestScore();
    loadRankingFromServer();
}

document.addEventListener('DOMContentLoaded', init);