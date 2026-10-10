const titles = [
  "Philosopher's Stone",
  'Chamber of Secrets',
  'Prisoner of Azkaban',
  'Goblet of Fire',
  'Order of the Phoenix',
  'Half-Blood Prince',
  'Deathly Hallows'
];

export const BOOKS = Object.freeze(titles.map((shortTitle, index) => Object.freeze({
  id: `hp-${index + 1}`,
  seriesId: 'harry-potter',
  seriesTitle: 'Harry Potter',
  shortTitle,
  title: `Harry Potter and the ${shortTitle}`,
  assetPath: `books/book${index + 1}.epub`,
  order: index + 1
})));

export const BOOK_BY_ID = new Map(BOOKS.map(book => [book.id, book]));
