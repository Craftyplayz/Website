import { BOOKS } from '../config/books.js';

export const MODES = Object.freeze([
  { id: 'normal', title: 'Normal', symbol: '✦' },
  { id: 'timed', title: 'Timed', symbol: '◷' },
  { id: 'minimum', title: 'Minimum context', symbol: '¶' },
  { id: 'specific', title: 'Specific books', symbol: '▤' }
]);
export const DEFAULT_CONFIG = Object.freeze({ duration: 60, contextLimit: 150 });
export const SPECIFIC_NOTE = 'One shared Specific leaderboard. Selected books change the passage pool, so runs are not directly comparable.';
export const RANKING_NOTE = 'Ranked by score (highest first), elapsed time (shortest first), date (earliest first), then entry ID.';

export function validateServerConfig(config) {
  return config !== null && typeof config === 'object' && !Array.isArray(config)
    && Object.keys(config).length === 2
    && Number.isInteger(config.duration) && config.duration >= 10 && config.duration <= 600
    && Number.isInteger(config.contextLimit) && config.contextLimit >= 60 && config.contextLimit <= 500;
}

export function modeDescription(mode, config = DEFAULT_CONFIG) {
  const base = 'Identify the book, then the chapter. Correct chapter: +1. Wrong chapter: continue; wrong book: game over. Passages never repeat.';
  return `${base} ${{
    normal: 'All seven books, with no time limit.',
    timed: `${config.duration} seconds, starting with the first playable passage.`,
    minimum: `All seven books; at most ${config.contextLimit} Unicode characters of context.`,
    specific: 'Choose one or more books, with no time limit.'
  }[mode] ?? ''}`;
}

export function validateName(value) {
  const raw = String(value ?? '');
  const name = raw.trim();
  if (/[\p{Cc}\p{Cf}]/u.test(raw)) return { error: 'Names cannot contain control or invisible formatting characters.' };
  if ([...name].length < 1 || [...name].length > 20) return { error: 'Enter a public name of 1–20 Unicode characters.' };
  return { name };
}

export function validateSelection(mode, books) {
  if (!MODES.some(item => item.id === mode)) return 'Choose a game mode.';
  if (mode === 'specific' && (!books.length || books.some(id => !BOOKS.some(book => book.id === id)))) {
    return 'Select at least one book.';
  }
  return '';
}

export function minimumContext(text, limit) {
  return [...String(text)].slice(0, limit).join('');
}
