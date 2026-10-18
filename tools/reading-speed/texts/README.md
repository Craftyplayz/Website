# Reading speed test texts

Folder layout:

```
texts/
  index.json              <- list of every language folder and its texts
  English/
    Harry Potter Chapter 1.json
  Spanish/
    El Faro.json
```

- The **folder name** is the language shown in the dropdown.
- The **file name** (without `.json`) is the text name shown in the dropdown.

## Adding a text

1. Create `texts/<Language>/<Name>.json`.
2. Add `"<Name>"` to that language's list in `texts/index.json`
   (add a new `"<Language>": [ ... ]` entry for a new language).

Plain HTML/JS can't list folders on the server, so a text that isn't in
`index.json` won't show up.

## Text file format

```json
{
    "author": "Author Name",
    "source": "Book / chapter / website (optional)",
    "lang": "en",
    "wordCount": 120,
    "paragraphs": [
        "First paragraph.",
        "Second paragraph."
    ]
}
```

| Field        | Required | Notes |
|--------------|----------|-------|
| `paragraphs` | yes      | One string per paragraph. A single `"text"` string also works; blank lines split it into paragraphs. |
| `author`     | no       | Shown under the title. |
| `source`     | no       | Shown under the title. |
| `wordCount`  | no       | Used for the WPM maths. If left out, words are counted automatically by splitting on spaces. Set it yourself for languages without spaces (Chinese, Japanese, Thai...). |
| `lang`       | no       | Language code (`en`, `es`, `fr`, `ar`...). Helps the browser with fonts/hyphenation. |
| `direction`  | no       | `"rtl"` for right-to-left languages such as Arabic or Hebrew. |

Quotes inside text must be escaped as `\"` (or use curly quotes “ ” which need no escaping).
