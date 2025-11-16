/*******************************
 ⭐ QUIZ PROJECT — JS CORRIGÉ ⭐
*******************************/

// ------------------- Questions du Quiz -------------------
const QUESTIONS = [
    {
        q: "Quelle est la capitale de la Tunisie ?",
        choices: ["Alger", "Tunis", "Rabat", "Tripoli"],
        answer: 1,
        explanation: "Tunis est la capitale officielle de la République Tunisienne."
    },
    {
        q: "Quel langage est principalement utilisé pour le style des pages web ?",
        choices: ["HTML", "Python", "CSS", "SQL"],
        answer: 2,
        explanation: "CSS (Cascading Style Sheets) est utilisé pour le style."
    },
    {
        q: "Combien de bits dans un octet ?",
        choices: ["4", "8", "16", "32"],
        answer: 1,
        explanation: "Un octet contient 8 bits."
    },
    {
        q: "HTML est un acronyme pour ?",
        choices: [
            "HyperText Markup Language",
            "Hyperlink Text Makeup Language",
            "Hyper Tool Markup Language",
            "HyperText Machine Language"
        ],
        answer: 0,
        explanation: "HTML signifie HyperText Markup Language."
    },
    {
        q: "Laquelle des balises HTML suivantes s'utilise pour un lien ?",
        choices: ["<img>", "<a>", "<div>", "<span>"],
        answer: 1,
        explanation: "La balise <a> définit un lien."
    },
    {
        q: "Quel est le sélecteur CSS pour cibler un id ?",
        choices: ["#id", ".id", "*id", "id"],
        answer: 0,
        explanation: "Le symbole '#' sélectionne un id en CSS."
    }
];


// ------------------- Variables Globales -------------------
let state = {
    current: 0,
    score: 0,
    answers: []
};

let timer = null;
let timeLeft = 0;
let timePerQuestion = 10;
const penaltyPerSec = 0.05;

// ------------------- Références DOM -------------------
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
    timePerQSelect: document.getElementById("time-per-q"),
    bestScoresList: document.getElementById("best-scores-list"),
    saveScoreBtn: document.getElementById("save-score"),
    clearScoresBtn: document.getElementById("clear-scores")
};


// ------------------- Initialisation -------------------
function init() {
    refs.totalQuestionsSpan.textContent = QUESTIONS.length;
    refs.qTotalEl.textContent = QUESTIONS.length;

    refs.startBtn.addEventListener("click", startQuiz);
    refs.nextBtn.addEventListener("click", gotoNext);
    refs.quitBtn.addEventListener("click", resetToStart);
    refs.retryBtn.addEventListener("click", resetToStart);
    refs.downloadReport.addEventListener("click", updateDownloadLink);

    if (refs.timePerQSelect) {
        refs.timePerQSelect.addEventListener("change", (e) => {
            timePerQuestion = Number(e.target.value);
        });
    }

    if (refs.saveScoreBtn) refs.saveScoreBtn.addEventListener("click", saveScore);
    if (refs.clearScoresBtn) refs.clearScoresBtn.addEventListener("click", clearScores);

    loadScores();
    updateScoresUI();

    // 🔥 Anti-refresh / Anti-fermeture
    window.addEventListener("beforeunload", beforeUnloadHandler);

    prepareTimerRing();
}


// ------------------- Timer Ring -------------------
function prepareTimerRing() {
    refs.timerRing = document.querySelector(".ring-fg");
    if (!refs.timerRing) return;

    const radius = 50;
    const circumference = 2 * Math.PI * radius;

    refs.timerRing.style.strokeDasharray = circumference;
    refs.timerRing.style.strokeDashoffset = circumference;

    refs._circumference = circumference;
}

function updateTimeUI() {
    if (refs.timeDisplay) {
        refs.timeDisplay.textContent = `${Math.ceil(timeLeft)}s`;
    }

    if (refs.timerRing && refs._circumference) {
        const percent = timeLeft / timePerQuestion;
        const offset = refs._circumference * percent;
        refs.timerRing.style.strokeDashoffset = offset;
    }
}


