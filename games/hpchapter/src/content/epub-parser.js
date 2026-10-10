import { PARAGRAPH_MAX_LENGTH, PARAGRAPH_MIN_LENGTH } from '../config/settings.js';

export function normalizeParagraph(text) {
  return String(text ?? '').replace(/\s+/g, ' ').trim();
}

export function isEligibleParagraph(text) {
  const length = normalizeParagraph(text).length;
  return length >= PARAGRAPH_MIN_LENGTH && length <= PARAGRAPH_MAX_LENGTH;
}

function archivePath(path) {
  const parts = [];
  for (const part of path.split('/')) {
    if (!part || part === '.') continue;
    if (part === '..') {
      parts.pop();
      continue;
    }
    try {
      parts.push(decodeURIComponent(part));
    } catch {
      parts.push(part);
    }
  }
  return parts.join('/');
}

export function resolveArchivePath(baseFilePath, reference) {
  const path = String(reference ?? '').split(/[?#]/, 1)[0];
  if (!path) return '';
  const base = path.startsWith('/') ? '' : String(baseFilePath ?? '').split('/').slice(0, -1).join('/');
  return archivePath(`${base}/${path}`);
}

function localName(element) {
  return String(element.localName || element.nodeName || '').split(':').pop().toLowerCase();
}

function elementsByName(document, name) {
  return Array.from(document.getElementsByTagName('*')).filter(element => localName(element) === name);
}

function firstDescendant(element, name) {
  return Array.from(element.getElementsByTagName('*')).find(child => localName(child) === name) ?? null;
}

function attribute(element, name) {
  return element?.getAttribute(name) ?? '';
}

function parseMarkup(source, mimeType, Parser) {
  const document = new Parser().parseFromString(source, mimeType);
  const rootName = localName(document.documentElement ?? {});
  const hasParserError = rootName === 'parsererror'
    || elementsByName(document, 'parsererror').length > 0;
  return hasParserError ? null : document;
}

function parseXml(source, Parser, { htmlFallback = false } = {}) {
  const document = parseMarkup(source, 'application/xml', Parser);
  if (document) return document;
  if (htmlFallback) {
    const htmlDocument = parseMarkup(source, 'text/html', Parser);
    if (htmlDocument) return htmlDocument;
  }
  throw new Error('The EPUB contains malformed XML.');
}

function addTitle(titleMap, path, title) {
  if (!path || !title) return;
  titleMap[path] = title;
  titleMap[path.split('/').pop()] = title;
}

function chapterTitlesFromNavigation(zip, manifest, opfPath, Parser) {
  const opfDirectory = opfPath.includes('/') ? opfPath.slice(0, opfPath.lastIndexOf('/') + 1) : '';
  const titleMap = {};
  const navItem = Object.values(manifest).find(item => item.properties.split(/\s+/).includes('nav'));

  if (navItem) {
    const navPath = resolveArchivePath(opfPath, navItem.href);
    const navSource = zip.file(navPath);
    if (navSource) {
      const navDocument = parseXml(navSource, Parser, { htmlFallback: true });
      for (const link of elementsByName(navDocument, 'a')) {
        const href = attribute(link, 'href');
        const title = normalizeParagraph(link.textContent);
        if (/^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(href)) continue;
        const resolved = resolveArchivePath(navPath, href);
        const relative = resolved.startsWith(opfDirectory) ? resolved.slice(opfDirectory.length) : resolved;
        addTitle(titleMap, relative, title);
        addTitle(titleMap, resolved, title);
      }
    }
  }

  if (Object.keys(titleMap).length === 0) {
    const ncxItem = Object.values(manifest).find(item => item.mediaType === 'application/x-dtbncx+xml');
    if (ncxItem) {
      const ncxPath = resolveArchivePath(opfPath, ncxItem.href);
      const ncxSource = zip.file(ncxPath);
      if (ncxSource) {
        const ncxDocument = parseXml(ncxSource, Parser, { htmlFallback: true });
        for (const point of elementsByName(ncxDocument, 'navpoint')) {
          const content = firstDescendant(point, 'content');
          const label = firstDescendant(point, 'navlabel');
          const labelText = label && firstDescendant(label, 'text');
          const href = attribute(content, 'src');
          const title = normalizeParagraph(labelText?.textContent);
          if (!href || !title) continue;
          const resolved = resolveArchivePath(ncxPath, href);
          const relative = resolved.startsWith(opfDirectory) ? resolved.slice(opfDirectory.length) : resolved;
          addTitle(titleMap, relative, title);
          addTitle(titleMap, resolved, title);
        }
      }
    }
  }
  return titleMap;
}

export async function parseEpub(data, book, {
  JSZipLibrary = globalThis.JSZip,
  DOMParserClass = globalThis.DOMParser
} = {}) {
  if (!JSZipLibrary?.loadAsync) throw new Error('The EPUB archive library is unavailable.');
  if (!DOMParserClass) throw new Error('This browser does not provide an XML parser.');

  const zip = await JSZipLibrary.loadAsync(data);
  const containerFile = zip.file('META-INF/container.xml');
  if (!containerFile) throw new Error('The EPUB has no META-INF/container.xml file.');
  const containerDocument = parseXml(await containerFile.async('text'), DOMParserClass);
  const rootfile = elementsByName(containerDocument, 'rootfile')[0];
  const opfPath = attribute(rootfile, 'full-path');
  if (!opfPath) throw new Error('The EPUB container does not identify its OPF package.');

  const opfFile = zip.file(archivePath(opfPath));
  if (!opfFile) throw new Error(`The EPUB package file "${opfPath}" is missing.`);
  const opfDocument = parseXml(await opfFile.async('text'), DOMParserClass);
  const manifest = {};
  for (const item of elementsByName(opfDocument, 'item')) {
    const id = attribute(item, 'id');
    if (!id) continue;
    manifest[id] = {
      href: attribute(item, 'href'),
      mediaType: attribute(item, 'media-type'),
      properties: attribute(item, 'properties')
    };
  }

  const titleMap = chapterTitlesFromNavigation(zip, manifest, opfPath, DOMParserClass);
  const opfDirectory = opfPath.includes('/') ? opfPath.slice(0, opfPath.lastIndexOf('/') + 1) : '';
  const spine = elementsByName(opfDocument, 'itemref');
  const chapters = [];

  for (const [spineIndex, reference] of spine.entries()) {
    const itemId = attribute(reference, 'idref');
    const item = manifest[itemId];
    if (!item || !item.mediaType.includes('html')) continue;

    const fullPath = resolveArchivePath(opfPath, item.href);
    const basename = item.href.split('/').pop();
    const chapterTitle = titleMap[item.href] || titleMap[fullPath]
      || titleMap[basename] || titleMap[fullPath.split('/').pop()];
    if (!chapterTitle) continue;

    const chapterFile = zip.file(fullPath);
    if (!chapterFile) continue;
    const source = await chapterFile.async('text');
    const chapterDocument = parseXml(source, DOMParserClass, { htmlFallback: true });
    const seen = new Set();
    const paragraphs = [];
    for (const [paragraphIndex, paragraph] of elementsByName(chapterDocument, 'p').entries()) {
      const text = normalizeParagraph(paragraph.textContent);
      if (!isEligibleParagraph(text) || seen.has(text)) continue;
      seen.add(text);
      paragraphs.push({
        id: `${book.id}:${itemId || spineIndex}:p${paragraphIndex + 1}`,
        text
      });
    }

    if (paragraphs.length > 0) {
      chapters.push({
        id: `${book.id}:${itemId || spineIndex}`,
        title: chapterTitle,
        href: fullPath,
        paragraphs
      });
    }
  }

  return { ...book, chapters };
}
