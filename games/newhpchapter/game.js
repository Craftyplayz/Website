// ── Score state ───────────────────────────────────────────────────────────────
let score = 0;
let highScore = parseInt(localStorage.getItem("hpHighScore") || "0", 10);

// ── Persistent element refs ───────────────────────────────────────────────────
const paragraphEl = document.getElementById("paragraph");
const buttonsEl = document.querySelector(".buttons");
const chapterIdEl = document.getElementById("chapter-id");
const scoreEl = document.getElementById("score-current");
const highScoreEl = document.getElementById("score-high");

// ── Round state ───────────────────────────────────────────────────────────────
let indexData = null;
let correctBookTitle = null;
let chapterId = null;

// ── Inject animations ─────────────────────────────────────────────────────────
(function injectStyles() {
  const style = document.createElement("style");
  style.textContent = `
    @keyframes fadeSlideIn {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeSlideOut {
      from { opacity: 1; transform: translateY(0); }
      to   { opacity: 0; transform: translateY(-8px); }
    }
    @keyframes scorePop {
      0%   { transform: scale(1); }
      40%  { transform: scale(1.4); }
      100% { transform: scale(1); }
    }
    @keyframes highlightCorrect {
      0%   { box-shadow: 0 0 0 0px rgba(80,200,100,0.6); }
      50%  { box-shadow: 0 0 0 5px rgba(80,200,100,0.6); }
      100% { box-shadow: 0 0 0 3px rgba(80,200,100,0.4); }
    }
    @keyframes shakeWrong {
      0%,100% { transform: translateX(0); }
      20%     { transform: translateX(-5px); }
      40%     { transform: translateX(5px); }
      60%     { transform: translateX(-3px); }
      80%     { transform: translateX(3px); }
    }

    .paragraph-animate {
      animation: fadeSlideIn 0.5s ease forwards;
    }

    .btn-animate {
      opacity: 0;
      animation: fadeSlideIn 0.35s ease forwards;
    }

    button.correct {
      background-color: #4caf50 !important;
      color: #fff !important;
      border-color: #4caf50 !important;
      animation: highlightCorrect 0.5s ease forwards;
    }

    button.wrong {
      opacity: 0.4;
      animation: shakeWrong 0.35s ease;
    }

    button:disabled {
      cursor: not-allowed;
    }

    #score-current {
      display: inline-block;
    }
    #score-current.pop {
      animation: scorePop 0.35s ease;
    }
  `;
  document.head.appendChild(style);
})();

// ── Score helpers ─────────────────────────────────────────────────────────────
function updateScoreDisplay() {
  scoreEl.textContent = score;
  highScoreEl.textContent = highScore;
}

function incrementScore() {
  score++;
  if (score > highScore) {
    highScore = score;
    localStorage.setItem("hpHighScore", highScore);
  }
  updateScoreDisplay();

  // Pop animation
  scoreEl.classList.remove("pop");
  void scoreEl.offsetWidth; // reflow
  scoreEl.classList.add("pop");
}

// ── Paragraph load ────────────────────────────────────────────────────────────
function loadParagraph(bookIndex, chapId) {
  return fetch(`/games/newhpchapter/0${bookIndex}/${chapId}.json`)
    .then((res) => res.json())
    .then((data) => {
      const idx = Math.floor(Math.random() * data.paragraphs.length);
      paragraphEl.classList.remove("paragraph-animate");
      void paragraphEl.offsetWidth;
      paragraphEl.textContent = data.paragraphs[idx];
      paragraphEl.classList.add("paragraph-animate");
    })
    .catch(console.error);
}

// ── Button renderers ──────────────────────────────────────────────────────────
function showBookButtons() {
  buttonsEl.innerHTML = "";

  indexData.forEach((book, i) => {
    const btn = document.createElement("button");
    btn.textContent = book.bookTitle;
    btn.style.animationDelay = `${i * 50}ms`;
    btn.classList.add("btn-animate");

    btn.addEventListener("click", () => {
      if (book.bookTitle === correctBookTitle) {
        btn.classList.add("correct");
        setTimeout(() => showChapterButtons(book), 400);
      } else {
        btn.classList.add("wrong");

        // Highlight the correct book, disable all, end game
        const allBtns = buttonsEl.querySelectorAll("button");
        allBtns.forEach((b) => {
          b.disabled = true;
          if (b.textContent === correctBookTitle) b.classList.add("correct");
        });

        endGame();
      }
    });

    buttonsEl.appendChild(btn);
  });
}

function showChapterButtons(book) {
  buttonsEl.innerHTML = "";

  book.chapters.forEach((chapter, i) => {
    const btn = document.createElement("button");
    btn.textContent = chapter.title;
    btn.style.animationDelay = `${i * 30}ms`;
    btn.classList.add("btn-animate");

    btn.addEventListener("click", () => {
      if (chapter.id === chapterId) {
        btn.classList.add("correct");
        incrementScore();

        // After 1 second, start a new round
        setTimeout(() => startRound(), 1000);
      } else {
        btn.classList.add("wrong");

        // Highlight the correct chapter, disable all, end game
        const correctTitle = book.chapters.find(
          (c) => c.id === chapterId,
        )?.title;
        const allBtns = buttonsEl.querySelectorAll("button");
        allBtns.forEach((b) => {
          b.disabled = true;
          if (b.textContent === correctTitle) b.classList.add("correct");
        });

        endGame();
      }
    });

    buttonsEl.appendChild(btn);
  });
}

// ── Game over ─────────────────────────────────────────────────────────────────
function endGame() {
  setTimeout(() => {
    buttonsEl.innerHTML = "";

    const msg = document.createElement("p");
    msg.textContent = `Game over! You scored ${score}.`;
    msg.style.cssText =
      "font-weight:bold; font-size:1.2em; margin-bottom:1em; animation: fadeSlideIn 0.4s ease forwards;";
    buttonsEl.appendChild(msg);

    const restartBtn = document.createElement("button");
    restartBtn.textContent = "Play Again";
    restartBtn.classList.add("btn-animate");
    restartBtn.addEventListener("click", () => {
      score = 0;
      updateScoreDisplay();
      startRound();
    });
    buttonsEl.appendChild(restartBtn);
  }, 800);
}

// ── Round logic ───────────────────────────────────────────────────────────────
function startRound() {
  const randomBook = indexData[Math.floor(Math.random() * indexData.length)];
  const randomChapter =
    randomBook.chapters[Math.floor(Math.random() * randomBook.chapters.length)];

  chapterId = randomChapter.id;
  correctBookTitle = randomBook.bookTitle;

  // Debug label
  chapterIdEl.textContent = `Chapter ID: ${chapterId}`;

  loadParagraph(randomBook.bookIndex, chapterId).then(() => {
    showBookButtons();
  });
}

// ── Boot ──────────────────────────────────────────────────────────────────────
updateScoreDisplay();

fetch("newhpchapter/index.json")
  .then((res) => res.json())
  .then((data) => {
    indexData = data;
    startRound();
  })
  .catch(console.error);
