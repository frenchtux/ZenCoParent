/* ============================================================
   ZenCoParent — Enfants Page Logic
   Child management + medical history
   ============================================================ */

(function (global) {
  'use strict';

  let children = [];
  let editingChild = null;
  let editingMedRecord = null;

  /* ── Init ─────────────────────────────────────────────────── */
  async function init() {
    await loadChildren();
    bindNewChildButton();
    bindMedicalRecordButton();
  }

  /* ── Data loading ─────────────────────────────────────────── */
  async function loadChildren() {
    const container = document.getElementById('children-grid');
    showSpinner(container);
    try {
      const res = await api.get('/children');
      children = Array.isArray(res) ? res : (res && res.data ? res.data : []);
      renderChildren();
    } catch (err) {
      toast(err.message || 'Impossible de charger les enfants.', 'error');
      showEmpty(container, 'Erreur de chargement', 'Veuillez réessayer.',
        `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`
      );
    }
  }

  /* ── Render children grid ─────────────────────────────────── */
  function renderChildren() {
    const container = document.getElementById('children-grid');
    if (children.length === 0) {
      showEmpty(container, 'Aucun enfant enregistré',
        'Ajoutez un enfant pour commencer à planifier ensemble.',
        `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>`
      );
      return;
    }

    container.innerHTML = children.map((child, idx) => {
      const colors = ['avatar-green', 'avatar-accent', 'avatar-info', 'avatar-success'];
      const colorClass = colors[idx % colors.length];
      const childInitials = initials(child.first_name, child.last_name);
      const birthStr = child.birthdate ? formatDate(child.birthdate) : 'Date de naissance non renseignée';
      const age = child.birthdate ? computeAge(child.birthdate) : null;

      return `
        <div class="child-card" id="child-card-${child.id}">
          <div class="child-card-header">
            <div class="avatar avatar-lg ${colorClass}">${escapeHtml(childInitials)}</div>
            <div style="flex:1;min-width:0;">
              <div style="font-family:var(--font-display);font-size:var(--text-lg);font-weight:var(--font-medium);color:var(--color-text);">
                ${escapeHtml(child.first_name || '')} ${escapeHtml(child.last_name || '')}
              </div>
              <div style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:2px;">
                ${escapeHtml(birthStr)}${age !== null ? ` · ${age} ans` : ''}
              </div>
            </div>
          </div>
          <div class="child-card-body">
            ${child.notes ? `<p style="font-size:var(--text-sm);color:var(--color-text-muted);line-height:1.6;">${escapeHtml(child.notes)}</p>` : ''}
          </div>
          <div class="child-card-footer" style="flex-wrap:wrap;gap:var(--space-2);">
            <button class="btn btn-ghost btn-sm" onclick="toggleMedHistory('${child.id}')">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 3.375 3.375 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" /></svg>
              Historique médical
            </button>
            <button class="btn btn-ghost btn-sm" onclick="openAddMedRecord('${child.id}', '${escapeHtml(child.first_name || '')}')">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              Ajouter
            </button>
            <button class="btn btn-ghost btn-sm" onclick="openEditChild(${JSON.stringify(child).replace(/"/g,'&quot;')})">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
              Modifier
            </button>
          </div>
          <!-- Medical history collapsible -->
          <div class="collapsible" id="med-history-${child.id}">
            <div style="padding: var(--space-4) var(--space-6); border-top: 1px solid var(--color-border-light);">
              <div class="loading-state" id="med-loading-${child.id}" style="display:none;padding:var(--space-4);">
                <div class="spinner spinner-sm"></div><span>Chargement...</span>
              </div>
              <div id="med-records-${child.id}"></div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  /* ── Medical history toggle ───────────────────────────────── */
  global.toggleMedHistory = async function (childId) {
    const panel = document.getElementById(`med-history-${childId}`);
    const isOpen = panel.classList.contains('open');

    if (isOpen) {
      panel.classList.remove('open');
      return;
    }

    panel.classList.add('open');
    const loading = document.getElementById(`med-loading-${childId}`);
    const records = document.getElementById(`med-records-${childId}`);

    loading.style.display = 'flex';
    records.innerHTML = '';

    try {
      const res = await api.get(`/children/${childId}/medical-history`);
      const items = Array.isArray(res) ? res : (res && res.data ? res.data : []);
      loading.style.display = 'none';

      if (items.length === 0) {
        records.innerHTML = '<p style="font-size:var(--text-sm);color:var(--color-text-muted);padding:var(--space-2) 0;">Aucun antécédent médical enregistré.</p>';
        return;
      }

      records.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:var(--space-3);">
          ${items.map(rec => `
            <div class="medical-record-item">
              <div class="medical-record-date">${formatDate(rec.recorded_at || rec.date || rec.created_at || '')}</div>
              <div class="medical-record-content">
                <div class="medical-record-desc">${escapeHtml(rec.report || rec.description || rec.notes || '')}</div>
                ${rec.practitioner || rec.doctor ? `<div style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:4px;">Dr ${escapeHtml(rec.practitioner || rec.doctor)}</div>` : ''}
              </div>
            </div>
          `).join('')}
        </div>
      `;
    } catch (err) {
      loading.style.display = 'none';
      records.innerHTML = '<p style="font-size:var(--text-sm);color:var(--color-error);">Erreur lors du chargement.</p>';
    }
  };

  /* ── New / Edit child ─────────────────────────────────────── */
  function bindNewChildButton() {
    document.getElementById('btn-new-child').addEventListener('click', () => {
      editingChild = null;
      resetChildForm();
      document.getElementById('child-modal-title').textContent = 'Ajouter un enfant';
      document.getElementById('child-save-btn').textContent = 'Ajouter';
      openModal('child-modal');
    });
  }

  global.openEditChild = function (child) {
    editingChild = child;
    resetChildForm();
    document.getElementById('child-modal-title').textContent = 'Modifier l\'enfant';
    document.getElementById('child-save-btn').textContent = 'Enregistrer';
    document.getElementById('child-first-name').value = child.first_name || '';
    document.getElementById('child-last-name').value  = child.last_name  || '';
    document.getElementById('child-birthdate').value  = (child.birthdate || '').slice(0, 10);
    document.getElementById('child-notes').value      = child.notes      || '';
    openModal('child-modal');
  };

  function resetChildForm() {
    document.getElementById('child-form').reset();
  }

  document.getElementById('child-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('child-save-btn');
    setLoading(btn, true);

    const payload = {
      first_name: document.getElementById('child-first-name').value.trim(),
      last_name:  document.getElementById('child-last-name').value.trim(),
      birthdate:  document.getElementById('child-birthdate').value || null,
      notes:      document.getElementById('child-notes').value.trim() || null,
    };

    if (!payload.first_name) {
      toast('Le prénom est requis.', 'warning');
      setLoading(btn, false);
      return;
    }

    try {
      if (editingChild) {
        await api.put(`/children/${editingChild.id}`, payload);
        toast('Enfant mis à jour.', 'success');
      } else {
        await api.post('/children', payload);
        toast('Enfant ajouté.', 'success');
      }
      closeModal('child-modal');
      await loadChildren();
    } catch (err) {
      toast(err.message || 'Erreur lors de la sauvegarde.', 'error');
    } finally {
      setLoading(btn, false);
    }
  });

  /* ── Add Medical Record ───────────────────────────────────── */
  function bindMedicalRecordButton() {
    // handled inline via openAddMedRecord global
  }

  global.openAddMedRecord = function (childId, childName) {
    editingMedRecord = { childId, childName };
    resetMedForm();
    document.getElementById('med-modal-child-name').textContent = childName;
    openModal('med-modal');
  };

  function resetMedForm() {
    document.getElementById('med-form').reset();
    document.getElementById('med-date').value = todayISO();
  }

  document.getElementById('med-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('med-save-btn');
    setLoading(btn, true);

    const payload = {
      child_id:    editingMedRecord.childId,
      // API expects 'report' (not 'description') and 'recorded_at' (not 'date')
      report:      document.getElementById('med-description').value.trim(),
      recorded_at: document.getElementById('med-date').value || null,
      practitioner:document.getElementById('med-doctor').value.trim() || null,
    };

    if (!payload.report) {
      toast('La description est requise.', 'warning');
      setLoading(btn, false);
      return;
    }

    try {
      await api.post('/medical-records', payload);
      toast('Antécédent médical enregistré.', 'success');
      closeModal('med-modal');
      // Refresh med history if open
      const panel = document.getElementById(`med-history-${payload.child_id}`);
      if (panel && panel.classList.contains('open')) {
        panel.classList.remove('open');
        await toggleMedHistory(payload.child_id);
      }
    } catch (err) {
      toast(err.message || 'Erreur lors de l\'enregistrement.', 'error');
    } finally {
      setLoading(btn, false);
    }
  });

  /* ── Utility ──────────────────────────────────────────────── */
  function computeAge(birthdate) {
    try {
      const b = new Date(birthdate);
      const today = new Date();
      let age = today.getFullYear() - b.getFullYear();
      const m = today.getMonth() - b.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < b.getDate())) age--;
      return age;
    } catch { return null; }
  }

  global.enfantsInit = init;

})(window);
