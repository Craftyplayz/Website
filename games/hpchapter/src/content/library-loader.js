import { parseEpub } from './epub-parser.js';

export async function loadLibrary(books, {
  fetchImpl = globalThis.fetch,
  resolveAsset = assetPath => assetPath,
  JSZipLibrary = globalThis.JSZip,
  DOMParserClass = globalThis.DOMParser,
  onProgress = () => {}
} = {}) {
  let completed = 0;
  const results = await Promise.all(books.map(async book => {
    let result;
    try {
      const response = await fetchImpl(resolveAsset(book.assetPath));
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.arrayBuffer();
      const parsedBook = await parseEpub(data, book, { JSZipLibrary, DOMParserClass });
      result = { book: parsedBook, error: null };
    } catch (error) {
      result = { book: null, error: error instanceof Error ? error : new Error(String(error)) };
    }
    completed += 1;
    onProgress({ completed, total: books.length, book, ...result });
    return { book, ...result };
  }));

  return {
    books: results.flatMap(result => result.book ? [result.book] : []),
    failures: results.filter(result => result.error).map(({ book, error }) => ({ book, error }))
  };
}
