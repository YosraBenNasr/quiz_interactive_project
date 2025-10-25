# Rapport de Mini Projet Web
**Titre du mini projet :** Page de Quiz interactif

**Élaboré par :**  
Étudiant 1 : ................  
Étudiant 2 : ................

**A.U. :** 2025/2026

---

## 1. Introduction
Ce mini-projet consiste à développer une **page de quiz interactif** en HTML5/CSS3/JavaScript. L'objectif est de créer une interface ludique, accessible et responsive permettant à un utilisateur de répondre à une série de questions et de recevoir un retour visuel immédiat ainsi que son score final.

## 2. Cahier des charges / Objectifs
- Créer une interface web ludique pour répondre à un quiz.
- Afficher des résultats en temps réel avec un feedback visuel.
- Respecter les contraintes techniques: HTML5, CSS3, JavaScript, responsive design, accessibilité et animations CSS.
- Fournir les fichiers séparés (`index.html`, `style.css`, `script.js`) et un rapport.

## 3. Conception
### Structure du dossier
```
/quiz_interactive_project
├─ index.html
├─ style.css
├─ script.js
├─ rapport.md
└─ README.md
```

### Structure HTML
La page contient:
- Header (titre + sous-titre)
- Main avec 3 écrans: écran de démarrage, écran de quiz, écran des résultats
- Footer

### Charte graphique
Couleurs : bleu principal pour l'accent, blanc pour les cartes, verts/rouges pour feedback (correct/incorrect). Police système moderne pour performance.

### Outils utilisés
- VS Code (ou éditeur de texte)
- Navigateur (Chrome/Firefox)
- Live Server (optionnel)

## 4. Implémentation (Code et explications)
Le quiz est implémenté en trois fichiers:

### index.html
- Contient la structure sémantique: header, main, footer.
- Trois sections: start-screen, quiz-screen, result-screen.
- Attributs ARIA pour améliorer l'accessibilité (`role="radiogroup"`, `aria-live`).

### style.css
- Utilisation de variables CSS pour la palette.
- Flexbox et media queries pour la mise en page responsive.
- Animations simples pour feedback (pop, shake).

### script.js
- Tableau `QUESTIONS` stocke les questions, les choix, la réponse correcte et une explication.
- `state` conserve l'état courant (index, score, réponses).
- `renderQuestion()` affiche dynamiquement les choix et lie les événements click/keydown pour l'accessibilité.
- `selectChoice()` applique le feedback visuel, incrémente le score, sauvegarde le résultat.
- `showResults()` affiche le score final et les explications.
- Téléchargement d'un rapport texte via création d'un Blob.

Extrait (exemple) — logique de sélection :
```js
if(idx === q.answer){
  button.classList.add('correct');
  state.score += 1;
} else {
  button.classList.add('wrong');
  // highlight correct one
}
```

## 5. Résultat final
L'application fonctionne localement en ouvrant `index.html`.  
Captures d'écran (à ajouter) : écran de démarrage, question avec feedback, écran de résultats.

## 6. Difficultés rencontrées et solutions
- Gestion de l'accessibilité au clavier : solution -> ajout d'écouteurs `keydown` pour Enter et Space.
- Feedback visuel et animation fluide : solution -> animations CSS (`@keyframes`).
- Mise en page responsive pour les choix : résolution avec `flex-wrap` et media queries.

## 7. Conclusion
Ce projet a consolidé les connaissances en HTML/CSS/JS, en particulier dans la création d'interfaces dynamiques, l'accessibilité, et le design responsive. Il constitue une base solide pour ajouter des fonctionnalités avancées (persistances, base de données, utilisateurs, timer, animations plus riches).

## 8. Annexes
- Code complet (voir fichiers fournis).
- Améliorations possibles : minuterie par question, sauvegarde des scores via localStorage ou backend, animations supplémentaires, plus de questions dynamiques via JSON externe.
