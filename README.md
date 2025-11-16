
---

## ⚙️ Fonctionnalités principales avec extraits de code

| Fonctionnalité | Description | Extrait de code |
|----------------|------------|----------------|
| **Feedback immédiat** | La réponse sélectionnée devient verte ou rouge selon qu’elle est correcte ou non | ```js function selectChoice(btn, index){ const isCorrect = index===q.answer; btn.classList.add(isCorrect ? "correct":"wrong"); } ``` |
| **Chronomètre par question** | Compte à rebours et pénalité si temps écoulé | ```js function startTimer(){ timeLeft = timePerQuestion; timer = setInterval(()=>{ timeLeft--; if(timeLeft<=0) timeExpired(); },1000); } ``` |
| **Calcul dynamique du score** | Score mis à jour selon la réponse et le temps restant | ```js state.score += Math.max(0,1-calculatePenalty(timeLeft)); ``` |
| **Progression visuelle** | Barre de progression et pourcentage | ```js function updateProgress(){ const pct = Math.round((state.current/QUESTIONS.length)*100); refs.progressBar.style.width = pct+"%"; refs.progressLabel.textContent = pct+"%"; } ``` |
| **Écran de résultats** | Affiche toutes les réponses, le score et les pénalités | ```js function showResults(){ refs.finalScore.textContent = `Score : ${state.score.toFixed(2)}`; } ``` |
| **Téléchargement du rapport** | Export des résultats sous forme de fichier texte | ```js function buildReportText(){ return state.answers.map(a=>`Q:${a.question} Réponse:${a.chosen}`).join("\n"); } ``` |
| **Meilleurs scores** | Sauvegarde dans localStorage, top 10 | ```js function saveScore(){ let scores = JSON.parse(localStorage.getItem("quiz_scores")||"[]"); scores.push({name:"Anonyme",score:state.score}); localStorage.setItem("quiz_scores",JSON.stringify(scores.slice(0,10))); } ``` |
| **Responsive et accessible** | Fonctionne sur mobile, tablette, desktop, navigation clavier | ```html <button id="start-btn" class="btn primary" type="button">Commencer le quiz</button> ``` |
| **Anti-refresh** | Empêche le rechargement ou fermeture accidentelle pendant le quiz | ```js window.addEventListener("beforeunload", e => { if(!refs.quizScreen.classList.contains("hidden")){ e.preventDefault(); e.returnValue=""; } }); ``` |

---

## 🖥️ Exécution

Ouvrir `index.html` dans un navigateur moderne :

```bash
# Depuis votre explorateur de fichiers
double-cliquez sur index.html
# ou via un serveur local (recommandé)
live-server