// ------------------- Timer -------------------
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

function stopTimer() {
    if (timer) clearInterval(timer);
    timer = null;
}


// ------------------- Start Quiz -------------------
function startQuiz() {
    state = { current: 0, score: 0, answers: [] };

    refs.startScreen.classList.add("hidden");
    refs.quizScreen.classList.remove("hidden");

    updateProgress();
    renderQuestion();
}


// ------------------- Questions -------------------
function renderQuestion() {
    const q = QUESTIONS[state.current];
    refs.qIndexEl.textContent = state.current + 1;
    refs.qTitle.textContent = q.q;

    refs.choicesEl.innerHTML = "";
    refs.nextBtn.disabled = true;

    q.choices.forEach((text, index) => {
        const btn = document.createElement("button");
        btn.className = "choice";
        btn.dataset.index = index;
        btn.innerHTML = `<span>${text}</span>`;

        btn.onclick = () => selectChoice(btn, index);
        refs.choicesEl.appendChild(btn);
    });

    startTimer();
}


// ------------------- Sélection d’un choix -------------------
function selectChoice(btn, index) {
    stopTimer();

    const q = QUESTIONS[state.current];
    const isCorrect = index === q.answer;

    document.querySelectorAll(".choice").forEach((b) => {
        b.disabled = true;
        b.style.pointerEvents = "none";
    });

    if (isCorrect) {
        btn.classList.add("correct");
        let gained = Math.max(0, 1 - calculatePenalty(timeLeft));
        state.score += gained;
    } else {
        btn.classList.add("wrong");
        const correct = Array.from(refs.choicesEl.children).find(
            (b) => Number(b.dataset.index) === q.answer
        );
        if (correct) correct.classList.add("correct");
    }

    state.answers.push({
        question: q.q,
        chosen: index,
        correct: q.answer,
        isCorrect,
        explanation: q.explanation,
        timeRemaining: timeLeft,
        penalty: calculatePenalty(timeLeft)
    });

    refs.nextBtn.disabled = false;
}


// ------------------- Temps écoulé -------------------
function timeExpired() {
    const q = QUESTIONS[state.current];

    document.querySelectorAll(".choice").forEach((b) => {
        b.disabled = true;
        b.style.pointerEvents = "none";
    });

    const correct = Array.from(refs.choicesEl.children).find(
        (b) => Number(b.dataset.index) === q.answer
    );
    if (correct) correct.classList.add("correct");

    state.answers.push({
        question: q.q,
        chosen: null,
        correct: q.answer,
        isCorrect: false,
        explanation: q.explanation,
        timeRemaining: 0,
        penalty: calculatePenalty(timePerQuestion)
    });

    refs.nextBtn.disabled = false;
}


// ------------------- Pénalité -------------------
function calculatePenalty(timeRem) {
    const elapsed = timePerQuestion - timeRem;
    return +(elapsed * penaltyPerSec).toFixed(3);
}


// ------------------- Question suivante -------------------
function gotoNext() {
    state.current++;

    if (state.current >= QUESTIONS.length) {
        showResults();
        return;
    }

    updateProgress();
    renderQuestion();
}


// ------------------- Progression -------------------
function updateProgress() {
    const pct = Math.round((state.current / QUESTIONS.length) * 100);
    if (refs.progressBar) refs.progressBar.style.width = pct + "%";
    if (refs.progressLabel) refs.progressLabel.textContent = pct + "%";
}


