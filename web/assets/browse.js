import {
  wrapIndex,
  candidateStepForDrag,
  photoIndexAfterStep,
  isPhotoControlTarget,
} from 'ainder-browse-model';
import { postJson } from './webmcp-common.js';

const browser = document.querySelector('.candidate-browser');
const stack = document.querySelector('.candidate-stack');
let cards = [...document.querySelectorAll('.candidate-card')];
const status = document.querySelector('[data-candidate-status]');
let candidateIndex = 0;
let pointerStart = null;
let pointerDelta = 0;
let suppressClick = false;

function currentCard() {
  return cards[candidateIndex] ?? null;
}

function setCurrentCandidate(nextIndex, animateFrom = 0) {
  if (cards.length === 0 || !browser) return;

  candidateIndex = wrapIndex(nextIndex, cards.length);
  cards.forEach((card, index) => {
    const active = index === candidateIndex;
    card.classList.toggle('is-current', active);
    card.setAttribute('aria-hidden', String(!active));
    card.style.removeProperty('transform');
  });

  const card = currentCard();
  const id = card?.dataset.candidateId ?? '';
  browser.setAttribute('data-current-candidate-id', id);
  document.documentElement.setAttribute('data-current-candidate-id', id);

  if (status && card) {
    const name = card.querySelector('.candidate-copy h1')?.textContent?.trim() ?? '';
    status.textContent = `目前顯示 ${name}`;
  }

  if (animateFrom !== 0 && card && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    card.animate(
      [
        { transform: `translateX(${animateFrom}px)`, opacity: 0.65 },
        { transform: 'translateX(0)', opacity: 1 },
      ],
      { duration: 220, easing: 'cubic-bezier(.2,.75,.25,1)' },
    );
  }
}

function moveCandidate(step) {
  setCurrentCandidate(candidateIndex + step, step > 0 ? 80 : -80);
}

function activePhoto(card) {
  return [...card.querySelectorAll('.candidate-photo')]
    .findIndex((photo) => photo.classList.contains('is-active'));
}

function showPhoto(card, requestedIndex) {
  const photos = [...card.querySelectorAll('.candidate-photo')];
  const segments = [...card.querySelectorAll('.photo-segments i')];
  if (photos.length === 0) return;

  const index = wrapIndex(requestedIndex, photos.length);
  photos.forEach((photo, photoIndex) => {
    photo.classList.toggle('is-active', photoIndex === index);
  });
  segments.forEach((segment, photoIndex) => {
    segment.classList.toggle('is-active', photoIndex === index);
  });
}

function movePhoto(card, step) {
  const photos = card.querySelectorAll('.candidate-photo');
  showPhoto(
    card,
    photoIndexAfterStep(activePhoto(card), step, photos.length),
  );
}

function candidateSnapshot(card = currentCard()) {
  if (!card) return null;

  const photos = [
    ...card.querySelectorAll('.candidate-photo:not(.has-error)'),
  ];
  const currentPhotoIndex = photos.findIndex((photo) => (
    photo.classList.contains('is-active')
  ));

  return {
    candidate_id: Number(card.dataset.candidateId),
    display_name: card.querySelector('.candidate-name')?.textContent?.trim() ?? '',
    age: Number(card.querySelector('.candidate-age')?.textContent?.trim() ?? 0),
    current_photo_index: currentPhotoIndex >= 0 ? currentPhotoIndex + 1 : 0,
    photo_count: photos.length,
  };
}

function browseCandidates(direction) {
  moveCandidate(direction === 'previous' ? -1 : 1);
  return candidateSnapshot();
}

function showCandidate(candidateId) {
  const index = cards.findIndex((card) => (
    Number(card.dataset.candidateId) === Number(candidateId)
  ));
  if (index < 0) return null;

  setCurrentCandidate(index, 0);
  return candidateSnapshot();
}

function updateIncomingLikeEmptyState() {
  const list = document.querySelector('.agent-like-list');
  const empty = document.querySelector('.sidebar-empty');
  const hasRows = Boolean(list?.querySelector('.agent-like-row'));
  if (list) list.hidden = !hasRows;
  if (empty) empty.hidden = hasRows;
}

function removeIncomingLike(likeId) {
  document.querySelector(
    `.agent-like-row[data-like-id="${Number(likeId)}"]`,
  )?.remove();
  updateIncomingLikeEmptyState();
}

