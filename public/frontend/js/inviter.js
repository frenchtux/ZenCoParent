/* ============================================================
   ZenCoParent — Inviter Module
   Inject invite modal and handle invite logic
   ============================================================ */

(function (global) {
  'use strict';

  // ── Modal HTML injected into body ──────────────────────────────────────────

  const MODAL_HTML = `
<div id="invite-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:1000;align-items:center;justify-content:center;">
  <div id="invite-modal" role="dialog" aria-modal="true" aria-labelledby="invite-modal-title"
    style="background:var(--color-bg);border-radius:var(--radius-xl);box-shadow:0 24px 60px rgba(0,0,0,0.18);width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:var(--space-8);margin:var(--space-4);">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-6);">
      <h2 id="invite-modal-title" style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:var(--font-medium);color:var(--color-text);margin:0;">
        Inviter un membre
      </h2>
      <button id="invite-modal-close" type="button"
        style="background:none;border:none;cursor:pointer;color:var(--color-text-muted);padding:var(--space-1);" aria-label="Fermer">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Create invite form -->
    <div id="invite-create-section">
      <div id="invite-form-error"
        style="display:none;padding:var(--space-3) var(--space-4);background:var(--color-error-bg);border:1px solid var(--color-error);border-radius:var(--radius-md);color:var(--color-error);font-size:var(--text-sm);margin-bottom:var(--space-4);">
      </div>

      <form id="invite-form" novalidate style="display:flex;flex-direction:column;gap:var(--space-4);">
        <div class="form-group">
          <label class="form-label required" for="invite-email">Adresse e-mail</label>
          <input class="form-input" type="email" id="invite-email" placeholder="alice@exemple.fr" required />
          <span class="form-error hidden" id="invite-email-error"></span>
        </div>

        <div class="form-group">
          <label class="form-label required" for="invite-role">Rôle</label>
          <select class="form-input" id="invite-role" required style="cursor:pointer;">
            <option value="parent">Co-parent</option>
            <option value="child">Enfant</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" id="invite-submit-btn" style="align-self:flex-end;min-width:160px;">
          Envoyer l'invitation
        </button>
      </form>

      <!-- Result: show invite link -->
      <div id="invite-link-section" style="display:none;margin-top:var(--space-6);">
        <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:var(--space-4);">
          <p style="font-size:var(--text-sm);color:var(--color-text-muted);margin:0 0 var(--space-3);">
            Invitation créée. Partagez ce lien avec la personne invitée :
          </p>
          <div style="display:flex;gap:var(--space-2);align-items:center;">
            <input id="invite-link-input" type="text" readonly class="form-input"
              style="flex:1;font-size:var(--text-xs);background:var(--color-bg);" />
            <button type="button" class="btn btn-ghost btn-sm" id="invite-copy-btn">Copier</button>
          </div>
          <p id="invite-copy-msg" style="display:none;font-size:var(--text-xs);color:var(--color-success,#4caf50);margin:var(--space-2) 0 0;">
            Lien copié !
          </p>
        </div>
      </div>
    </div>

    <!-- Pending invitations list -->
    <div style="margin-top:var(--space-8);border-top:1px solid var(--color-border);padding-top:var(--space-6);">
      <h3 style="font-size:var(--text-base);font-weight:var(--font-medium);color:var(--color-text);margin:0 0 var(--space-4);">
        Invitations envoyées
      </h3>
      <div id="invite-list" style="font-size:var(--text-sm);color:var(--color-text-muted);">
        Chargement...
      </div>
    </div>
  </div>
</div>
`;

  // ── Helpers ────────────────────────────────────────────────────────────────

  function esc(str) {
    return String(str || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function formatDate(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch { return iso; }
  }

  // ── Modal state ────────────────────────────────────────────────────────────

  let modalInjected = false;

  function injectModal() {
    if (modalInjected) return;
    document.body.insertAdjacentHTML('beforeend', MODAL_HTML);
    modalInjected = true;

    // Close handlers
    document.getElementById('invite-modal-close').addEventListener('click', closeInviteModal);
    document.getElementById('invite-modal-overlay').addEventListener('click', function (e) {
      if (e.target === this) closeInviteModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeInviteModal();
    });

    // Form submit
    document.getElementById('invite-form').addEventListener('submit', handleInviteSubmit);

    // Copy button
    document.getElementById('invite-copy-btn').addEventListener('click', function () {
      const input = document.getElementById('invite-link-input');
      input.select();
      try {
        document.execCommand('copy');
      } catch {
        navigator.clipboard.writeText(input.value).catch(() => {});
      }
      const msg = document.getElementById('invite-copy-msg');
      msg.style.display = 'block';
      setTimeout(() => { msg.style.display = 'none'; }, 2500);
    });
  }

  function openInviteModal() {
    injectModal();

    // Reset form
    document.getElementById('invite-form').reset();
    document.getElementById('invite-form-error').style.display = 'none';
    document.getElementById('invite-link-section').style.display = 'none';
    document.querySelectorAll('#invite-form .form-error').forEach(el => {
      el.textContent = ''; el.classList.add('hidden');
    });
    document.querySelectorAll('#invite-form .form-input').forEach(el => el.classList.remove('error'));

    // Show modal
    const overlay = document.getElementById('invite-modal-overlay');
    overlay.style.display = 'flex';
    document.getElementById('invite-email').focus();

    // Load pending invitations
    loadInviteList();
  }

  function closeInviteModal() {
    const overlay = document.getElementById('invite-modal-overlay');
    if (overlay) overlay.style.display = 'none';
  }

  async function handleInviteSubmit(e) {
    e.preventDefault();

    const email    = document.getElementById('invite-email').value.trim();
    const role     = document.getElementById('invite-role').value;
    const errBanner = document.getElementById('invite-form-error');
    const emailErr  = document.getElementById('invite-email-error');
    const btn       = document.getElementById('invite-submit-btn');

    // Reset errors
    errBanner.style.display = 'none';
    emailErr.textContent = ''; emailErr.classList.add('hidden');
    document.getElementById('invite-email').classList.remove('error');

    if (!email) {
      emailErr.textContent = "L'e-mail est requis.";
      emailErr.classList.remove('hidden');
      document.getElementById('invite-email').classList.add('error');
      return;
    }

    btn.classList.add('loading');
    btn.disabled = true;

    try {
      const result = await api.post('/invitations', { email, role });

      if (result && result.success && result.data) {
        document.getElementById('invite-link-input').value = result.data.invite_url || '';
        document.getElementById('invite-link-section').style.display = 'block';
        document.getElementById('invite-form').reset();
        loadInviteList();
      }
    } catch (err) {
      const msg = (err.data && err.data.error) || err.message || "Erreur lors de la création de l'invitation.";
      errBanner.textContent = msg;
      errBanner.style.display = 'block';
    } finally {
      btn.classList.remove('loading');
      btn.disabled = false;
    }
  }

  async function loadInviteList() {
    const listEl = document.getElementById('invite-list');
    if (!listEl) return;
    listEl.innerHTML = 'Chargement...';

    try {
      const result = await api.get('/invitations');
      const invitations = (result && result.data) || [];

      if (!invitations.length) {
        listEl.innerHTML = '<p style="margin:0;font-style:italic;">Aucune invitation envoyée.</p>';
        return;
      }

      listEl.innerHTML = invitations.map(inv => {
        const statusLabel = inv.accepted_at
          ? '<span style="color:var(--color-success,#4caf50);font-weight:600;">Acceptée</span>'
          : new Date(inv.expires_at) < new Date()
            ? '<span style="color:var(--color-error);">Expirée</span>'
            : '<span style="color:var(--color-text-muted);">En attente</span>';

        const roleLabel = inv.role === 'child' ? 'Enfant' : 'Co-parent';

        return `<div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-3) 0;border-bottom:1px solid var(--color-border);">
          <div>
            <div style="font-weight:var(--font-medium);color:var(--color-text);">${esc(inv.email)}</div>
            <div style="font-size:var(--text-xs);color:var(--color-text-muted);">${esc(roleLabel)} &bull; envoyée le ${formatDate(inv.created_at)}</div>
          </div>
          <div style="text-align:right;">${statusLabel}</div>
        </div>`;
      }).join('');
    } catch (err) {
      listEl.innerHTML = '<p style="margin:0;color:var(--color-error);">Impossible de charger les invitations.</p>';
    }
  }

  // Expose
  global.openInviteModal  = openInviteModal;
  global.closeInviteModal = closeInviteModal;

  // Auto-inject modal HTML on DOMContentLoaded so it's ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectModal);
  } else {
    injectModal();
  }

})(window);
