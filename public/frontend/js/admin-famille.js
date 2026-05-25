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

  const { tenant, subscription, plan, payments } = res.data;

  document.getElementById('page-title').textContent    = escapeHtml(tenant.name);
  document.getElementById('page-subtitle').textContent = tenant.slug;
  document.getElementById('loading-state').style.display = 'none';
  document.getElementById('family-detail').style.display  = 'block';

  // ── Subscription info ─────────────────────────────────────────────────────
  const STATUS_LABELS = {
    active:    ['badge-success', 'Actif'],
    trial:     ['badge-warning', "Période d'essai"],
    past_due:  ['badge-error',   'Paiement en retard'],
    cancelled: ['badge-neutral', 'Annulé'],
    expired:   ['badge-neutral', 'Expiré'],
  };

  function statusBadge(s) {
    const [cls, label] = STATUS_LABELS[s] || ['badge-neutral', s];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  const subEl = document.getElementById('sub-info');
  if (subscription) {
    const periodEnd = subscription.current_period_end
      ? new Date(subscription.current_period_end).toLocaleDateString('fr-FR')
      : '—';
    const trialEnd = subscription.trial_ends_at
      ? new Date(subscription.trial_ends_at).toLocaleDateString('fr-FR')
      : '—';
    subEl.innerHTML = `
      <dl style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-2) var(--space-4);">
        <dt class="text-muted">Statut</dt>       <dd>${statusBadge(subscription.status)}</dd>
        <dt class="text-muted">Plan</dt>          <dd>${plan ? escapeHtml(plan.display_name) : '—'}</dd>
        <dt class="text-muted">Facturation</dt>   <dd>${subscription.billing_interval || '—'}</dd>
        <dt class="text-muted">Fin de période</dt><dd>${periodEnd}</dd>
        <dt class="text-muted">Fin d'essai</dt>   <dd>${trialEnd}</dd>
        <dt class="text-muted">Stripe ID</dt>
        <dd><small class="text-muted">${subscription.stripe_subscription_id || '—'}</small></dd>
      </dl>`;
  } else {
    subEl.innerHTML = '<p class="text-muted">Aucun abonnement enregistré.</p>';
  }

  // ── Module toggles ────────────────────────────────────────────────────────
  const overrideContainer = document.getElementById('module-toggles');
  const planModules = plan ? (plan.modules || {}) : {};

  function renderToggles(override) {
    overrideContainer.innerHTML = MODULES.map(mod => {
      const fromPlan   = !!(planModules[mod]);
      const fromOverride = override !== null ? !!(override && override[mod]) : null;
      const effective  = fromOverride !== null ? fromOverride : fromPlan;
      const isOverridden = override !== null && override !== undefined;

      return `<label class="toggle-label" style="display:flex;align-items:center;justify-content:space-between;">
        <span>
          ${MODULE_LABELS[mod]}
          ${isOverridden
            ? '<small class="badge badge-warning" style="margin-left:4px;">override</small>'
            : `<small class="text-muted" style="margin-left:4px;">(plan : ${fromPlan ? 'oui' : 'non'})</small>`}
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
      toast('Override supprimé — le plan s\'applique.', 'success');
      renderToggles(null);
    } else {
      toast(res.error || 'Erreur.', 'error');
    }
  });

  // ── Payments ──────────────────────────────────────────────────────────────
  const STATUS_PAY = {
    succeeded: ['badge-success', 'Réussi'],
    pending:   ['badge-warning', 'En attente'],
    failed:    ['badge-error',   'Échoué'],
    refunded:  ['badge-neutral', 'Remboursé'],
  };

  const tbody = document.getElementById('payments-tbody');
  if (!payments || !payments.length) {
    tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Aucun paiement.</td></tr>';
  } else {
    tbody.innerHTML = payments.map(p => {
      const [cls, label] = STATUS_PAY[p.status] || ['badge-neutral', p.status];
      return `<tr>
        <td><small>${new Date(p.created_at).toLocaleDateString('fr-FR')}</small></td>
        <td><span class="badge badge-neutral">${p.type === 'installation_key' ? 'Clé install.' : 'Abonnement'}</span></td>
        <td><strong>${(p.amount_cents / 100).toFixed(2)} ${p.currency.toUpperCase()}</strong></td>
        <td><span class="badge ${cls}">${label}</span></td>
      </tr>`;
    }).join('');
  }
}());
