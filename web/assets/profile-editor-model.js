const PHOTO_LIMIT = 6;

function withNextAddSlot(slots) {
  return {
    slots,
    nextAddSlot: slots.length < PHOTO_LIMIT ? slots.length + 1 : null,
  };
}

export function createProfilePhotoState(paths) {
  if (!Array.isArray(paths) || paths.length < 2 || paths.length > PHOTO_LIMIT) {
    throw new RangeError('Profile requires two through six photos.');
  }
  return withNextAddSlot(paths.map((path, index) => ({
    position: index + 1,
    path: String(path),
    upload: null,
  })));
}

export function replaceProfilePhoto(state, position, upload) {
  if (!Number.isInteger(position) || position < 1 || position > state.slots.length) {
    throw new RangeError('Photo position is unavailable.');
  }
  return withNextAddSlot(state.slots.map((slot) => (
    slot.position === position ? { ...slot, upload } : { ...slot }
  )));
}

export function appendPendingPhoto(state, upload) {
  if (state.nextAddSlot === null) {
    throw new RangeError('Profile already has six photos.');
  }
  return withNextAddSlot([
    ...state.slots.map((slot) => ({ ...slot })),
    {
      position: state.nextAddSlot,
      path: '',
      upload,
    },
  ]);
}
