function decodeEntities(text) {
  return text.replace(/&(#x[\da-f]+|#\d+|amp|lt|gt|quot|apos);/gi, (_, entity) => {
    if (entity[0] === '#') {
      const code = entity[1].toLowerCase() === 'x'
        ? Number.parseInt(entity.slice(2), 16)
        : Number.parseInt(entity.slice(1), 10);
      return String.fromCodePoint(code);
    }
    return { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" }[entity.toLowerCase()];
  });
}

class FixtureElement {
  constructor(name, attributes = {}) {
    this.nodeName = name;
    this.localName = name.split(':').pop();
    this.attributes = attributes;
    this.contents = [];
  }

  getAttribute(name) {
    return this.attributes[name] ?? null;
  }

  get textContent() {
    return this.contents.map(content => typeof content === 'string' ? content : content.textContent).join('');
  }

  getElementsByTagName(name) {
    const descendants = [];
    for (const content of this.contents) {
      if (typeof content === 'string') continue;
      if (name === '*' || content.nodeName === name || content.localName === name) descendants.push(content);
      descendants.push(...content.getElementsByTagName(name));
    }
    return descendants;
  }
}

class FixtureDocument {
  constructor(root) {
    this.documentElement = root;
  }

  getElementsByTagName(name) {
    const matches = name === '*' || this.documentElement.nodeName === name
      || this.documentElement.localName === name ? [this.documentElement] : [];
    return [...matches, ...this.documentElement.getElementsByTagName(name)];
  }
}

export class FixtureDOMParser {
  parseFromString(source, mimeType) {
    const isXml = mimeType !== 'text/html';
    const root = new FixtureElement(isXml ? 'document' : 'html');
    const stack = [root];
    const tokens = source.match(/<!--[\s\S]*?-->|<!\[CDATA\[[\s\S]*?\]\]>|<\?[\s\S]*?\?>|<![^>]*>|<\/?[^>]+>|[^<]+/g) ?? [];
    const reconstructed = tokens.join('');
    let malformed = isXml && reconstructed.replace(/\s/g, '') !== source.replace(/\s/g, '');

    for (const token of tokens) {
      if (token.startsWith('<!--') || token.startsWith('<?') || /^<!doctype/i.test(token)) continue;
      if (token.startsWith('<![CDATA[')) {
        stack.at(-1).contents.push(token.slice(9, -3));
        continue;
      }
      if (token.startsWith('</')) {
        const name = token.slice(2, -1).trim().split(':').pop();
        const index = stack.map(element => element.localName).lastIndexOf(name);
        if (index <= 0) {
          if (isXml) malformed = true;
        } else {
          if (isXml && index !== stack.length - 1) malformed = true;
          stack.length = index;
        }
        continue;
      }
      if (token.startsWith('<')) {
        if (/^<!/.test(token)) continue;
        const match = token.match(/^<([^\s/>]+)([\s\S]*?)\/?>$/);
        if (!match) {
          if (isXml) malformed = true;
          continue;
        }
        const attributes = {};
        for (const attribute of match[2].matchAll(/([^\s=]+)\s*=\s*(?:"([^"]*)"|'([^']*)')/g)) {
          attributes[attribute[1]] = decodeEntities(attribute[2] ?? attribute[3] ?? '');
        }
        const element = new FixtureElement(match[1], attributes);
        stack.at(-1).contents.push(element);
        if (!/\/\s*>$/.test(token)) stack.push(element);
        continue;
      }
      stack.at(-1).contents.push(decodeEntities(token));
    }

    if (isXml && stack.length !== 1) malformed = true;
    if (malformed) {
      const error = new FixtureElement('parsererror');
      return new FixtureDocument(error);
    }
    if (isXml && root.contents.length === 1 && typeof root.contents[0] !== 'string') {
      return new FixtureDocument(root.contents[0]);
    }
    return new FixtureDocument(root);
  }
}

export function makeSyntheticEpub({ navigation = 'epub3', malformedPackage = false } = {}) {
  const paragraph = 'A'.repeat(120);
  const longParagraph = 'B'.repeat(2500);
  const tooLong = 'C'.repeat(2501);
  const nav = navigation === 'broken'
    ? '<broken'
    : '<html xmlns="http://www.w3.org/1999/xhtml"><body><nav><a href="../Text/one.xhtml#part"> Chapter One </a><a href="../Text/nested/two.xhtml">Chapter Two</a></nav></body></html>';
  const files = {
    'META-INF/container.xml': '<container><rootfiles><rootfile full-path="OPS/package.opf"/></rootfiles></container>',
    'OPS/package.opf': malformedPackage ? '<package><manifest>' : `<package><manifest>
      <item id="nav" href="nav/nav.xhtml" properties="nav" media-type="application/xhtml+xml"/>
      <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
      <item id="chapter-one" href="Text/one.xhtml" media-type="application/xhtml+xml"/>
      <item id="chapter-two" href="Text/nested/two.xhtml" media-type="application/xhtml+xml"/>
      </manifest><spine><itemref idref="chapter-one"/><itemref idref="chapter-two"/></spine></package>`,
    'OPS/nav/nav.xhtml': nav,
    'OPS/toc.ncx': `<ncx><navMap>
      <navPoint><navLabel><text>Fallback One</text></navLabel><content src="../OPS/Text/one.xhtml"/></navPoint>
      <navPoint><navLabel><text>Fallback Two</text></navLabel><content src="../OPS/Text/nested/two.xhtml"/></navPoint>
      </navMap></ncx>`,
    'OPS/Text/one.xhtml': `<html><body><p>${paragraph}</p><p> ${paragraph} </p><p>${longParagraph}</p><p>${tooLong}</p></body></html>`,
    'OPS/Text/nested/two.xhtml': `<html><body><p>${paragraph}</p><p>${'D'.repeat(121)}</p></body></html>`
  };
  return {
    paragraph,
    files,
    data: Object.keys(files),
    JSZipLibrary: {
      loadAsync: async () => ({
        file(path) {
          if (!(path in files)) return null;
          return { async: async () => files[path] };
        }
      })
    }
  };
}
