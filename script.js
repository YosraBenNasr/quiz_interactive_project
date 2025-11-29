// Fichier : app.js

const LOCAL_STORAGE_KEY = 'bestQuizScore';

let QUESTIONS = [];
let state = { current: 0, score: 0, answers: [] };
let timer = null;
let timeLeft = 0;
const timePerQuestion = 10; // Temps alloué par question (secondes)
const penaltyPerSec = 0.05; // Pénalité par seconde non utilisée (pour la validation serveur)

const refs = {
    startBtn: document.getElementById("start-btn"),
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
    totalQuestionsSpan: document.getElementById("total-questions"),
    finalScore: document.getElementById("final-score"),
    resultDetails: document.getElementById("result-details"),
    downloadReport: document.getElementById("download-report"),
    progressBar: document.getElementById("progressBar"),
    progressLabel: document.getElementById("progressLabel"),
    timeDisplay: document.getElementById("time-display"),
    bestScoresList: document.getElementById("best-scores-list"),
    saveScoreBtn: document.getElementById("save-score"),
    clearScoresBtn: document.getElementById("clear-scores"),
    bestLocalScore: document.getElementById("best-local-score")
};

// ====================================================================
// I. INITIALISATION ET CHARGEMENT DES DONNÉES
// ====================================================================

function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

async function loadQuestionsFromServer() {
    try {
        const res = await fetch('get_questions.php', { cache: "no-store" });
        const data = await res.json();

        if (data.error || !data.questions || !Array.isArray(data.questions)) {
            alert('Erreur serveur lors du chargement des questions : ' + (data.error || 'Données invalides.'));
            return;
        }

        // On ne charge que les données nécessaires au client (sans index correct)
        QUESTIONS = data.questions.map(q => ({
            id: q.id,
            q: q.q,
            choices: q.choices,
            explanation: q.explanation || 'Explication non disponible.'
        }));

        QUESTIONS = shuffle(QUESTIONS);
        
    } catch (e) {
        console.error("Erreur de communication :", e);
        alert('Erreur lors du chargement des questions. Vérifiez le serveur PHP.');
    }
}



async function clearServerScores() {
    if (!confirm("ATTENTION : Êtes-vous sûr de vouloir effacer TOUS les scores enregistrés sur le serveur ? Cette action est irréversible.")) {
        return;
    }
    
    try {
        const res = await fetch('clear_scores.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({}) // Corps vide
        });
        const data = await res.json();
        
        if (data.success) {
            alert('Scores serveur effacés avec succès !');
            loadRankingFromServer(); // Recharger le classement (qui sera vide)
        } else {
            // Affichage de l'erreur précise du serveur
            alert('Échec de l\'effacement : ' + (data.error || 'Erreur serveur interne.'));
        }
    } catch(e) {
        console.error("Erreur lors de l'envoi de la requête d'effacement (AJAX) :", e);
        alert('Erreur de communication avec le serveur.');
    }
}


// ====================================================================
// II. GESTION DU QUIZ ET CHRONOMÈTRE
// ====================================================================

function startTimer() {
    stopTimer();
    timeLeft = timePerQuestion;
    updateTimeUI();
    
    timer = setInterval(() => {
        timeLeft--;
        updateTimeUI();
        if (timeLeft <= 0) {
            timeLeft = 0;
            stopTimer();
            timeExpired();
        }
    }, 1000);
}

function stopTimer() { if (timer) clearInterval(timer); timer = null; }
function updateTimeUI() { 
    if (refs.timeDisplay) refs.timeDisplay.textContent = `${Math.ceil(timeLeft)}s`; 
}

function startQuiz() {
    state = { current: 0, score: 0, answers: [] };
    refs.startScreen.classList.add('hidden');
    refs.resultScreen.classList.add('hidden');
    refs.quizScreen.classList.remove('hidden');
    updateProgress();
    renderQuestion();
}

function renderQuestion() {
    const q = QUESTIONS[state.current];
    refs.qIndexEl.textContent = state.current + 1;
    refs.qTitle.textContent = q.q;
    refs.choicesEl.innerHTML = '';
    refs.nextBtn.disabled = true;

    // Affiche les options
    q.choices.forEach((text, index) => {
        const btn = document.createElement('button');
        btn.className = 'choice';
        btn.dataset.index = index;
        btn.innerHTML = `<span>${text}</span>`;
        btn.onclick = () => selectChoice(btn, index);
        refs.choicesEl.appendChild(btn);
    });

    startTimer();
}

