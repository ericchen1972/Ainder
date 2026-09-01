export function csrfToken() {
  return document.querySelector('meta[name="ainder-csrf-token"]')?.content ?? '';
}

export async function postJson(path, payload) {
  try {
    const response = await fetch(path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Ainder-CSRF': csrfToken(),
      },
      body: JSON.stringify(payload),
    });
    const body = await response.json();
    if (!response.ok || body.ok !== true) {
      return {
        ok: false,
        error: body.error ?? {
          code: 'REQUEST_FAILED',
          message: 'Ainder request failed.',
        },
      };
    }
    return body;
  } catch {
    return {
      ok: false,
      error: {
        code: 'NETWORK_FAILED',
        message: 'Ainder could not be reached.',
      },
    };
  }
}

export function currentCandidateId() {
  const value = Number(
    document.documentElement.dataset.currentCandidateId ?? 0,
  );
  return Number.isInteger(value) && value > 0 ? value : null;
}
