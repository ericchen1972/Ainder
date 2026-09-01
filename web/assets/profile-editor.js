import { preprocessProfilePhoto } from './client-photo-processor.js';
import {
  appendPendingPhoto,
  createProfilePhotoState,
  replaceProfilePhoto,
} from './profile-editor-model.js';

const modal = document.querySelector('.profile-editor-modal');
const form = modal?.querySelector('.profile-editor-form');
const nameInput = form?.querySelector('input[name="display_name"]');
const photoInput = form?.querySelector('[data-profile-photo-input]');
const photoGrid = form?.querySelector('.profile-photo-grid');
const errorTarget = form?.querySelector('.profile-editor-error');
const saveButton = form?.querySelector('.profile-editor-save');

if (modal && form && nameInput && photoInput && photoGrid && errorTarget && saveButton) {
  let committedName = nameInput.value;
  let committedPaths = [...photoGrid.querySelectorAll('[data-profile-photo-slot] img')]
    .map((image) => image.getAttribute('src') ?? '');
  let state = createProfilePhotoState(committedPaths);
  let pendingAction = null;
  let objectUrls = [];
  let busy = false;
  let opener = null;

  const setError = (message = '') => {
    errorTarget.textContent = message;
    errorTarget.hidden = message === '';
  };

  const setBusy = (nextBusy) => {
    busy = nextBusy;
    saveButton.disabled = busy;
    saveButton.textContent = busy ? 'Saving…' : 'Save changes';
    photoInput.disabled = busy;
  };

  const clearObjectUrls = () => {
    objectUrls.forEach((url) => URL.revokeObjectURL(url));
    objectUrls = [];
  };

  const renderPhotos = () => {
    clearObjectUrls();
    photoGrid.replaceChildren();
    state.slots.forEach((slot) => {
      const button = document.createElement('button');
      button.className = 'profile-photo-slot';
      button.type = 'button';
      button.dataset.profilePhotoSlot = String(slot.position);
      button.setAttribute('aria-label', `Replace photo ${slot.position}`);
      const image = document.createElement('img');
      if (slot.upload) {
        const url = URL.createObjectURL(slot.upload);
        objectUrls.push(url);
        image.src = url;
      } else {
        image.src = slot.path;
      }
      image.alt = `Profile photo ${slot.position}`;
      button.append(image);
      if (slot.position === 1) {
        const main = document.createElement('span');
        main.className = 'profile-photo-main';
        main.textContent = 'Main';
        button.append(main);
      }
      button.addEventListener('click', () => {
        if (busy) return;
        pendingAction = { type: 'replace', position: slot.position };
        photoInput.click();
      });
      photoGrid.append(button);
    });

    if (state.nextAddSlot !== null) {
      const add = document.createElement('button');
      add.className = 'profile-photo-add';
      add.type = 'button';
      add.textContent = '＋';
      add.setAttribute('aria-label', `Add photo ${state.nextAddSlot}`);
      add.addEventListener('click', () => {
        if (busy) return;
        pendingAction = { type: 'append' };
        photoInput.click();
      });
      photoGrid.append(add);
    }
  };

  const resetDraft = () => {
    state = createProfilePhotoState(committedPaths);
    nameInput.value = committedName;
    pendingAction = null;
    setError();
    renderPhotos();
  };

  document.querySelectorAll('.profile-open').forEach((button) => {
    button.addEventListener('click', () => {
      opener = button;
      resetDraft();
      modal.showModal();
      nameInput.focus();
      nameInput.select();
    });
  });

  modal.querySelector('.profile-editor-close')?.addEventListener('click', () => {
    modal.close();
  });
  modal.addEventListener('close', () => {
    resetDraft();
    opener?.focus();
  });

  photoInput.addEventListener('change', async () => {
    const file = photoInput.files?.[0] ?? null;
    photoInput.value = '';
    if (!file || !pendingAction) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setError('Choose a JPG, PNG, or WebP image.');
      return;
    }

    setBusy(true);
    setError();
    try {
      const processed = await preprocessProfilePhoto(file);
      state = pendingAction.type === 'replace'
        ? replaceProfilePhoto(state, pendingAction.position, processed)
        : appendPendingPhoto(state, processed);
      renderPhotos();
    } catch {
      setError('This photo could not be processed.');
    } finally {
      pendingAction = null;
      setBusy(false);
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    const displayName = nameInput.value.trim();
    if (displayName === '' || displayName.length > 120) {
      setError('Name must contain 1–120 characters.');
      return;
    }

    const body = new FormData();
    body.append('display_name', displayName);
    body.append('csrf_token', form.elements.csrf_token.value);
    state.slots.filter((slot) => slot.upload).forEach((slot) => {
      body.append('photo_slots[]', String(slot.position));
      body.append('photos[]', slot.upload, slot.upload.name);
    });

    setBusy(true);
    setError();
    try {
      const response = await fetch('/ainder/api/profile/update.php', {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result.error?.message ?? 'Profile was not updated.');
      }
      committedName = result.profile.display_name;
      committedPaths = [...result.profile.photos];
      document.querySelectorAll('[data-member-avatar]').forEach((image) => {
        image.src = result.profile.avatar_path;
      });
      nameInput.value = committedName;
      state = createProfilePhotoState(committedPaths);
      renderPhotos();
      modal.close();
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Profile was not updated.');
    } finally {
      setBusy(false);
    }
  });

  renderPhotos();
}
