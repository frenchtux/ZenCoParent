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
    if (document.getElementById('kpi-mrr')) {
      document.getElementById('kpi-mrr').textContent = (d.mrr_euros || 0).toFixed(2) + ' €';
    }
  }

  // ── Families tab ──────────────────────────────────────────────────────────
  function renderFamiliesTable(families) {
    const tbody = document.getElementById('families-tbody');
    if (!families.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Aucune famille.</td></tr>';
    } else {
      tbody.innerHTML = families.map(f => {
        return `<tr>
          <td><strong>${escapeHtml(f.name)}</strong><br><small class="text-muted">${escapeHtml(f.slug)}</small></td>
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

  // ── Init ──────────────────────────────────────────────────────────────────
  await Promise.all([loadDashboard(), loadFamilies()]);
}());
