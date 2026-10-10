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
    let parsedBook = null;
    let error = null;
    try {
      const response = await fetchImpl(resolveAsset(book.assetPath));
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.arrayBuffer();
      parsedBook = await parseEpub(data, book, { JSZipLibrary, DOMParserClass });
    } catch (loadError) {
      error = loadError instanceof Error ? loadError : new Error(String(loadError));
    }
    completed += 1;
    const result = { book, parsedBook, error };
    onProgress({ completed, total: books.length, ...result });
    return result;
  }));

  return {
    books: results.flatMap(result => result.parsedBook ? [result.parsedBook] : []),
    failures: results.filter(result => result.error).map(({ book, error }) => ({ book, error }))
  };
}
