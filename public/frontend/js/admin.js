/* ============================================================
   ZenCoParent — Admin Dashboard
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

  // ── State ────────────────────────────────────────────────────────────────
  let allFamilies = [];
  let allPayments = [];

  // ── Tabs ─────────────────────────────────────────────────────────────────
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => (p.style.display = 'none'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).style.display = 'block';
    });
  });

  // ── Load dashboard ────────────────────────────────────────────────────────
  async function loadDashboard() {
    const res = await api.get('/admin/dashboard');
    if (!res.success) return;
    const d = res.data;
    document.getElementById('kpi-total').textContent  = d.families.total;
    document.getElementById('kpi-active').textContent = d.families.active;
    document.getElementById('kpi-trial').textContent  = d.families.trial;
    document.getElementById('kpi-mrr').textContent    = d.mrr_euros.toFixed(2) + ' €';
  }

  // ── Families tab ──────────────────────────────────────────────────────────
  function statusBadge(status) {
    const map = {
      active:    ['badge-success', 'Actif'],
      trial:     ['badge-warning', 'Essai'],
      past_due:  ['badge-error',   'Impayé'],
      cancelled: ['badge-neutral', 'Annulé'],
      expired:   ['badge-neutral', 'Expiré'],
    };
    const [cls, label] = map[status] || ['badge-neutral', status];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function renderFamiliesTable(families) {
    const tbody = document.getElementById('families-tbody');
    if (!families.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Aucune famille.</td></tr>';
    } else {
      tbody.innerHTML = families.map(f => {
        const sub    = f.subscription;
        const plan   = f.plan;
        const status = sub ? sub.status : 'trial';
        return `<tr>
          <td><strong>${escapeHtml(f.name)}</strong><br><small class="text-muted">${escapeHtml(f.slug)}</small></td>
          <td>${plan ? escapeHtml(plan.display_name) : '<span class="text-muted">—</span>'}</td>
          <td>${statusBadge(status)}</td>
          <td><small>${new Date(f.created_at).toLocaleDateString('fr-FR')}</small></td>
          <td><a href="/frontend/admin-famille.html?id=${encodeURIComponent(f.id)}" class="btn btn-ghost btn-sm">Détail →</a></td>
        </tr>`;
      }).join('');
    }
    document.getElementById('families-loading').style.display = 'none';
    document.getElementById('families-table').style.display   = 'table';
  }

  async function loadFamilies() {
    const res = await api.get('/admin/families?limit=200');
    if (!res.success) return;
    allFamilies = res.data;
    renderFamiliesTable(allFamilies);
  }

  document.getElementById('search-families').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    renderFamiliesTable(allFamilies.filter(f =>
      f.name.toLowerCase().includes(q) || f.slug.toLowerCase().includes(q)
    ));
  });

  // ── Plans tab ─────────────────────────────────────────────────────────────
  function renderPlans(plans) {
    const body = document.getElementById('plans-body');
    if (!plans.length) {
      body.innerHTML = '<p class="empty-state">Aucun plan.</p>';
      return;
    }
    body.innerHTML = `<div class="grid-3">${plans.map(p => `
      <div class="card" style="border:1px solid var(--color-border);">
        <div class="card-header">
          <h3 class="card-title">${escapeHtml(p.display_name)}</h3>
          <button class="btn btn-ghost btn-sm" data-plan-id="${p.id}">Modifier</button>
        </div>
        <div class="card-body">
          <p class="text-muted" style="margin-bottom:var(--space-3);">${escapeHtml(p.description)}</p>
          <p><strong>${(p.price_monthly_cents / 100).toFixed(2)} €</strong> /mois &nbsp;·&nbsp; <strong>${(p.price_yearly_cents / 100).toFixed(2)} €</strong> /an</p>
          <div style="margin-top:var(--space-3);display:flex;flex-wrap:wrap;gap:var(--space-2);">
            ${Object.entries(p.modules || {}).map(([mod, on]) =>
              `<span class="badge ${on ? 'badge-success' : 'badge-neutral'}">${mod}</span>`
            ).join('')}
          </div>
        </div>
      </div>`).join('')}</div>`;

    body.querySelectorAll('[data-plan-id]').forEach(btn => {
      btn.addEventListener('click', () => openPlanModal(plans.find(p => p.id === btn.dataset.planId)));
    });
  }

  async function loadPlans() {
    const res = await api.get('/admin/plans');
    if (!res.success) return;
    renderPlans(res.data);
  }

  // ── Plan edit modal ───────────────────────────────────────────────────────
  function openPlanModal(plan) {
    document.getElementById('plan-id').value            = plan.id;
    document.getElementById('plan-display-name').value  = plan.display_name;
    document.getElementById('plan-description').value   = plan.description;
    document.getElementById('plan-price-monthly').value = plan.price_monthly_cents;
    document.getElementById('plan-price-yearly').value  = plan.price_yearly_cents;
    document.getElementById('plan-stripe-monthly').value = plan.stripe_price_id_monthly || '';
    document.getElementById('plan-stripe-yearly').value  = plan.stripe_price_id_yearly  || '';
    document.getElementById('mod-expenses').checked = !!(plan.modules && plan.modules.expenses);
    document.getElementById('mod-photos').checked   = !!(plan.modules && plan.modules.photos);
    document.getElementById('mod-messages').checked = !!(plan.modules && plan.modules.messages);
    document.getElementById('mod-medical').checked  = !!(plan.modules && plan.modules.medical);
    document.getElementById('plan-modal').style.display = 'flex';
  }

  function closePlanModal() {
    document.getElementById('plan-modal').style.display = 'none';
  }

  document.getElementById('plan-modal-close').addEventListener('click', closePlanModal);
  document.getElementById('plan-modal-cancel').addEventListener('click', closePlanModal);
  document.getElementById('plan-modal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closePlanModal();
  });

  document.getElementById('plan-modal-save').addEventListener('click', async () => {
    const id = document.getElementById('plan-id').value;
    const payload = {
      display_name:             document.getElementById('plan-display-name').value.trim(),
      description:              document.getElementById('plan-description').value.trim(),
      price_monthly_cents:      parseInt(document.getElementById('plan-price-monthly').value, 10),
      price_yearly_cents:       parseInt(document.getElementById('plan-price-yearly').value, 10),
      stripe_price_id_monthly:  document.getElementById('plan-stripe-monthly').value.trim() || null,
      stripe_price_id_yearly:   document.getElementById('plan-stripe-yearly').value.trim()  || null,
      modules: {
        expenses: document.getElementById('mod-expenses').checked,
        photos:   document.getElementById('mod-photos').checked,
        messages: document.getElementById('mod-messages').checked,
        medical:  document.getElementById('mod-medical').checked,
      },
    };
    const res = await api.put(`/admin/plans/${id}`, payload);
    if (res.success) {
      toast('Plan mis à jour.', 'success');
      closePlanModal();
      loadPlans();
    } else {
      toast(res.error || 'Erreur.', 'error');
    }
  });

  // ── Payments tab ──────────────────────────────────────────────────────────
  function paymentStatusBadge(status) {
    const map = {
      succeeded: ['badge-success', 'Réussi'],
      pending:   ['badge-warning', 'En attente'],
      failed:    ['badge-error',   'Échoué'],
      refunded:  ['badge-neutral', 'Remboursé'],
    };
    const [cls, label] = map[status] || ['badge-neutral', status];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  async function loadPayments() {
    const res = await api.get('/admin/payments?limit=100');
    if (!res.success) return;
    allPayments = res.data;
    const tbody = document.getElementById('payments-tbody');
    if (!allPayments.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Aucun paiement.</td></tr>';
    } else {
      tbody.innerHTML = allPayments.map(p => `<tr>
        <td><small>${new Date(p.created_at).toLocaleDateString('fr-FR')}</small></td>
        <td>${p.tenant_id ? `<small>${escapeHtml(p.tenant_id.slice(0, 8))}…</small>` : '<span class="text-muted">—</span>'}</td>
        <td><span class="badge badge-neutral">${p.type === 'installation_key' ? 'Clé installation' : 'Abonnement'}</span></td>
        <td><strong>${(p.amount_cents / 100).toFixed(2)} ${p.currency.toUpperCase()}</strong></td>
        <td>${paymentStatusBadge(p.status)}</td>
      </tr>`).join('');
    }
    document.getElementById('payments-loading').style.display = 'none';
    document.getElementById('payments-table').style.display   = 'table';
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  await Promise.all([loadDashboard(), loadFamilies(), loadPlans(), loadPayments()]);
}());
