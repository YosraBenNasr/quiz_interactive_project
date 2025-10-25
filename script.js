/* Quiz logic: questions array, UI bindings, accessible keyboard support, feedback in real-time */
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
    explanation: "CSS (Cascading Style Sheets) est utilisé pour le style et la mise en forme."
  },
  {
    q: "Combien de bits dans un octet ?",
    choices: ["4", "8", "16", "32"],
    answer: 1,
    explanation: "Un octet = 8 bits."
  },
  {
    q: "HTML est un acronyme pour ?",
    choices: ["HyperText Markup Language", "Hyperlink Text Makeup Language", "Hyper Tool Markup Language", "HyperText Machine Language"],
    answer: 0,
    explanation: "HTML signifie HyperText Markup Language."
  },
  {
    q: "Laquelle des balises HTML suivantes s'utilise pour un lien ?",
    choices: ["<img>", "<a>", "<div>", "<span>"],
    answer: 1,
    explanation: "La balise <a> définit un hyperlien."
  },
  {
    q: "Quel est le sélecteur CSS pour cibler un id ?",
    choices: ["#id", ".id", "*id", "id"],
    answer: 0,
    explanation: "Le symbole '#' est utilisé pour sélectionner un id en CSS."
  }
];

let state = {
  current: 0,
  score: 0,
  answers: []
};

const startBtn = document.getElementById('start-btn');
const startScreen = document.getElementById('start-screen');
const quizScreen = document.getElementById('quiz-screen');
const resultScreen = document.getElementById('result-screen');
const qIndexEl = document.getElementById('q-index');
const qTotalEl = document.getElementById('q-total');
const qTitle = document.getElementById('question-title');
const choicesEl = document.getElementById('choices');
const nextBtn = document.getElementById('next-btn');
const quitBtn = document.getElementById('quit-btn');
const retryBtn = document.getElementById('retry-btn');
const totalQuestionsSpan = document.getElementById('total-questions');
const finalScore = document.getElementById('final-score');
const resultDetails = document.getElementById('result-details');
const downloadReport = document.getElementById('download-report');

function init(){
  totalQuestionsSpan.textContent = QUESTIONS.length;
  qTotalEl.textContent = QUESTIONS.length;
  document.getElementById('q-index').textContent = 0;
  startBtn.addEventListener('click', startQuiz);
  nextBtn.addEventListener('click', gotoNext);
  quitBtn.addEventListener('click', () => location.reload());
  retryBtn.addEventListener('click', () => location.reload());
  downloadReport.addEventListener('click', generateReportDownload);
}

function startQuiz(){
  state.current = 0;
  state.score = 0;
  state.answers = [];
  startScreen.classList.add('hidden');
  quizScreen.classList.remove('hidden');
  renderQuestion();
}

function renderQuestion(){
  const q = QUESTIONS[state.current];
  qIndexEl.textContent = state.current + 1;
  qTitle.textContent = q.q;
  choicesEl.innerHTML = '';
  nextBtn.disabled = true;

  q.choices.forEach((choiceText, i) => {
    const button = document.createElement('button');
    button.className = 'choice';
    button.setAttribute('role','radio');
    button.setAttribute('aria-checked','false');
    button.setAttribute('data-index', i);
    button.tabIndex = 0;

    const span = document.createElement('span');
    span.className = 'label';
    span.textContent = choiceText;

    button.appendChild(span);
    choicesEl.appendChild(button);

    button.addEventListener('click', () => selectChoice(button, i));
    // keyboard support
    button.addEventListener('keydown', (e) => {
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        selectChoice(button, i);
      }
    });
  });
}

function selectChoice(button, idx){
  const q = QUESTIONS[state.current];
  // disable other choices
  const choiceButtons = Array.from(choicesEl.querySelectorAll('.choice'));
  choiceButtons.forEach(b => {
    b.setAttribute('aria-checked','false');
    b.disabled = true;
    b.style.pointerEvents = 'none';
  });

  const isCorrect = idx === q.answer;
  if(isCorrect){
    button.classList.add('correct');
    state.score += 1;
  } else {
    button.classList.add('wrong');
    // highlight the correct answer
    const correctBtn = choiceButtons.find(b => Number(b.dataset.index) === q.answer);
    if(correctBtn) correctBtn.classList.add('correct');
  }

  // save answer with explanation
  state.answers.push({
    question: q.q,
    chosen: idx,
    correct: q.answer,
    isCorrect: isCorrect,
    explanation: q.explanation
  });

  nextBtn.disabled = false;
}

function gotoNext(){
  state.current += 1;
  if(state.current >= QUESTIONS.length){
    showResults();
  } else {
    renderQuestion();
  }
}

function showResults(){
  quizScreen.classList.add('hidden');
  resultScreen.classList.remove('hidden');
  finalScore.textContent = `Ton score: ${state.score} / ${QUESTIONS.length}`;
  resultDetails.innerHTML = '';
  state.answers.forEach((ans, i) => {
    const div = document.createElement('div');
    div.className = 'result-item';
    div.innerHTML = `<strong>Q${i+1}.</strong> ${ans.question}<br>
                     Ta réponse: ${QUESTIONS[i].choices[ans.chosen] || '—'} — <strong>${ans.isCorrect ? 'Correct' : 'Incorrect'}</strong><br>
                     <em>Explication:</em> ${ans.explanation}`;
    resultDetails.appendChild(div);
  });
  // enable download link update
  updateDownloadLink();
}

function updateDownloadLink(){
  const content = buildReportText();
  const blob = new Blob([content], {type: 'text/plain;charset=utf-8'});
  const url = URL.createObjectURL(blob);
  downloadReport.href = url;
}

function buildReportText(){
  const parts = [];
  parts.push("Rapport - Quiz Interactif\n");
  parts.push("Score: " + state.score + " / " + QUESTIONS.length + "\n\n");
  parts.push("Détails:\n");
  state.answers.forEach((a, i) => {
    parts.push(`Q${i+1}. ${a.question}`);
    parts.push(`Ta réponse: ${QUESTIONS[i].choices[a.chosen] || '—'}`);
    parts.push(`Correct: ${QUESTIONS[i].choices[a.correct]}`);
    parts.push(`Explication: ${a.explanation}\n`);
  });
  return parts.join("\n");
}

function generateReportDownload(e){
  // link is already prepared via updateDownloadLink
  // allow default behavior (download attribute)
}

// initialize on DOM ready
document.addEventListener('DOMContentLoaded', init);
