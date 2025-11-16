# 🎯 Projet Web — Page de Quiz Interactif

**Sujet 7 — Quiz Dynamique avec JavaScript**

---

## 📌 Description du Projet

Ce projet consiste à développer une page de **Quiz interactif en JavaScript**, permettant à l’utilisateur de répondre à une série de questions avec gestion de **chronomètre**, **feedback immédiat**, **score dynamique** et **historique des meilleurs résultats**.

L’application est **100% dynamique**, sans rechargement de page, et respecte les bonnes pratiques de **structure HTML5**, **accessibilité**, **responsive design**, et **JavaScript vanilla**.

---

## 🚀 Fonctionnalités Principales

| Fonctionnalité            | Description                                     |
| ------------------------- | ----------------------------------------------- |
| ⏱ Chronomètre             | Décompte individuel pour chaque question        |
| 🎯 Calcul de score        | Score basé sur exactitude + pénalité temporelle |
| 💾 Sauvegarde des scores  | Stockage local via `localStorage`               |
| 🔁 Rejouer                | Reset complet du quiz sans recharger la page    |
| 🔍 Types de questions     | QCM et Vrai/Faux                                |
| ⭐ Progression visuelle    | Barre de progression + compteur                 |
| 📥 Rapport téléchargeable | Génération de fichier `.txt`                    |
| 🎨 UX interactive         | Feedback immédiat (correct / incorrect)         |

---

## 🧠 Logique du score & pénalités

* Chaque bonne réponse rapporte **1 point maximum**
* Une **pénalité temporelle** réduit les points :

```js
points = 1 - (tempsÉcoulé * 0.05)
```

* Si le temps est écoulé → **0 point**
* Score final arrondi à **2 décimales**

---

## 🛠 Technologies & Contraintes

### 🧩 Langages

* HTML5 sémantique
* CSS3 moderne (flexbox, animations, responsive)
* JavaScript Vanilla (DOM API, `setInterval`, `localStorage`)

### ⚙ Contraintes techniques

* ❌ Aucun framework ou librairie externe (jQuery, React, Bootstrap…)
* ❌ Aucun refresh de page
* ✔ Code séparé : `index.html`, `style.css`, `script.js`
* ✔ Accessibilité (`aria-*`, roles, labels…)
* ✔ Interface responsive mobile & desktop

---

## 📂 Structure des fichiers

```
/quiz_project
│
├── index.html       # Structure et interface utilisateur
├── style.css        # Styles et animations
└── script.js        # Logique du quiz et fonctionnalités
```

---

## 📸 Aperçu des écrans

1️⃣ Écran de démarrage  
![Écran de démarrage](images/start-screen.png)

2️⃣ Interface de question  
![Interface de question](images/question-screen.png)

3️⃣ Résultats détaillés + statistiques  
![Résultats détaillés](images/result-screen.png)



---

## 🔮 Améliorations possibles

* Ajout des **niveaux de difficulté**
* Banque de questions via API
* Mode **off-line PWA**
* Support **clavier + lecteur d’écran (ARIA)** renforcé

---

## 👨‍💻 Auteur

Projet réalisé dans le cadre du module **Développement Web — FIA3 — Année 2025/2026**

---

### 🏁 Merci et bonne exploration du quiz !