// 🔥 CORRECTION CRITIQUE : Affichage des corrections et feedbacks immédiats
async function selectChoice(btn, chosenIndex) {
    stopTimer();
    
    // Désactiver tous les boutons de choix immédiatement
    document.querySelectorAll('.choice').forEach(b => {
        b.disabled = true;
        b.style.pointerEvents = 'none';
    });

    const q = QUESTIONS[state.current];
    let isCorrect = false;

    try {
        // 1. Appel AJAX pour vérifier la réponse (check_answer.php doit exister)
        const payload = { 
            question_id: q.id, 
            chosen_index: chosenIndex 
        };
        const res = await fetch('check_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.error || !data.success) {
            console.error('Erreur vérification:', data.error);
            alert('Erreur serveur lors de la vérification de la réponse.');
            return; 
        }

        isCorrect = data.isCorrect;
        const correctIdx = data.correctIndex; // Index correct renvoyé par le serveur
        
        // 2. Affichage du Feedback

        // Marquer la bonne réponse en vert
        const correctBtn = document.querySelector(`.choice[data-index="${correctIdx}"]`);
        if (correctBtn) {
            correctBtn.classList.add('correct');
        }

        if (!isCorrect) {
            // Marquer le choix de l'utilisateur en rouge s'il est incorrect
            btn.classList.add('wrong');
        } else {
            btn.classList.add('chosen');
        }
        
    } catch (e) {
        console.error("Erreur de communication lors de la vérification :", e);
    }
    
    // 3. Enregistrement de la réponse (pour la soumission finale)
    state.answers.push({
        question_id: q.id,
        chosen: chosenIndex,
        timeRemaining: timeLeft,
        isCorrect: isCorrect 
    });

    // Rendre le bouton 'Suivant' actif
    refs.nextBtn.disabled = false;
}

function timeExpired() {
    // Désactiver les boutons
    document.querySelectorAll('.choice').forEach(b => { 
        b.disabled = true; 
        b.style.pointerEvents = 'none'; 
    });
    
    // Enregistrer une réponse nulle (temps écoulé)
    state.answers.push({
        question_id: QUESTIONS[state.current].id,
        chosen: null, // null = aucune réponse choisie
        timeRemaining: 0,
        isCorrect: false // Toujours incorrect si le temps est écoulé
    });
    
    refs.nextBtn.disabled = false;
}

async function gotoNext() {
    state.current++;
    if (state.current >= QUESTIONS.length) {
        await submitToServer();
        return;
    }
    updateProgress();
    renderQuestion();
}

function updateProgress() {
    const pct = Math.round((state.current / QUESTIONS.length) * 100);
    if (refs.progressBar) refs.progressBar.style.width = pct + '%';
    if (refs.progressLabel) refs.progressLabel.textContent = pct + '%';
}

function resetToStart() {
    stopTimer();
    refs.resultScreen.classList.add('hidden');
    refs.quizScreen.classList.add('hidden');
    refs.startScreen.classList.remove('hidden');
    state = {current:0, score:0, answers: []};
    loadRankingFromServer(); 
}


// ====================================================================
// III. COMMUNICATION SERVEUR (AJAX)
// ====================================================================

