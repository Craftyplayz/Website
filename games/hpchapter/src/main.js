import { GameController } from './application/game-controller.js';
import { createHighScoreStore } from './services/high-scores.js';
import { GameView } from './ui/game-view.js';

const view = new GameView(document, {
  onBookAnswer: bookId => controller.answerBook(bookId),
  onChapterAnswer: chapterId => controller.answerChapter(chapterId),
  onRetry: () => controller.load(),
  onRestart: () => controller.restart()
});
const controller = new GameController(view, {
  highScoreStore: createHighScoreStore(() => window.localStorage),
  document
});

controller.load();
