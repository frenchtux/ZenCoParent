/* ============================================================
   ZenCoParent — Abonnement (family subscription management)
   ============================================================ */
(async function () {
  'use strict';

  const user = await requireAuth();
  if (!user) return;

  // Admins do not manage subscriptions — redirect to dashboard
  if (user.role === 'admin') {
    window.location.href = '/frontend/dashboard.html';
    return;
  }

  renderNav('abonnement.html');

  let selectedInterval = 'monthly';
  let plans = [];

  // ── Check for Stripe redirect ─────────────────────────────────────────────
  const params = new URLSearchParams(window.location.search);
  if (params.get('checkout') === 'success') {
    toast('Paiement confirmé ! Votre abonnement est en cours d\'activation.', 'success');
    history.replaceState({}, '', window.location.pathname);
  }
  if (params.get('checkout') === 'cancelled') {
    toast('Paiement annulé.', 'warning');
    history.replaceState({}, '', window.location.pathname);
  }

  // ── Load current subscription ─────────────────────────────────────────────
  const STATUS_LABELS = {
    none:      { label: 'Aucun abonnement', cls: 'badge-neutral' },
    trial:     { label: 'Période d\'essai', cls: 'badge-warning' },
    active:    { label: 'Actif',            cls: 'badge-success' },
    past_due:  { label: 'Paiement en retard', cls: 'badge-error' },
    cancelled: { label: 'Annulé',           cls: 'badge-neutral' },
    expired:   { label: 'Expiré',           cls: 'badge-error'   },
  };

  async function loadCurrentSub() {
    const res = await api.get('/billing/status');
    const subBody = document.getElementById('current-sub-body');
    const portalCard = document.getElementById('portal-card');

    if (!res || !res.success) {
      subBody.innerHTML = '<p class="text-muted">Impossible de charger le statut.</p>';
      return;
    }

    const d = res.data;
    const statusInfo = STATUS_LABELS[d.status] || { label: d.status, cls: 'badge-neutral' };

    let html = `<div style="display:flex;align-items:center;gap:var(--space-3);flex-wrap:wrap;margin-bottom:var(--space-4);">
      <span class="badge ${statusInfo.cls}">${escapeHtml(statusInfo.label)}</span>`;

    if (d.plan) {
      html += `<span style="font-weight:600;">${escapeHtml(d.plan.name)}</span>`;
      const price = d.billing_interval === 'yearly' ? d.plan.price_yearly : d.plan.price_monthly;
      const label = d.billing_interval === 'yearly' ? '/an' : '/mois';
      html += `<span class="text-muted">${price.toFixed(2)} €${label}</span>`;
    }
    html += '</div>';

    if (d.period_end) {
      const dt = new Date(d.period_end).toLocaleDateString('fr-FR');
      html += `<p class="text-muted" style="margin:0;font-size:var(--text-sm);">Prochaine échéance : <strong>${dt}</strong></p>`;
    }
    if (d.trial_ends_at && d.status === 'trial') {
      const dt = new Date(d.trial_ends_at).toLocaleDateString('fr-FR');
      html += `<p class="text-muted" style="margin:var(--space-2) 0 0;font-size:var(--text-sm);">Essai gratuit jusqu'au : <strong>${dt}</strong></p>`;
    }
    if (d.cancel_at) {
      const dt = new Date(d.cancel_at).toLocaleDateString('fr-FR');
      html += `<p style="margin:var(--space-2) 0 0;font-size:var(--text-sm);color:var(--color-error,#dc2626);">Annulé le ${dt}</p>`;
    }
    if (!d.plan) {
      html += `<p class="text-muted" style="margin:var(--space-2) 0 0;font-size:var(--text-sm);">Sélectionnez un plan ci-dessous pour démarrer votre abonnement.</p>`;
    }

    subBody.innerHTML = html;

    // Show portal only if subscription exists
    if (d.status !== 'none' && d.plan && portalCard) {
      portalCard.style.display = 'block';
    }
  }

  // ── Load plans ────────────────────────────────────────────────────────────
  async function loadPlans() {
    const res = await api.get('/admin/plans');
    if (!res.success) return;
    plans = res.data.filter(p => p.is_active && p.name !== 'free');
    renderPlans();
  }

  const MODULE_LABELS = {
    expenses: 'Dépenses',
    photos:   'Photos',
    messages: 'Messagerie',
    medical:  'Dossiers médicaux',
  };

  function renderPlans() {
    const grid = document.getElementById('plans-grid');
    if (!plans.length) {
      grid.innerHTML = '<p class="empty-state" style="grid-column:1/-1;">Aucun plan disponible.</p>';
      return;
    }

    grid.innerHTML = plans.map(p => {
      const price = selectedInterval === 'yearly' ? p.price_yearly_cents : p.price_monthly_cents;
      const priceDisplay = (price / 100).toFixed(2) + ' €';
      const priceLabel   = selectedInterval === 'yearly' ? '/an' : '/mois';
      const modules = Object.entries(p.modules || {})
        .filter(([, v]) => v)
        .map(([k]) => MODULE_LABELS[k] || k);

      return `<div class="card" style="border:2px solid var(--color-primary-200);position:relative;">
        <div class="card-header" style="border-bottom:1px solid var(--color-border);">
          <div>
            <h3 style="margin:0;font-size:var(--font-size-lg);">${escapeHtml(p.display_name)}</h3>
            <p class="text-muted" style="margin:var(--space-1) 0 0;">${escapeHtml(p.description)}</p>
          </div>
        </div>
        <div class="card-body">
          <div style="margin-bottom:var(--space-4);">
            <span style="font-size:var(--font-size-2xl);font-weight:700;">${priceDisplay}</span>
            <span class="text-muted">${priceLabel}</span>
          </div>
          <ul style="list-style:none;padding:0;margin:0 0 var(--space-5);display:flex;flex-direction:column;gap:var(--space-2);">
            <li style="color:var(--color-success-600);">✓ Enfants &amp; Calendrier</li>
            ${modules.map(m => `<li style="color:var(--color-success-600);">✓ ${escapeHtml(m)}</li>`).join('')}
          </ul>
          <button class="btn btn-primary" style="width:100%;"
            data-plan-id="${p.id}"
            ${price === 0 ? 'disabled' : ''}>
            ${price === 0 ? 'Gratuit' : 'Choisir ce plan'}
          </button>
        </div>
      </div>`;
    }).join('');

    grid.querySelectorAll('[data-plan-id]').forEach(btn => {
      if (!btn.disabled) {
        btn.addEventListener('click', () => checkout(btn.dataset.planId));
      }
    });
  }

  // ── Billing interval toggle ───────────────────────────────────────────────
  document.querySelectorAll('#interval-toggle .btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#interval-toggle .btn').forEach(b => {
        b.classList.remove('active', 'btn-primary');
        b.classList.add('btn-ghost');
      });
      btn.classList.add('active', 'btn-primary');
      btn.classList.remove('btn-ghost');
      selectedInterval = btn.dataset.interval;
      renderPlans();
    });
  });

  // ── Checkout ──────────────────────────────────────────────────────────────
  async function checkout(planId) {
    const res = await api.post('/payments/checkout/subscription', {
      plan_id:  planId,
      interval: selectedInterval,
    });
    if (res.success && res.data.url) {
      window.location.href = res.data.url;
    } else {
      toast(res.error || 'Impossible de créer la session de paiement.', 'error');
    }
  }

  // ── Stripe portal ─────────────────────────────────────────────────────────
  document.getElementById('portal-btn')?.addEventListener('click', async () => {
    const res = await api.get('/payments/portal');
    if (res.success && res.data.url) {
      window.location.href = res.data.url;
    } else {
      toast('Aucun abonnement Stripe actif trouvé.', 'error');
    }
  });

  // ── Init ──────────────────────────────────────────────────────────────────
  await Promise.all([loadCurrentSub(), loadPlans()]);
}());
