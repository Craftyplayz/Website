let books = [];
let score = 0;
let correctBook = null;
let correctChapter = null;
let currentParagraph = "";
let correctBookTitle = "";
let correctChapterTitle = "";

const gameDiv = document.getElementById("game");

// Load index.json first
async function loadIndex() {
  const res = await fetch("index.json");
  books = await res.json();
  startRound();
}

// Start a round
async function startRound() {
  gameDiv.innerHTML = "";

  // 1. random book
  const book = books[Math.floor(Math.random() * books.length)];

  // 2. random chapter
  const chapter =
    book.chapters[Math.floor(Math.random() * book.chapters.length)];

  correctBook = book.bookIndex;
  correctChapter = chapter.chapterIndex;
  correctBookTitle = book.bookTitle;
  correctChapterTitle = chapter.title;

  // 3. load chapter file
  const folder = String(book.bookIndex).padStart(2, "0");
  const res = await fetch(`${folder}/${chapter.id}.json`);
  const data = await res.json();

  // 4. random paragraph
  const paragraph =
    data.paragraphs[Math.floor(Math.random() * data.paragraphs.length)];
  currentParagraph = paragraph;

  showParagraph();
}

// Show paragraph + book buttons
function showParagraph() {
  gameDiv.innerHTML = `<p>${currentParagraph}</p>`;

  books.forEach((book) => {
    const btn = document.createElement("button");
    btn.textContent = `Book ${book.bookIndex}`;
    btn.onclick = () => selectBook(book);
    gameDiv.appendChild(btn);
  });
}

function selectBook(book) {
  if (book.bookIndex !== correctBook) {
    showCorrectAnswer();
    return;
  }

  showChapters(book);
}

// Show chapters
function showChapters(book) {
  gameDiv.innerHTML = `<p>${currentParagraph}</p>`;

  book.chapters.forEach((ch) => {
    const btn = document.createElement("button");
    btn.textContent = `${ch.title} - ${String(ch.chapterIndex).padStart(2, "0")}`;
    btn.onclick = () => selectChapter(ch);
    gameDiv.appendChild(btn);
  });
}

function selectChapter(chapter) {
  if (chapter.chapterIndex === correctChapter) {
    score++;
    startRound();
  } else {
    showCorrectAnswer();
  }
}


function showCorrectAnswer() {
  gameDiv.innerHTML = `
    <h2>Wrong!</h2>
    <p>Correct book: ${correctBookTitle}</p>
    <p>Correct chapter: ${correctChapterTitle} - ${String(correctChapter).padStart(2, "0")}</p>
    <p>Final score: ${score}</p>
  `;

  setTimeout(() => {
    endGame();
  }, 5000);
}

function endGame() {
  gameDiv.innerHTML = `<h2>Game Over</h2><p>Score: ${score}</p>`;
}

// Start
loadIndex();
