export class GameSession {
  constructor(questions) {
    this.questions = [...questions];
    this.score = 0;
    this.currentQuestion = null;
    this.state = 'ready';
    this.pendingOutcome = null;
    this.result = null;
  }

  start() {
    if (this.state !== 'ready') return this.rejected('invalid-state');
    return this.selectNextQuestion();
  }

  answerBook(bookId) {
    if (this.state !== 'book-selection') return this.rejected('invalid-state');
    const correct = bookId === this.currentQuestion.bookId;
    this.state = 'answer-feedback';
    this.pendingOutcome = correct ? 'book-correct' : 'book-incorrect';
    return {
      accepted: true,
      correct,
      outcome: this.pendingOutcome,
      correctBookId: this.currentQuestion.bookId,
      question: this.currentQuestion,
      score: this.score
    };
  }

  beginChapterSelection() {
    if (this.state !== 'answer-feedback' || this.pendingOutcome !== 'book-correct') {
      return this.rejected('invalid-state');
    }
    this.pendingOutcome = null;
    this.state = 'chapter-selection';
    return { accepted: true, question: this.currentQuestion, score: this.score };
  }

  answerChapter(chapterId) {
    if (this.state !== 'chapter-selection') return this.rejected('invalid-state');
    const correct = chapterId === this.currentQuestion.chapterId;
    if (correct) this.score += 1;
    this.state = 'answer-feedback';
    this.pendingOutcome = correct ? 'chapter-correct' : 'chapter-incorrect';
    return {
      accepted: true,
      correct,
      outcome: this.pendingOutcome,
      correctChapterId: this.currentQuestion.chapterId,
      question: this.currentQuestion,
      score: this.score
    };
  }

  advance() {
    if (this.state !== 'answer-feedback') return this.rejected('invalid-state');
    if (this.pendingOutcome === 'book-incorrect') {
      this.state = 'book-failed';
      this.result = this.makeResult('incorrect-book', this.currentQuestion);
      return { accepted: true, completed: true, result: this.result };
    }
    if (this.pendingOutcome !== 'chapter-correct' && this.pendingOutcome !== 'chapter-incorrect') {
      return this.rejected('invalid-transition');
    }
    this.pendingOutcome = null;
    return this.selectNextQuestion();
  }

  selectNextQuestion() {
    if (this.questions.length === 0) {
      this.state = 'completed';
      this.result = this.makeResult('pool-exhausted', null);
      return { accepted: true, completed: true, result: this.result };
    }
    this.currentQuestion = this.questions.shift();
    this.state = 'book-selection';
    return { accepted: true, completed: false, question: this.currentQuestion, score: this.score };
  }

  snapshot() {
    return {
      state: this.state,
      score: this.score,
      currentQuestion: this.currentQuestion,
      remainingQuestions: this.questions.length,
      result: this.result
    };
  }

  rejected(reason) {
    return { accepted: false, reason, score: this.score };
  }

  makeResult(reason, question) {
    return { reason, score: this.score, question };
  }
}
