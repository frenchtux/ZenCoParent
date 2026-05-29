/* ============================================================
   ZenCoParent — Mon Profil Module
   Personal profile editing and password change
   ============================================================ */

(function (global) {
  'use strict';

  let _currentUser = null;

  /* ── Populate profile ────────────────────────────────────── */

  function populateProfile(user) {
    _currentUser = user;

    // Avatar
    const avatarEl = document.getElementById('profile-avatar');
    if (avatarEl) avatarEl.textContent = initials(user.first_name, user.last_name);

    // Summary card
    const nameEl = document.getElementById('profile-full-name');
    if (nameEl) nameEl.textContent = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.email;

    const emailEl = document.getElementById('profile-email');
    if (emailEl) emailEl.textContent = user.email || '';

    const roleEl = document.getElementById('profile-role-badge');
    if (roleEl) {
      roleEl.textContent = user.role === 'admin' ? 'Administrateur' : 'Parent';
      roleEl.className = user.role === 'admin' ? 'badge badge-primary' : 'badge badge-neutral';
    }

    const memberSinceEl = document.getElementById('profile-member-since');
    if (memberSinceEl) memberSinceEl.textContent = `Membre depuis ${formatDate(user.created_at)}`;

    // Form fields
    const setVal = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.value = val || '';
    };
    setVal('profile-first-name', user.first_name);
    setVal('profile-last-name',  user.last_name);
    setVal('profile-phone',      user.phone);
    setVal('profile-address',    user.address);
  }

  /* ── Load own profile ────────────────────────────────────── */

  async function loadProfile() {
    const formArea = document.getElementById('profile-form-area');
    if (formArea) {
      formArea.innerHTML = `
        <div class="loading-overlay" style="position:relative;min-height:120px;display:flex;align-items:center;justify-content:center;">
          <div class="spinner"></div>
        </div>`;
    }

    try {
      const res = await api.get('/users/me');
      const user = (res && res.data) ? res.data : res;
      populateProfile(user);
      // Restore form after spinner
      if (formArea) formArea.innerHTML = getProfileFormHtml();
      bindProfileForm();
      // Re-populate fields after rebuilding DOM
      populateProfile(user);
    } catch (err) {
      toast(err.message, 'error');
    }
  }

  /* ── Profile form HTML (used when dynamic rebuild needed) ── */
  // Not used in static HTML approach — form is static in HTML.
  // loadProfile just populates fields directly.

  function getProfileFormHtml() {
    return ''; // static HTML provides the form; this path not used
  }

  /* ── Submit profile ──────────────────────────────────────── */

  async function submitProfile(e) {
    e.preventDefault();
    if (!_currentUser) return;

    const btn = document.getElementById('profile-save-btn');
    setLoading(btn, true);

    const body = {
      first_name: document.getElementById('profile-first-name').value.trim(),
      last_name:  document.getElementById('profile-last-name').value.trim(),
    };
    const phone   = document.getElementById('profile-phone').value.trim();
    const address = document.getElementById('profile-address').value.trim();
    if (phone)   body.phone = phone;
    if (address) body.address = address;

    try {
      const res = await api.put(`/users/${_currentUser.id}`, body);
      const updated = (res && res.data) ? res.data : { ..._currentUser, ...body };
      _currentUser = updated;
      auth.setUser(updated);
      populateProfile(updated);
      toast('Profil mis à jour avec succès.', 'success');
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      setLoading(btn, false);
    }
  }

  /* ── Submit password ─────────────────────────────────────── */

  async function submitPassword(e) {
    e.preventDefault();
    if (!_currentUser) return;

    const currentPwd = document.getElementById('pwd-current').value;
    const newPwd     = document.getElementById('pwd-new').value;
    const confirmPwd = document.getElementById('pwd-confirm').value;

    if (newPwd !== confirmPwd) {
      toast('Les nouveaux mots de passe ne correspondent pas.', 'error');
      return;
    }
    if (newPwd.length < 8) {
      toast('Le nouveau mot de passe doit contenir au moins 8 caractères.', 'error');
      return;
    }

    const btn = document.getElementById('pwd-save-btn');
    setLoading(btn, true);

    try {
      await api.patch(`/users/${_currentUser.id}/password`, {
        current_password: currentPwd,
        new_password:     newPwd,
      });
      toast('Mot de passe modifié avec succès.', 'success');
      document.getElementById('password-form').reset();
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      setLoading(btn, false);
    }
  }

  /* ── Bind forms ──────────────────────────────────────────── */

  function bindProfileForm() {
    const profileForm = document.getElementById('profile-form');
    if (profileForm) profileForm.addEventListener('submit', submitProfile);

    const passwordForm = document.getElementById('password-form');
    if (passwordForm) passwordForm.addEventListener('submit', submitPassword);

    bindGdpr();
  }

  /* ── RGPD : export + suppression de compte ──────────────────── */

  function bindGdpr() {
    const exportBtn = document.getElementById('export-btn');
    if (exportBtn) {
      exportBtn.addEventListener('click', async () => {
        setLoading(exportBtn, true);
        try {
          // Endpoint streams a JSON attachment — fetch as blob and trigger download
          const csrf = document.cookie.match(/csrf_token=([^;]+)/)?.[1] || '';
          const res = await fetch('/account/export', {
            credentials: 'include',
            headers: { 'X-CSRF-Token': csrf },
          });
          if (!res.ok) throw new Error('Export impossible.');
          const blob = await res.blob();
          const url  = URL.createObjectURL(blob);
          const a    = document.createElement('a');
          a.href = url;
          a.download = 'zencoparent-export.json';
          document.body.appendChild(a);
          a.click();
          a.remove();
          URL.revokeObjectURL(url);
          toast('Export téléchargé.', 'success');
        } catch (e) {
          toast(e.message || 'Erreur lors de l\'export.', 'error');
        } finally {
          setLoading(exportBtn, false);
        }
      });
    }

    const deleteBtn = document.getElementById('delete-account-btn');
    if (deleteBtn) {
      deleteBtn.addEventListener('click', () => {
        document.getElementById('delete-form').reset();
        openModal('delete-modal');
      });
    }

    const deleteForm = document.getElementById('delete-form');
    if (deleteForm) {
      deleteForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const pwd = document.getElementById('delete-password').value;
        if (!pwd) { toast('Mot de passe requis.', 'warning'); return; }
        const btn = document.getElementById('delete-confirm-btn');
        setLoading(btn, true);
        try {
          await api.del('/account', { password: pwd });
          auth.clearUser();
          toast('Compte supprimé.', 'success');
          setTimeout(() => { window.location.href = '/frontend/index.html'; }, 800);
        } catch (err) {
          toast(err.message || 'Suppression impossible.', 'error');
          setLoading(btn, false);
        }
      });
    }
  }

  /* ── Init ────────────────────────────────────────────────── */

  async function monProfilInit(currentUser) {
    // Use the already-known user from session as initial state
    _currentUser = currentUser;
    populateProfile(currentUser);

    // Bind forms immediately (HTML is static)
    bindProfileForm();

    // Then fetch fresh data from server
    try {
      const res = await api.get('/users/me');
      const fresh = (res && res.data) ? res.data : res;
      _currentUser = fresh;
      auth.setUser(fresh);
      populateProfile(fresh);
    } catch (err) {
      toast('Impossible de charger les données du profil.', 'error');
    }
  }

  global.monProfilInit = monProfilInit;

})(window);
