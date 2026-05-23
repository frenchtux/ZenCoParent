/* ============================================================
   ZenCoParent — Utilisateurs Module
   Admin user management: list, create, edit, password reset, toggle status
   ============================================================ */

(function (global) {
  'use strict';

  let _users = [];
  let _editingUserId = null;
  let _passwordUserId = null;

  /* ── Stats ────────────────────────────────────────────────── */

  function renderStats(users) {
    const total = users.length;
    const active = users.filter(u => u.is_active).length;
    const admins = users.filter(u => u.role === 'admin').length;
    const parents = users.filter(u => u.role === 'parent').length;

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    set('stat-total', total);
    set('stat-active', active);
    set('stat-admins', admins);
    set('stat-parents', parents);
  }

  /* ── Table ────────────────────────────────────────────────── */

  function roleBadge(role) {
    if (role === 'admin')  return `<span class="badge badge-primary">Administrateur</span>`;
    if (role === 'child')  return `<span class="badge badge-accent">Enfant</span>`;
    return `<span class="badge badge-neutral">Parent</span>`;
  }

  function statusBadge(isActive) {
    if (isActive) {
      return `<span class="badge badge-success">Actif</span>`;
    }
    return `<span class="badge badge-danger">Inactif</span>`;
  }

  function renderTable(users) {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;

    if (users.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center;padding:var(--space-10);">
            <div class="empty-state">
              <div class="empty-state-title">Aucun utilisateur</div>
              <p class="empty-state-desc">Aucun utilisateur trouvé dans ce tenant.</p>
            </div>
          </td>
        </tr>`;
      return;
    }

    tbody.innerHTML = users.map(u => {
      const ini = initials(u.first_name, u.last_name);
      const fullName = escapeHtml(`${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email);
      const toggleLabel = u.is_active ? 'Désactiver' : 'Activer';
      const toggleIcon = u.is_active
        ? `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>`
        : `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;

      return `<tr>
        <td>
          <div style="display:flex;align-items:center;gap:var(--space-3);">
            <div class="avatar" style="width:36px;height:36px;font-size:var(--text-xs);">${escapeHtml(ini)}</div>
            <span style="font-weight:500;">${fullName}</span>
          </div>
        </td>
        <td>${escapeHtml(u.email)}</td>
        <td>${roleBadge(u.role)}</td>
        <td>${statusBadge(u.is_active)}</td>
        <td>${formatDate(u.created_at)}</td>
        <td>
          <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;">
            <button class="btn btn-outline btn-sm" title="Modifier" onclick="utilisateurs.openEditModal('${u.id}')">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
              Modifier
            </button>
            <button class="btn btn-outline btn-sm" title="Réinitialiser le mot de passe" onclick="utilisateurs.openPasswordModal('${u.id}')">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
              Mot de passe
            </button>
            <button class="btn btn-sm ${u.is_active ? 'btn-danger' : 'btn-outline'}" title="${toggleLabel}" onclick="utilisateurs.toggleStatus('${u.id}', ${u.is_active})">
              ${toggleIcon}
              ${toggleLabel}
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  /* ── Load users ──────────────────────────────────────────── */

  async function loadUsers() {
    const tbody = document.getElementById('users-table-body');
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center;padding:var(--space-8);">
            <div class="loading-state" style="display:inline-flex;">
              <div class="spinner spinner-sm"></div><span>Chargement...</span>
            </div>
          </td>
        </tr>`;
    }

    try {
      const res = await api.get('/users');
      _users = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);
      renderStats(_users);
      renderTable(_users);
    } catch (err) {
      toast(err.message, 'error');
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--color-danger);padding:var(--space-8);">Impossible de charger les utilisateurs.</td></tr>`;
      }
    }
  }

  /* ── Create Modal ────────────────────────────────────────── */

  function openCreateModal() {
    const form = document.getElementById('create-user-form');
    if (form) form.reset();
    openModal('create-user-modal');
  }

  async function submitCreate(e) {
    e.preventDefault();
    const btn = document.getElementById('create-save-btn');
    setLoading(btn, true);

    const body = {
      first_name: document.getElementById('create-first-name').value.trim(),
      last_name:  document.getElementById('create-last-name').value.trim(),
      email:      document.getElementById('create-email').value.trim(),
      password:   document.getElementById('create-password').value,
      role:       document.getElementById('create-role').value,
    };
    const phone   = document.getElementById('create-phone').value.trim();
    const address = document.getElementById('create-address').value.trim();
    if (phone)   body.phone = phone;
    if (address) body.address = address;

    try {
      await api.post('/users', body);
      toast('Utilisateur créé avec succès.', 'success');
      closeModal('create-user-modal');
      await loadUsers();
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      setLoading(btn, false);
    }
  }

  /* ── Edit Modal ──────────────────────────────────────────── */

  function openEditModal(userId) {
    const user = _users.find(u => u.id === userId);
    if (!user) { toast('Utilisateur introuvable.', 'error'); return; }
    _editingUserId = userId;

    document.getElementById('edit-first-name').value = user.first_name || '';
    document.getElementById('edit-last-name').value  = user.last_name  || '';
    document.getElementById('edit-email-display').textContent = user.email;
    document.getElementById('edit-phone').value   = user.phone   || '';
    document.getElementById('edit-address').value = user.address || '';
    document.getElementById('edit-role').value    = user.role    || 'parent';
    document.getElementById('edit-is-active').checked = !!user.is_active;

    openModal('edit-user-modal');
  }

  async function submitEdit(e) {
    e.preventDefault();
    if (!_editingUserId) return;

    const btn = document.getElementById('edit-save-btn');
    setLoading(btn, true);

    const body = {
      first_name: document.getElementById('edit-first-name').value.trim(),
      last_name:  document.getElementById('edit-last-name').value.trim(),
      role:       document.getElementById('edit-role').value,
      is_active:  document.getElementById('edit-is-active').checked,
    };
    const phone   = document.getElementById('edit-phone').value.trim();
    const address = document.getElementById('edit-address').value.trim();
    if (phone)   body.phone = phone;
    if (address) body.address = address;

    try {
      await api.put(`/users/${_editingUserId}`, body);
      toast('Utilisateur mis à jour.', 'success');
      closeModal('edit-user-modal');
      _editingUserId = null;
      await loadUsers();
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      setLoading(btn, false);
    }
  }

  /* ── Password Modal ──────────────────────────────────────── */

  function openPasswordModal(userId) {
    _passwordUserId = userId;
    const form = document.getElementById('password-reset-form');
    if (form) form.reset();
    openModal('password-modal');
  }

  async function submitPasswordReset(e) {
    e.preventDefault();
    if (!_passwordUserId) return;

    const newPwd     = document.getElementById('reset-new-password').value;
    const confirmPwd = document.getElementById('reset-confirm-password').value;

    if (newPwd !== confirmPwd) {
      toast('Les mots de passe ne correspondent pas.', 'error');
      return;
    }
    if (newPwd.length < 8) {
      toast('Le mot de passe doit contenir au moins 8 caractères.', 'error');
      return;
    }

    const btn = document.getElementById('password-save-btn');
    setLoading(btn, true);

    try {
      await api.patch(`/users/${_passwordUserId}/password`, { new_password: newPwd });
      toast('Mot de passe réinitialisé.', 'success');
      closeModal('password-modal');
      _passwordUserId = null;
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      setLoading(btn, false);
    }
  }

  /* ── Toggle status ───────────────────────────────────────── */

  async function toggleStatus(userId, currentStatus) {
    const user = _users.find(u => u.id === userId);
    if (!user) { toast('Utilisateur introuvable.', 'error'); return; }
    try {
      await api.put(`/users/${userId}`, {
        first_name: user.first_name || '',
        last_name:  user.last_name  || '',
        is_active:  !currentStatus,
      });
      toast(currentStatus ? 'Utilisateur désactivé.' : 'Utilisateur activé.', 'success');
      await loadUsers();
    } catch (err) {
      toast(err.message, 'error');
    }
  }

  /* ── Init ────────────────────────────────────────────────── */

  async function utilisateursInit(currentUser) {
    // Admin guard (also enforced in HTML inline script, belt-and-suspenders)
    if (!currentUser || currentUser.role !== 'admin') {
      window.location.href = '/frontend/dashboard.html';
      return;
    }

    await loadUsers();

    // Bind create button
    const btnCreate = document.getElementById('btn-create-user');
    if (btnCreate) btnCreate.addEventListener('click', openCreateModal);

    // Bind create form
    const createForm = document.getElementById('create-user-form');
    if (createForm) createForm.addEventListener('submit', submitCreate);

    // Bind edit form
    const editForm = document.getElementById('edit-user-form');
    if (editForm) editForm.addEventListener('submit', submitEdit);

    // Bind password form
    const pwdForm = document.getElementById('password-reset-form');
    if (pwdForm) pwdForm.addEventListener('submit', submitPasswordReset);
  }

  // Expose
  global.utilisateurs = {
    openCreateModal,
    openEditModal,
    openPasswordModal,
    toggleStatus,
  };
  global.utilisateursInit = utilisateursInit;

})(window);
