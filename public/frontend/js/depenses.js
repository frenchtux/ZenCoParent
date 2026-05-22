/* ============================================================
   ZenCoParent — Dépenses Page Logic
   Expense management with filters + summary bar
   ============================================================ */

(function (global) {
  'use strict';

  let expenses    = [];
  let users       = [];
  let currentUser = null;
  let editingExp  = null;

  // Filters
  let filterCategory = '';
  let filterFrom     = '';
  let filterTo       = '';

  /* ── Init ─────────────────────────────────────────────────── */
  async function init(user) {
    currentUser = user;
    await loadUsers();
    await loadExpenses();
    buildUserSelects();
    bindNewExpenseButton();
    bindExpenseForm();
    bindFilters();
  }

  /* ── Data loading ─────────────────────────────────────────── */
  async function loadUsers() {
    try {
      const res = await api.get('/users');
      users = Array.isArray(res) ? res : (res && res.data ? res.data : []);
    } catch {
      users = [];
    }
  }

  async function loadExpenses() {
    const tableEl = document.getElementById('expenses-table-body');
    tableEl.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:var(--space-8);"><div class="loading-state" style="display:inline-flex;"><div class="spinner spinner-sm"></div><span>Chargement...</span></div></td></tr>';

    try {
      const res = await api.get('/expenses');
      expenses = Array.isArray(res) ? res : (res && res.data ? res.data : []);
      renderExpenses();
      renderSummary();
    } catch (err) {
      toast(err.message || 'Impossible de charger les dépenses.', 'error');
      tableEl.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:var(--space-6);color:var(--color-error);">Erreur de chargement.</td></tr>';
    }
  }

  /* ── Filter ───────────────────────────────────────────────── */
  function getFiltered() {
    return expenses.filter(exp => {
      const cat  = (exp.category || '').toLowerCase();
      const date = (exp.date || exp.created_at || '').slice(0, 10);
      if (filterCategory && cat !== filterCategory) return false;
      if (filterFrom && date < filterFrom) return false;
      if (filterTo   && date > filterTo)   return false;
      return true;
    });
  }

  function bindFilters() {
    document.getElementById('filter-category').addEventListener('change', function () {
      filterCategory = this.value;
      renderExpenses();
      renderSummary();
    });
    document.getElementById('filter-from').addEventListener('change', function () {
      filterFrom = this.value;
      renderExpenses();
      renderSummary();
    });
    document.getElementById('filter-to').addEventListener('change', function () {
      filterTo = this.value;
      renderExpenses();
      renderSummary();
    });
    document.getElementById('btn-clear-filters').addEventListener('click', function () {
      filterCategory = '';
      filterFrom     = '';
      filterTo       = '';
      document.getElementById('filter-category').value = '';
      document.getElementById('filter-from').value = '';
      document.getElementById('filter-to').value   = '';
      renderExpenses();
      renderSummary();
    });
  }

  /* ── Summary bar ──────────────────────────────────────────── */
  function renderSummary() {
    const filtered = getFiltered();
    const total = filtered.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);

    // Group by paid_by
    const byUser = {};
    filtered.forEach(e => {
      const key = e.paid_by || e.paid_by_id || 'inconnu';
      byUser[key] = (byUser[key] || 0) + parseFloat(e.amount || 0);
    });

    document.getElementById('summary-total').textContent = formatAmount(total);
    document.getElementById('summary-count').textContent = `${filtered.length} dépense${filtered.length !== 1 ? 's' : ''}`;

    // Per-parent split
    const splitEl = document.getElementById('summary-split');
    const entries = Object.entries(byUser);
    if (entries.length === 0) {
      splitEl.innerHTML = '<span style="font-size:var(--text-sm);color:var(--color-text-muted);">—</span>';
    } else {
      splitEl.innerHTML = entries.map(([userId, amt]) => {
        const u = users.find(u => String(u.id) === String(userId));
        const name = u ? `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email : `#${userId}`;
        return `<div class="summary-bar-item">
          <div class="summary-bar-label">${escapeHtml(name)}</div>
          <div class="summary-bar-value" style="font-size:var(--text-md);">${formatAmount(amt)}</div>
        </div>`;
      }).join('');
    }
  }

  /* ── Render table ─────────────────────────────────────────── */
  function renderExpenses() {
    const tbody = document.getElementById('expenses-table-body');
    const filtered = getFiltered().sort((a, b) => {
      const da = a.date || a.created_at || '';
      const db = b.date || b.created_at || '';
      return db.localeCompare(da);
    });

    if (filtered.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6">
            <div class="empty-state" style="padding: var(--space-8);">
              <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
              </div>
              <div class="empty-state-title">Aucune dépense</div>
              <p class="empty-state-desc">Aucune dépense ne correspond à vos filtres.</p>
            </div>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = filtered.map(exp => {
      const catKey = (exp.category || 'autre').toLowerCase();
      const paidBy = exp.paid_by || exp.paid_by_id;
      const u = users.find(u => String(u.id) === String(paidBy));
      const paidName = u ? `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email : (paidBy ? `#${paidBy}` : '—');

      return `
        <tr>
          <td>${escapeHtml(formatDate(exp.date || exp.created_at || ''))}</td>
          <td style="max-width:200px;">
            <div style="font-weight:var(--font-medium);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${escapeHtml(exp.description || 'Dépense')}
            </div>
            ${exp.notes ? `<div style="font-size:var(--text-xs);color:var(--color-text-muted);">${escapeHtml(exp.notes)}</div>` : ''}
          </td>
          <td><span class="badge expense-badge-${catKey}">${escapeHtml(expenseCategoryLabel(catKey))}</span></td>
          <td style="font-family:var(--font-display);font-weight:var(--font-semibold);font-size:var(--text-md);">
            ${formatAmount(exp.amount)}
          </td>
          <td style="font-size:var(--text-sm);color:var(--color-text-muted);">${escapeHtml(paidName)}</td>
          <td>
            <div class="table-actions">
              <button class="btn btn-ghost btn-sm btn-icon" title="Modifier" onclick="openEditExpense(${JSON.stringify(exp).replace(/"/g,'&quot;')})">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
              </button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Supprimer" onclick="confirmDeleteExpense('${exp.id}')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14" style="color:var(--color-error)"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  /* ── User selects in modal ────────────────────────────────── */
  function buildUserSelects() {
    const sel = document.getElementById('expense-paid-by');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Choisir —</option>' +
      users.map(u => {
        const name = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
        const selected = String(u.id) === String(currentUser.id) ? ' selected' : '';
        return `<option value="${u.id}"${selected}>${escapeHtml(name)}</option>`;
      }).join('');
  }

  /* ── New Expense ──────────────────────────────────────────── */
  function bindNewExpenseButton() {
    document.getElementById('btn-new-expense').addEventListener('click', () => {
      editingExp = null;
      resetExpenseForm();
      document.getElementById('expense-modal-title').textContent = 'Nouvelle dépense';
      document.getElementById('expense-save-btn').textContent    = 'Ajouter';
      document.getElementById('expense-date').value = todayISO();
      // Default paid_by = current user
      document.getElementById('expense-paid-by').value = String(currentUser.id);
      openModal('expense-modal');
    });
  }

  global.openEditExpense = function (exp) {
    editingExp = exp;
    resetExpenseForm();
    document.getElementById('expense-modal-title').textContent = 'Modifier la dépense';
    document.getElementById('expense-save-btn').textContent    = 'Enregistrer';
    document.getElementById('expense-description').value = exp.description || '';
    document.getElementById('expense-amount').value      = exp.amount || '';
    document.getElementById('expense-category').value   = exp.category || 'autre';
    document.getElementById('expense-date').value        = (exp.date || '').slice(0, 10);
    document.getElementById('expense-paid-by').value    = exp.paid_by || exp.paid_by_id || '';
    document.getElementById('expense-notes').value      = exp.notes || '';
    openModal('expense-modal');
  };

  global.confirmDeleteExpense = function (id) {
    if (!confirm('Supprimer cette dépense ?')) return;
    deleteExpense(id);
  };

  async function deleteExpense(id) {
    try {
      await api.del(`/expenses/${id}`);
      toast('Dépense supprimée.', 'success');
      await loadExpenses();
    } catch (err) {
      toast(err.message || 'Erreur lors de la suppression.', 'error');
    }
  }

  function resetExpenseForm() {
    document.getElementById('expense-form').reset();
  }

  /* ── Expense form submit ──────────────────────────────────── */
  function bindExpenseForm() {
    document.getElementById('expense-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('expense-save-btn');
      setLoading(btn, true);

      const payload = {
        description: document.getElementById('expense-description').value.trim(),
        amount:      parseFloat(document.getElementById('expense-amount').value),
        category:    document.getElementById('expense-category').value,
        date:        document.getElementById('expense-date').value || null,
        paid_by:     document.getElementById('expense-paid-by').value || null,
        notes:       document.getElementById('expense-notes').value.trim() || null,
      };

      if (!payload.description) {
        toast('La description est requise.', 'warning');
        setLoading(btn, false);
        return;
      }
      if (isNaN(payload.amount) || payload.amount <= 0) {
        toast('Le montant doit être supérieur à 0.', 'warning');
        setLoading(btn, false);
        return;
      }

      try {
        if (editingExp) {
          await api.put(`/expenses/${editingExp.id}`, payload);
          toast('Dépense mise à jour.', 'success');
        } else {
          await api.post('/expenses', payload);
          toast('Dépense ajoutée.', 'success');
        }
        closeModal('expense-modal');
        await loadExpenses();
      } catch (err) {
        toast(err.message || 'Erreur lors de la sauvegarde.', 'error');
      } finally {
        setLoading(btn, false);
      }
    });
  }

  global.depensesInit = init;

})(window);
