/* ============================================================
   ZenCoParent — Admin : Détail famille
   ============================================================ */
(async function () {
  'use strict';

  const user = await requireAuth();
  if (!user) return;
  if (user.role !== 'admin') {
    window.location.href = '/frontend/dashboard.html';
    return;
  }
  renderNav('admin.html');

  const params   = new URLSearchParams(window.location.search);
  const familyId = params.get('id');
  if (!familyId) {
    window.location.href = '/frontend/admin.html';
    return;
  }

  const MODULES = ['expenses', 'photos', 'messages', 'medical'];
  const MODULE_LABELS = {
    expenses: 'Dépenses',
    photos:   'Photos',
    messages: 'Messagerie',
    medical:  'Dossiers médicaux',
  };

  // ── Load detail ───────────────────────────────────────────────────────────
  const res = await api.get(`/admin/families/${familyId}`);
  if (!res.success) {
    toast('Famille introuvable.', 'error');
    return;
  }

  const { tenant } = res.data;

  document.getElementById('page-title').textContent    = escapeHtml(tenant.name);
  document.getElementById('page-subtitle').textContent = tenant.slug;
  document.getElementById('loading-state').style.display = 'none';
  document.getElementById('family-detail').style.display  = 'block';

  // ── Module toggles ────────────────────────────────────────────────────────
  const overrideContainer = document.getElementById('module-toggles');

  function renderToggles(override) {
    overrideContainer.innerHTML = MODULES.map(mod => {
      const fromOverride = override !== null ? !!(override && override[mod]) : null;
      const effective    = fromOverride !== null ? fromOverride : true;
      const isOverridden = override !== null && override !== undefined;

      return `<label class="toggle-label" style="display:flex;align-items:center;justify-content:space-between;">
        <span>
          ${MODULE_LABELS[mod]}
          ${isOverridden
            ? '<small class="badge badge-warning" style="margin-left:4px;">override</small>'
            : ''}
        </span>
        <input type="checkbox" class="module-toggle" data-module="${mod}" ${effective ? 'checked' : ''} />
      </label>`;
    }).join('');
  }

  renderToggles(tenant.modules_override);

  // ── Save overrides ────────────────────────────────────────────────────────
  document.getElementById('save-modules').addEventListener('click', async () => {
    const modules = {};
    document.querySelectorAll('.module-toggle').forEach(cb => {
      modules[cb.dataset.module] = cb.checked;
    });
    const res = await api.patch(`/admin/families/${familyId}/modules`, { modules });
    if (res.success) {
      toast('Modules mis à jour.', 'success');
      renderToggles(modules);
    } else {
      toast(res.error || 'Erreur.', 'error');
    }
  });

  document.getElementById('reset-modules').addEventListener('click', async () => {
    const res = await api.patch(`/admin/families/${familyId}/modules`, { modules: null });
    if (res.success) {
      toast('Override supprimé.', 'success');
      renderToggles(null);
    } else {
      toast(res.error || 'Erreur.', 'error');
    }
  });

}());