function removeCandidate(candidateId) {
  const id = Number(candidateId);
  const index = cards.findIndex((card) => Number(card.dataset.candidateId) === id);
  if (index < 0) return candidateSnapshot();

  const wasCurrent = index === candidateIndex;
  cards[index].remove();
  cards.splice(index, 1);
  document.querySelectorAll(
    `.agent-like-row[data-candidate-id="${id}"]`,
  ).forEach((row) => row.remove());
  updateIncomingLikeEmptyState();

  if (cards.length === 0) {
    candidateIndex = 0;
    stack?.setAttribute('hidden', '');
    browser?.setAttribute('data-current-candidate-id', '');
    document.documentElement.setAttribute('data-current-candidate-id', '');
    if (status) status.textContent = 'No more candidates.';
    return null;
  }

  if (index < candidateIndex) candidateIndex -= 1;
  if (candidateIndex >= cards.length) candidateIndex = 0;
  setCurrentCandidate(candidateIndex, wasCurrent ? 80 : 0);
  return candidateSnapshot();
}

function changeCandidatePhoto(direction) {
  const card = currentCard();
  if (!card) return null;

  movePhoto(card, direction === 'previous' ? -1 : 1);
  return candidateSnapshot(card);
}

function updatePhotoControls(card) {
  const availableCount = card.querySelectorAll(
    '.candidate-photo:not(.has-error)',
  ).length;
  card.querySelectorAll('.photo-control').forEach((control) => {
    control.hidden = availableCount < 2;
  });
}

document.addEventListener('keydown', (event) => {
  if (event.key === 'ArrowLeft') moveCandidate(1);
  if (event.key === 'ArrowRight') moveCandidate(-1);
});

cards.forEach((card) => {
  card.querySelector('.photo-previous')?.addEventListener('pointerdown', (event) => {
    event.stopPropagation();
  });
  card.querySelector('.photo-previous')?.addEventListener('click', (event) => {
    event.stopPropagation();
    movePhoto(card, -1);
  });
  card.querySelector('.photo-next')?.addEventListener('pointerdown', (event) => {
    event.stopPropagation();
  });
  card.querySelector('.photo-next')?.addEventListener('click', (event) => {
    event.stopPropagation();
    movePhoto(card, 1);
  });

  card.querySelectorAll('.candidate-photo img').forEach((image) => {
    image.addEventListener('dragstart', (event) => {
      event.preventDefault();
    });
    image.addEventListener('error', () => {
      image.closest('.candidate-photo')?.classList.add('has-error');
      const available = [
        ...card.querySelectorAll('.candidate-photo:not(.has-error)'),
      ];
      if (available.length > 0) {
        showPhoto(card, Number(available[0].dataset.photoIndex));
      }
      updatePhotoControls(card);
      card.classList.toggle('all-photos-failed', available.length === 0);
    });
  });
});

document.querySelectorAll('.agent-like-target').forEach((target) => {
  target.addEventListener('click', () => {
    showCandidate(Number(target.dataset.candidateId));
  });
});

document.querySelectorAll('.agent-like-remove').forEach((button) => {
  button.addEventListener('click', async () => {
    const likeId = Number(button.dataset.likeId);
    if (!Number.isInteger(likeId) || likeId < 1) return;

    button.disabled = true;
    const result = await postJson('/ainder/api/likes/remove.php', {
      like_id: likeId,
    });
    if (result.ok) {
      removeIncomingLike(likeId);
      return;
    }
    button.disabled = false;
    if (status) status.textContent = result.error?.message ?? 'Like was not removed.';
  });
});

stack?.addEventListener('pointerdown', (event) => {
  if (isPhotoControlTarget(event.target)) return;

  pointerStart = event.clientX;
  pointerDelta = 0;
  stack.setPointerCapture(event.pointerId);
  currentCard()?.classList.add('is-dragging');
});

stack?.addEventListener('pointermove', (event) => {
  if (pointerStart === null) return;

  pointerDelta = event.clientX - pointerStart;
  const card = currentCard();
  if (card) {
    card.style.transform = `translateX(${pointerDelta}px) rotate(${pointerDelta / 35}deg)`;
  }
});

function finishPointer(cancelled = false) {
  if (pointerStart === null) return;

  const step = cancelled ? 0 : candidateStepForDrag(pointerDelta, 64);
  currentCard()?.classList.remove('is-dragging');
  currentCard()?.style.removeProperty('transform');
  pointerStart = null;
  pointerDelta = 0;
  suppressClick = step !== 0;

  if (step !== 0) {
    moveCandidate(step);
    setTimeout(() => { suppressClick = false; }, 0);
  }
}

stack?.addEventListener('pointerup', () => finishPointer(false));
stack?.addEventListener('pointercancel', () => finishPointer(true));
stack?.addEventListener('click', (event) => {
  if (!suppressClick) return;
  event.preventDefault();
  event.stopPropagation();
  suppressClick = false;
}, true);

setCurrentCandidate(0);

globalThis.ainderBrowseController = Object.freeze({
  getCurrentCandidate: () => candidateSnapshot(),
  browseCandidates,
  changeCandidatePhoto,
  showCandidate,
  removeCandidate,
  removeIncomingLike,
});