// ------------------- Résultats -------------------
// ------------------- Affichage des résultats -------------------
function showResults() {
    stopTimer();

    refs.quizScreen.classList.add("hidden");
    refs.resultScreen.classList.remove("hidden");

    // Vider anciens résultats
    if (refs.resultDetails) refs.resultDetails.innerHTML = "";

    const scoreValue = +(state.score).toFixed(2);
    refs.finalScore.textContent = `Ton score: ${scoreValue} / ${QUESTIONS.length}`;

    const percent = Math.round((scoreValue / QUESTIONS.length) * 100);
    const resultBar = document.querySelector(".progress-bar-result > span");

    if (resultBar) resultBar.style.width = percent + "%";

    state.answers.forEach((ans, i) => {
        const q = QUESTIONS[i];

        const div = document.createElement("div");
        div.className = "result-item " + (ans.isCorrect ? "correct" : "wrong");

        const chosenText =
            ans.chosen === null ? "Temps écoulé" : (q.choices[ans.chosen] || "—");

        const timeElapsed = +(timePerQuestion - ans.timeRemaining).toFixed(1);

        div.innerHTML = `
            <p class="question-title-summary">
                <strong>Q${i + 1}.</strong> ${q.q}
            </p>

            <div class="answer-summary">
                Ta réponse: ${chosenText} —
                <strong>${ans.isCorrect ? "Correct" : "Incorrect"}</strong>
            </div>

            <div class="explanation-box">
                ${q.explanation}
            </div>

            <div class="stats-row">
                <span>🕒 Temps : ${timeElapsed}s</span>
                <span>⚠️ Pénalité : ${ans.penalty}</span>
                <span>⭐ Points : ${ans.pointsGained}</span>
            </div>
        `;

        refs.resultDetails.appendChild(div);
    });

    if (refs.saveScoreBtn) refs.saveScoreBtn.classList.remove("hidden");

    updateDownloadLink();
}

// ------------------- Rapport Txt -------------------
function buildReportText() {
    let text = `Rapport Quiz\nScore : ${state.score.toFixed(2)}/${QUESTIONS.length}\n\n`;

    state.answers.forEach((a, i) => {
        text += `Q${i + 1}. ${a.question}\n`;
        text += `Réponse : ${a.chosen === null ? "Temps écoulé" : QUESTIONS[i].choices[a.chosen]}\n`;
        text += `Correct  : ${QUESTIONS[i].choices[a.correct]}\n`;
        text += `Pénalité : ${a.penalty}\n\n`;
    });

    return text;
}

function updateDownloadLink() {
    const blob = new Blob([buildReportText()], { type: "text/plain" });
    refs.downloadReport.href = URL.createObjectURL(blob);
}


// ------------------- Reset -------------------
function resetToStart() {
    stopTimer();
    refs.resultScreen.classList.add("hidden");
    refs.quizScreen.classList.add("hidden");
    refs.startScreen.classList.remove("hidden");

    state = { current: 0, score: 0, answers: [] };
}


// ------------------- Scores LocalStorage -------------------
function saveScore() {
    const name = prompt("Nom (laisser vide = Anonyme)") || "Anonyme";

    const entry = {
        name,
        score: +state.score.toFixed(2),
        date: new Date().toISOString()
    };

    const scores = JSON.parse(localStorage.getItem("quiz_scores") || "[]");
    scores.push(entry);

    scores.sort((a, b) => b.score - a.score);

    localStorage.setItem("quiz_scores", JSON.stringify(scores.slice(0, 10)));

    updateScoresUI();
}

function loadScores() {
    refs.scores = JSON.parse(localStorage.getItem("quiz_scores") || "[]");
}

function updateScoresUI() {
    loadScores();
    if (!refs.bestScoresList) return;

    refs.bestScoresList.innerHTML = "";

    if (refs.scores.length === 0) {
        refs.bestScoresList.innerHTML = "<li>Aucun score</li>";
        return;
    }

    refs.scores.forEach((s) => {
        const li = document.createElement("li");
        li.textContent = `${s.name} — ${s.score} pts — ${new Date(
            s.date
        ).toLocaleString()}`;
        refs.bestScoresList.appendChild(li);
    });
}

function clearScores() {
    localStorage.removeItem("quiz_scores");
    updateScoresUI();
}


// ------------------- Anti-refresh / Anti-Fermer -------------------
function beforeUnloadHandler(e) {
    if (!refs.quizScreen.classList.contains("hidden")) {
        e.preventDefault();
        e.returnValue = "";
    }
}


// ------------------- DOM Ready -------------------
document.addEventListener("DOMContentLoaded", init);
