import { HIGH_SCORE_STORAGE_KEY } from '../config/settings.js';

function validScore(value) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  return Number.isSafeInteger(parsed) && parsed >= 0 ? parsed : 0;
}

export function createHighScoreStore(storageProvider = () => globalThis.localStorage) {
  let best = 0;

  return {
    read() {
      try {
        const storage = typeof storageProvider === 'function' ? storageProvider() : storageProvider;
        best = validScore(storage?.getItem(HIGH_SCORE_STORAGE_KEY));
      } catch {
        best = 0;
      }
      return best;
    },
    record(score) {
      const candidate = validScore(score);
      if (candidate <= best) return { isRecord: false, highScore: best };
      best = candidate;
      try {
        const storage = typeof storageProvider === 'function' ? storageProvider() : storageProvider;
        storage?.setItem(HIGH_SCORE_STORAGE_KEY, String(candidate));
      } catch {
        // Keep the in-memory best for this page even if browser storage is unavailable.
      }
      return { isRecord: true, highScore: best };
    }
  };
}
