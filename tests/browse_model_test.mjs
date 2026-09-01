import test from 'node:test';
import assert from 'node:assert/strict';
import {
  wrapIndex,
  candidateStepForDrag,
  photoIndexAfterStep,
} from '../web/assets/browse-model.mjs';

test('candidate indices wrap at both ends', () => {
  assert.equal(wrapIndex(10, 10), 0);
  assert.equal(wrapIndex(-1, 10), 9);
  assert.equal(wrapIndex(3, 10), 3);
});

test('left drag means next and right drag means previous', () => {
  assert.equal(candidateStepForDrag(-80, 64), 1);
  assert.equal(candidateStepForDrag(80, 64), -1);
  assert.equal(candidateStepForDrag(20, 64), 0);
});

test('photo navigation wraps without changing candidates', () => {
  assert.equal(photoIndexAfterStep(1, 1, 2), 0);
  assert.equal(photoIndexAfterStep(0, -1, 2), 1);
});
