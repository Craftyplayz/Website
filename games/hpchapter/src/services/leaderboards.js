export function leaderboardRows(entries) {
  return entries.map((entry, index) => ({
    rank: index + 1, name: String(entry.name ?? ''),
    score: String(entry.score ?? 0),
    elapsed: `${(Number(entry.elapsedMs || 0) / 1000).toFixed(2)}s`,
    date: String(entry.achievedAt ?? ''),
    books: Array.isArray(entry.selectedBooks) ? entry.selectedBooks.join(', ') : '',
    contextLimit: Number.isInteger(entry.contextLimit) && entry.contextLimit > 0
      ? `${entry.contextLimit} characters` : 'Not recorded',
    duration: Number.isInteger(entry.duration) && entry.duration > 0
      ? `${entry.duration}s` : 'Not recorded'
  }));
}

export function globalRank(entries, result) {
  const id = result?.id ?? result?.entryId;
  if (id != null) {
    const index = entries.findIndex(entry => String(entry.id) === String(id));
    if (index >= 0) return index + 1;
  }
  return Number.isInteger(result?.rank) && result.rank > 0 ? result.rank : null;
}
