/* ============================================================
   ZenCoParent — UI Helpers
   toast, modal, loading, formatting utilities
   ============================================================ */

(function (global) {
  'use strict';

  /* ── Toast ──────────────────────────────────────────────── */

  const ICONS = {
    success: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
    error:   `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`,
    warning: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`,
    info:    `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>`,
  };

  const CLOSE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>`;

  /**
   * Show a toast notification.
   * @param {string} message
   * @param {'success'|'error'|'warning'|'info'} [type='info']
   * @param {number} [duration=4000] ms before auto-close
   */
  function toast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `
      <span class="toast-icon">${ICONS[type] || ICONS.info}</span>
      <span class="toast-message">${escapeHtml(message)}</span>
      <button class="toast-close" aria-label="Fermer">${CLOSE_ICON}</button>
    `;

    function dismiss() {
      el.classList.add('exiting');
      el.addEventListener('animationend', () => el.remove(), { once: true });
    }

    el.querySelector('.toast-close').addEventListener('click', dismiss);
    container.appendChild(el);

    if (duration > 0) {
      setTimeout(dismiss, duration);
    }
  }

  /* ── Modal ──────────────────────────────────────────────── */

  /**
   * Open a modal by backdrop id.
   * @param {string} id - the id of the .modal-backdrop element
   */
  function openModal(id) {
    const backdrop = document.getElementById(id);
    if (!backdrop) return;
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Close on backdrop click
    backdrop._closeOnBackdrop = function (e) {
      if (e.target === backdrop) closeModal(id);
    };
    backdrop.addEventListener('click', backdrop._closeOnBackdrop);

    // Close on Escape
    backdrop._closeOnEsc = function (e) {
      if (e.key === 'Escape') closeModal(id);
    };
    document.addEventListener('keydown', backdrop._closeOnEsc);
  }

  /**
   * Close a modal by backdrop id.
   * @param {string} id
   */
  function closeModal(id) {
    const backdrop = document.getElementById(id);
    if (!backdrop) return;
    backdrop.classList.remove('open');
    document.body.style.overflow = '';

    if (backdrop._closeOnBackdrop) {
      backdrop.removeEventListener('click', backdrop._closeOnBackdrop);
      delete backdrop._closeOnBackdrop;
    }
    if (backdrop._closeOnEsc) {
      document.removeEventListener('keydown', backdrop._closeOnEsc);
      delete backdrop._closeOnEsc;
    }
  }

  /* ── Loading Button ─────────────────────────────────────── */

  /**
   * Toggle loading state on a button.
   * @param {HTMLButtonElement} btn
   * @param {boolean} loading
   */
  function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
      btn.classList.add('loading');
      btn.disabled = true;
      btn._originalText = btn.innerHTML;
    } else {
      btn.classList.remove('loading');
      btn.disabled = false;
      if (btn._originalText != null) {
        btn.innerHTML = btn._originalText;
      }
    }
  }

  /* ── Formatting ─────────────────────────────────────────── */

  /**
   * Get initials from first and last name.
   * @param {string} first
   * @param {string} [last='']
   * @returns {string} e.g. 'MJ'
   */
  function initials(first = '', last = '') {
    const f = (first || '').trim().charAt(0).toUpperCase();
    const l = (last || '').trim().charAt(0).toUpperCase();
    return f + l || '?';
  }

  /**
   * Format an ISO date string to French short date.
   * @param {string} str - e.g. '2025-03-15' or '2025-03-15T10:30:00Z'
   * @returns {string} e.g. '15 mars 2025'
   */
  function formatDate(str) {
    if (!str) return '—';
    try {
      const d = new Date(str);
      return d.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    } catch {
      return str;
    }
  }

  /**
   * Format an ISO datetime string to French date + time.
   * @param {string} str
   * @returns {string} e.g. '15 mars 2025 à 10h30'
   */
  function formatDateTime(str) {
    if (!str) return '—';
    try {
      const d = new Date(str);
      const datePart = d.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
      const timePart = d.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
      });
      return `${datePart} à ${timePart}`;
    } catch {
      return str;
    }
  }

  /**
   * Format a number as a French currency amount.
   * @param {number} num
   * @returns {string} e.g. '1 234,56 €'
   */
  function formatAmount(num) {
    if (num == null) return '—';
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num);
  }

  /**
   * Format a date to a short "15 mars" style.
   * @param {string} str
   * @returns {string}
   */
  function formatShortDate(str) {
    if (!str) return '—';
    try {
      const d = new Date(str);
      return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    } catch {
      return str;
    }
  }

  /**
   * Format just the time from an ISO string.
   * @param {string} str
   * @returns {string} e.g. '10:30'
   */
  function formatTime(str) {
    if (!str) return '';
    try {
      const d = new Date(str);
      return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    } catch {
      return '';
    }
  }

  /* ── DOM Helpers ────────────────────────────────────────── */

  /**
   * Escape HTML special characters to prevent XSS.
   * @param {string} str
   * @returns {string}
   */
  function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /**
   * Show a spinner in an element, replacing its content.
   * @param {HTMLElement} el
   */
  function showSpinner(el) {
    if (!el) return;
    el.innerHTML = `
      <div class="loading-state">
        <div class="spinner"></div>
        <span>Chargement...</span>
      </div>
    `;
  }

  /**
   * Show an empty state in an element.
   * @param {HTMLElement} el
   * @param {string} title
   * @param {string} desc
   * @param {string} [iconSvg]
   */
  function showEmpty(el, title, desc, iconSvg = '') {
    if (!el) return;
    el.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">${iconSvg}</div>
        <div class="empty-state-title">${escapeHtml(title)}</div>
        <p class="empty-state-desc">${escapeHtml(desc)}</p>
      </div>
    `;
  }

  /**
   * Get event type label in French.
   */
  function eventTypeLabel(type) {
    const map = {
      'rendezvous': 'Rendez-vous',
      'activite':   'Activité',
      'medical':    'Médical',
      'vacances':   'Vacances',
      'autre':      'Autre',
    };
    return map[type] || type;
  }

  /**
   * Get expense category label in French.
   */
  function expenseCategoryLabel(cat) {
    const map = {
      'alimentation': 'Alimentation',
      'sante':        'Santé',
      'education':    'Éducation',
      'loisirs':      'Loisirs',
      'transport':    'Transport',
      'logement':     'Logement',
      'autre':        'Autre',
    };
    return map[cat] || cat;
  }

  /**
   * Get thread type label in French.
   */
  function threadTypeLabel(type) {
    const map = {
      'parents': 'Parents',
      'family':  'Famille',
      'general': 'Général',
    };
    return map[type] || type;
  }

  /**
   * Today's date as ISO date string (YYYY-MM-DD).
   */
  function todayISO() {
    return new Date().toISOString().slice(0, 10);
  }

  /**
   * Get the day number from a date string.
   */
  function dayNumber(str) {
    if (!str) return '';
    try {
      return new Date(str).getDate();
    } catch { return ''; }
  }

  /**
   * Get abbreviated month name from a date string.
   */
  function monthAbbr(str) {
    if (!str) return '';
    try {
      return new Date(str).toLocaleDateString('fr-FR', { month: 'short' });
    } catch { return ''; }
  }

  // Expose globally
  global.toast = toast;
  global.openModal = openModal;
  global.closeModal = closeModal;
  global.setLoading = setLoading;
  global.initials = initials;
  global.formatDate = formatDate;
  global.formatDateTime = formatDateTime;
  global.formatAmount = formatAmount;
  global.formatShortDate = formatShortDate;
  global.formatTime = formatTime;
  global.escapeHtml = escapeHtml;
  global.showSpinner = showSpinner;
  global.showEmpty = showEmpty;
  global.eventTypeLabel = eventTypeLabel;
  global.expenseCategoryLabel = expenseCategoryLabel;
  global.threadTypeLabel = threadTypeLabel;
  global.todayISO = todayISO;
  global.dayNumber = dayNumber;
  global.monthAbbr = monthAbbr;

})(window);
