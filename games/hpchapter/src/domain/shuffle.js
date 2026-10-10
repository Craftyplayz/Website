export function shuffle(items, random = Math.random) {
  const shuffled = [...items];
  for (let index = shuffled.length - 1; index > 0; index -= 1) {
    const sample = Number(random());
    const safeSample = Number.isFinite(sample)
      ? Math.max(0, Math.min(1 - Number.EPSILON, sample))
      : 0;
    const otherIndex = Math.floor(safeSample * (index + 1));
    [shuffled[index], shuffled[otherIndex]] = [shuffled[otherIndex], shuffled[index]];
  }
  return shuffled;
}