async function submitToServer() {
    stopTimer();
    refs.quizScreen.classList.add('hidden');

    try {
        const payload = { answers: state.answers, timePerQuestion, penaltyPerSec };
        const res = await fetch('submit_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.error) throw new Error(data.error);
        
        showResultsFromServer(data);
    } catch (e) {
        console.error("Erreur validation :", e);
        alert('Erreur serveur lors de la validation du quiz. Voir console.');
        resetToStart();
    }
}

async function promptSaveScore() {
    const name = prompt('Entrez votre nom pour le classement :') || 'Anonyme';
    
    const scoreText = refs.finalScore.textContent;
    // Extraction du score, le premier nombre avec ou sans décimale
    const scoreMatch = scoreText.match(/([\d\.]+)/); 
    const scoreToSave = scoreMatch ? scoreMatch[1] : null;

    if (scoreToSave === null) {
        console.error("Score introuvable pour la sauvegarde.");
        return alert("Score introuvable, impossible de sauvegarder.");
    }
    
    const payload = {
        name,
        score: parseFloat(scoreToSave),
        totalQuestions: QUESTIONS.length // CECI est crucial pour la base de données
    };
    
    try {
        const res = await fetch('save_score.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.ok) {
            alert('Score sauvegardé avec succès !');
            loadRankingFromServer(); 
            refs.saveScoreBtn.classList.add('hidden'); 
        } else {
            // Affichage de l'erreur précise du serveur
            alert('Échec de la sauvegarde : ' + (data.error || 'Erreur serveur interne.'));
        }
    } catch(e) {
        console.error("Erreur lors de l'envoi du score (AJAX) :", e);
        alert('Erreur lors de l\'envoi du score.');
    }
}

// ====================================================================
// IV. AFFICHAGE DES RÉSULTATS ET SAUVEGARDE LOCALE
// ====================================================================

function showResultsFromServer(data) {
    refs.resultScreen.classList.remove('hidden');
    refs.resultDetails.innerHTML = '';
    
    const scoreValue = +(data.score).toFixed(2);
    refs.finalScore.textContent = `${scoreValue} / ${data.totalQuestions}`; 

    const percent = Math.round((scoreValue / data.totalQuestions) * 100);
    const resultBarSpan = document.querySelector('#progress-bar-result > span');
    if (resultBarSpan) resultBarSpan.style.width = percent + '%';

    data.details.forEach((ans, i) => {
        const q = QUESTIONS.find(q => q.id === ans.question_id) || {q: 'Question inconnue', choices: [], explanation: 'N/A'};
        const div = document.createElement('div');
        div.className = 'result-item ' + (ans.isCorrect ? 'correct' : 'wrong');
        
        const chosenText = ans.chosen === null ? 'Temps écoulé' : (q.choices[ans.chosen] || '—');
        
        div.innerHTML = `
            <p class="question-title-summary"><strong>Q${i+1}.</strong> ${q.q}</p>
            <div class="answer-summary">Ta réponse: **${chosenText}** — <strong>${ans.isCorrect ? 'Correct' : 'Incorrect'}</strong></div>
            <div class="explanation-box">${q.explanation}</div>
            <div class="stats-row">
                <span>⚠️ Pénalité : ${ans.penalty.toFixed(2)}</span>
                <span>⭐ Points : ${ans.pointsGained.toFixed(2)}</span>
            </div>
        `;
        refs.resultDetails.appendChild(div);
    });

    updateDownloadLink(data);
    updateLocalBestScore(scoreValue); 
    
    if (refs.saveScoreBtn) refs.saveScoreBtn.classList.remove('hidden');
    loadRankingFromServer(); 
}

// Fonctions localStorage
function updateLocalBestScore(newScore) {
    const currentBest = parseFloat(localStorage.getItem(LOCAL_STORAGE_KEY) || 0);
    if (newScore > currentBest) {
        localStorage.setItem(LOCAL_STORAGE_KEY, newScore);
    }
    displayLocalBestScore();
}

function displayLocalBestScore() {
    const bestScore = localStorage.getItem(LOCAL_STORAGE_KEY) || 0;
    if (refs.bestLocalScore) {
        refs.bestLocalScore.textContent = `${parseFloat(bestScore).toFixed(2)} pts`;
    }
}


// Classement Serveur
async function loadRankingFromServer() {
    if (!refs.bestScoresList) return;

    try {
        const res = await fetch('get_ranking.php', { cache: "no-store" });
        const ranking = await res.json();

        if (ranking.error) throw new Error(ranking.error);

        //refs.bestScoresList.innerHTML = '<h3>🏆 Classement</h3>';
        
        ranking.forEach((entry, index) => {
            const li = document.createElement('li');
            li.innerHTML = `
                <span class="rank">${index + 1}.</span> 
                <span class="name">${entry.nom_utilisateur}</span> 
                <span class="score">${parseFloat(entry.score_final).toFixed(2)} pts</span>
            `;
            refs.bestScoresList.appendChild(li);
        });

    } catch (e) {
        refs.bestScoresList.innerHTML = '<p>Classement serveur non disponible.</p>';
    }
}


// Rapport
function buildReportText(serverData) {
    let text = `Rapport Quiz\nScore final: ${serverData.score.toFixed(2)} / ${serverData.totalQuestions}\n\n`;
    
    serverData.details.forEach((a, i) => {
        const q = QUESTIONS.find(x => x.id === a.question_id) || { q: 'Question inconnue', choices: [] };
        const chosenText = a.chosen === null ? "Temps écoulé" : (q.choices[a.chosen] || "—");

        text += `Q${i + 1}. ${q.q}\n`;
        text += `Réponse choisie: ${chosenText} (${a.isCorrect ? 'Correct' : 'Incorrect'})\n`;
        text += `Points : ${a.pointsGained.toFixed(2)}\n\n`;
    });
    return text;
}

function updateDownloadLink(serverData) {
    const report = buildReportText(serverData);
    const blob = new Blob([report], {type: 'text/plain'});
    refs.downloadReport.href = URL.createObjectURL(blob);
    refs.downloadReport.download = 'rapport_quiz.txt';
}


// ====================================================================
// V. INITIALISATION GLOBALE
// ====================================================================

async function init() {
    // Écouteurs d'événements
    if (refs.startBtn) refs.startBtn.addEventListener('click', startQuiz);
    if (refs.nextBtn) refs.nextBtn.addEventListener('click', gotoNext);
    if (refs.quitBtn) refs.quitBtn.addEventListener('click', resetToStart);
    if (refs.retryBtn) refs.retryBtn.addEventListener('click', resetToStart);
    if (refs.saveScoreBtn) refs.saveScoreBtn.addEventListener('click', promptSaveScore);


  
    if (refs.clearScoresBtn) refs.clearScoresBtn.addEventListener('click', clearServerScores);


    // Chargement initial des données
    await loadQuestionsFromServer();
    
    if (QUESTIONS.length > 0) {
        refs.totalQuestionsSpan.textContent = QUESTIONS.length;
        refs.qTotalEl.textContent = QUESTIONS.length;
    } else {
        refs.startBtn.disabled = true;
        refs.startBtn.textContent = 'Erreur : Questions non chargées';
    }
    
    // Affichage des scores
    displayLocalBestScore();
    loadRankingFromServer();
}

document.addEventListener('DOMContentLoaded', init);