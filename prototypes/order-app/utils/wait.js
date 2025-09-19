/**
 * Возвращает промис, который резолвится через указанное время.
 * @param {number} ms
 */
export function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
