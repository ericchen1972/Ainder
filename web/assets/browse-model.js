export function wrapIndex(index, total) {
  if (!Number.isInteger(total) || total < 1) return 0;
  return ((index % total) + total) % total;
}

export function candidateStepForDrag(deltaX, threshold = 64) {
  if (deltaX <= -threshold) return 1;
  if (deltaX >= threshold) return -1;
  return 0;
}

export function photoIndexAfterStep(index, step, total) {
  return wrapIndex(index + step, total);
}
