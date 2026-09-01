import assert from 'node:assert/strict';
import test from 'node:test';

import {
  appendPendingPhoto,
  createProfilePhotoState,
  replaceProfilePhoto,
} from '../web/assets/profile-editor-model.js';

const file = (name) => ({ name, type: 'image/webp' });

test('plus appends the next photo through six', () => {
  let state = createProfilePhotoState(['/1.webp', '/2.webp']);
  state = appendPendingPhoto(state, file('3.webp'));
  assert.equal(state.slots.length, 3);
  assert.equal(state.nextAddSlot, 4);
});

test('replacement preserves the selected position', () => {
  const state = replaceProfilePhoto(
    createProfilePhotoState(['/1.webp', '/2.webp']),
    1,
    file('main.webp'),
  );
  assert.equal(state.slots[0].upload.name, 'main.webp');
  assert.equal(state.slots[1].path, '/2.webp');
});

test('a sixth photo removes the plus state and no delete operation exists', () => {
  const state = createProfilePhotoState(['/1', '/2', '/3', '/4', '/5', '/6']);
  assert.equal(state.nextAddSlot, null);
  assert.equal(Object.hasOwn(state, 'removeProfilePhoto'), false);
});

test('replacement and append changes serialize in slot order', async () => {
  let state = createProfilePhotoState(['/1.webp', '/2.webp']);
  state = appendPendingPhoto(state, file('3.webp'));
  state = replaceProfilePhoto(state, 1, file('main.webp'));
  assert.deepEqual(
    state.slots.filter((slot) => slot.upload).map((slot) => slot.position),
    [1, 3],
  );
});
