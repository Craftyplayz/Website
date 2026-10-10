import { shuffle } from './shuffle.js';
import { isEligibleParagraph, normalizeParagraph } from '../content/epub-parser.js';

export function createQuestionPool(books, random = Math.random) {
  const questions = [];
  const seenPassages = new Set();

  for (const book of books) {
    const chapterChoices = book.chapters.map(chapter => ({ id: chapter.id, title: chapter.title }));
    for (const chapter of book.chapters) {
      for (const paragraph of chapter.paragraphs) {
        const text = normalizeParagraph(typeof paragraph === 'string' ? paragraph : paragraph.text);
        if (!isEligibleParagraph(text) || seenPassages.has(text)) continue;
        seenPassages.add(text);
        questions.push({
          id: paragraph.id ?? `${chapter.id}:p${questions.length + 1}`,
          passage: text,
          bookId: book.id,
          chapterId: chapter.id,
          chapterTitle: chapter.title,
          chapterChoices
        });
      }
    }
  }

  return shuffle(questions, random);
}
