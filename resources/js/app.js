import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Global double-click prevention and loading spinner handler across the app
document.addEventListener('DOMContentLoaded', () => {

  // 1. Handle ALL Form Submissions (HTML Forms)
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form || !(form instanceof HTMLFormElement)) return;

    if (form.dataset.submitting === 'true') {
      e.preventDefault();
      e.stopImmediatePropagation();
      return false;
    }

    const submitBtn = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

    if (submitBtn) {
      if (submitBtn.disabled || submitBtn.dataset.submitting === 'true') {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }

      form.dataset.submitting = 'true';
      submitBtn.dataset.submitting = 'true';

      // Disable all submit buttons inside form to prevent secondary clicks
      const allFormButtons = form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type="button"])');
      allFormButtons.forEach((btn) => {
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
      });

      // Inject spinner if HTML button element
      if (submitBtn.tagName.toLowerCase() === 'button' && !submitBtn.querySelector('.global-btn-spinner')) {
        const spinnerSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        spinnerSvg.setAttribute('class', 'global-btn-spinner inline-block w-4 h-4 mr-2 animate-spin text-current shrink-0');
        spinnerSvg.setAttribute('fill', 'none');
        spinnerSvg.setAttribute('viewBox', '0 0 24 24');
        spinnerSvg.innerHTML = '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>';

        submitBtn.insertBefore(spinnerSvg, submitBtn.firstChild);
      }

      // Safety timeout to reset state after 15 seconds if page does not reload
      setTimeout(() => {
        window.resetFormButtons(form);
      }, 15000);
    }
  });

  // 2. Re-enable form buttons if HTML5 validation fails
  document.addEventListener(
    'invalid',
    (e) => {
      const form = e.target.form;
      if (form) {
        window.resetFormButtons(form);
      }
    },
    true
  );

  // 3. UNIVERSAL DOUBLE CLICK PROTECTOR FOR ALL BUTTONS & ACTIONS
  // Protects all buttons without breaking inline onclick or Alpine.js @click handlers!
  document.addEventListener(
    'click',
    (e) => {
      const btn = e.target.closest('button, input[type="submit"], input[type="button"], [role="button"]');
      if (!btn) return;

      // Ignore elements explicitly marked to allow multi-click
      if (btn.hasAttribute('data-allow-multi-click') || btn.classList.contains('allow-multi-click')) {
        return;
      }

      // If button is currently processing a click within throttle window, BLOCK double click!
      if (btn.dataset.clicking === 'true') {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }

      // Mark button as clicking and lock for 700ms throttle window
      btn.dataset.clicking = 'true';

      setTimeout(() => {
        btn.dataset.clicking = 'false';
      }, 700);
    },
    true // Capture phase to intercept double clicks before repeat handlers fire
  );
});

// Global helper to reset form buttons (for AJAX / custom scripts)
window.resetFormButtons = function (formOrButton) {
  if (!formOrButton) return;

  let buttons = [];

  if (formOrButton instanceof HTMLFormElement) {
    formOrButton.dataset.submitting = 'false';
    buttons = Array.from(formOrButton.querySelectorAll('button, input[type="submit"], input[type="button"]'));
  } else if (formOrButton instanceof HTMLElement) {
    buttons = [formOrButton];
    if (formOrButton.form) {
      formOrButton.form.dataset.submitting = 'false';
    }
  }

  buttons.forEach((btn) => {
    btn.disabled = false;
    btn.dataset.submitting = 'false';
    btn.dataset.clicking = 'false';
    btn.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

    const spinner = btn.querySelector('.global-btn-spinner');
    if (spinner) {
      spinner.remove();
    }
  });
};

// Realtime partial polling: fetch a URL returning an HTML partial and swap a container's content.
window.realtimePoll = function (opts) {
  return {
    interval: opts.interval || 15000,
    init() {
      this.tick();
      this._timer = setInterval(() => this.tick(), this.interval);
    },
    destroy() {
      if (this._timer) clearInterval(this._timer);
    },
    async tick() {
      try {
        const res = await fetch(opts.url, { headers: { Accept: 'text/html', 'X-Live': '1' }, cache: 'no-store' });
        if (!res.ok) return;
        const html = await res.text();
        const target = document.getElementById(opts.target);
        if (!target || !html.trim()) return;
        target.innerHTML = html;
        if (window.Alpine) window.Alpine.initTree(target);
      } catch (e) {
        // transient failure — keep last rendered data
      }
    },
  };
};

Alpine.start();
