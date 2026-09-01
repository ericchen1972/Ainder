import {
  wrapIndex,
  candidateStepForDrag,
  photoIndexAfterStep,
} from 'ainder-browse-model';

const browser = document.querySelector('.candidate-browser');
const stack = document.querySelector('.candidate-stack');
const cards = [...document.querySelectorAll('.candidate-card')];
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

document.querySelector('.candidate-next')?.addEventListener('click', () => {
  moveCandidate(1);
});
document.querySelector('.candidate-previous')?.addEventListener('click', () => {
  moveCandidate(-1);
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'ArrowLeft') moveCandidate(1);
  if (event.key === 'ArrowRight') moveCandidate(-1);
});

cards.forEach((card) => {
  card.querySelector('.photo-previous')?.addEventListener('click', (event) => {
    event.stopPropagation();
    movePhoto(card, -1);
  });
  card.querySelector('.photo-next')?.addEventListener('click', (event) => {
    event.stopPropagation();
    movePhoto(card, 1);
  });

  card.querySelectorAll('.candidate-photo img').forEach((image) => {
    image.addEventListener('error', () => {
      image.closest('.candidate-photo')?.classList.add('has-error');
      const available = [
        ...card.querySelectorAll('.candidate-photo:not(.has-error)'),
      ];
      if (available.length > 0) {
        showPhoto(card, Number(available[0].dataset.photoIndex));
      }
      card.classList.toggle('all-photos-failed', available.length === 0);
    });
  });
});

stack?.addEventListener('pointerdown', (event) => {
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
