/* ============================================================
   ZenCoParent — Santé Module
   Antécédents médicaux et pièces jointes
   ============================================================ */

(function (global) {
  'use strict';

  /* ── State ──────────────────────────────────────────────── */
  let _children   = [];   // [{ id, first_name, last_name }]
  let _allRecords = [];   // tous les enregistrements chargés
  let _currentRecordId = null; // pour la modal détail

  /* ── Helpers ─────────────────────────────────────────────── */

  function childName(childId) {
    const c = _children.find(ch => String(ch.id) === String(childId));
    return c ? `${c.first_name} ${c.last_name}`.trim() : '—';
  }

  function fmtDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function excerpt(text, max = 80) {
    if (!text) return '—';
    const t = text.trim().replace(/\s+/g, ' ');
    return t.length > max ? t.slice(0, max) + '…' : t;
  }

  function getCsrfToken() {
    const match = document.cookie.match(/(?:^|; )csrf_token=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
  }

  /* ── Load children ───────────────────────────────────────── */

  async function loadChildren() {
    try {
      const res = await api.get('/children');
      _children = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);
    } catch (e) {
      _children = [];
    }
  }

  function populateChildSelects() {
    const filterSel = document.getElementById('filter-child');
    const modalSel  = document.getElementById('record-child');

    const opts = _children.map(c =>
      `<option value="${escapeHtml(String(c.id))}">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</option>`
    ).join('');

    if (filterSel) {
      filterSel.innerHTML = '<option value="">Tous</option>' + opts;
    }
    if (modalSel) {
      modalSel.innerHTML = '<option value="">Sélectionner un enfant…</option>' + opts;
    }
  }

  /* ── Load records ────────────────────────────────────────── */

  async function loadAllRecords() {
    const tbody = document.getElementById('records-table-body');
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:var(--space-8);">
        <div class="loading-state" style="display:inline-flex;"><div class="spinner spinner-sm"></div><span>Chargement...</span></div>
      </td></tr>`;
    }

    const filterChildId = document.getElementById('filter-child')?.value || '';

    try {
      let childIds = filterChildId
        ? [filterChildId]
        : _children.map(c => c.id);

      const results = await Promise.all(
        childIds.map(id =>
          api.get(`/medical-records/${id}/history`).catch(() => null)
        )
      );

      _allRecords = [];
      results.forEach(res => {
        if (!res) return;
        const arr = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);
        _allRecords.push(...arr);
      });

      // Sort by recorded_at desc
      _allRecords.sort((a, b) => {
        const da = new Date(a.recorded_at || a.created_at || 0);
        const db = new Date(b.recorded_at || b.created_at || 0);
        return db - da;
      });

    } catch (e) {
      _allRecords = [];
      showToast('Erreur lors du chargement des antécédents.', 'error');
    }

    renderTable();
  }

  /* ── Filter & Render ─────────────────────────────────────── */

  function getFilteredRecords() {
    const childId     = document.getElementById('filter-child')?.value || '';
    const practitioner = (document.getElementById('filter-practitioner')?.value || '').trim().toLowerCase();
    const fromStr     = document.getElementById('filter-from')?.value || '';
    const toStr       = document.getElementById('filter-to')?.value || '';

    const fromDate = fromStr ? new Date(fromStr) : null;
    const toDate   = toStr   ? new Date(toStr + 'T23:59:59') : null;

    return _allRecords.filter(r => {
      if (childId && String(r.child_id) !== childId) return false;

      if (practitioner) {
        const p = (r.practitioner || '').toLowerCase();
        if (!p.includes(practitioner)) return false;
      }

      const recDate = new Date(r.recorded_at || r.created_at || 0);
      if (fromDate && recDate < fromDate) return false;
      if (toDate   && recDate > toDate)   return false;

      return true;
    });
  }

  function renderTable() {
    const tbody = document.getElementById('records-table-body');
    if (!tbody) return;

    const records = getFilteredRecords();

    if (records.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:var(--space-8);color:var(--color-text-muted);">Aucun antécédent trouvé.</td></tr>`;
      return;
    }

    tbody.innerHTML = records.map(r => {
      const date        = fmtDate(r.recorded_at || r.created_at);
      const child       = escapeHtml(childName(r.child_id));
      const practitioner = escapeHtml(r.practitioner || '—');
      const extract     = escapeHtml(excerpt(r.report));
      const attachCount = r.attachment_count != null ? r.attachment_count : '…';

      return `<tr>
        <td style="white-space:nowrap;">${date}</td>
        <td>${child}</td>
        <td>${practitioner}</td>
        <td style="max-width:320px;word-break:break-word;">${extract}</td>
        <td style="text-align:center;">
          <span style="display:inline-flex;align-items:center;gap:4px;font-size:var(--text-sm);color:var(--color-text-muted);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
            ${attachCount}
          </span>
        </td>
        <td style="white-space:nowrap;">
          <button class="btn btn-ghost btn-sm" title="Voir le détail" onclick="openRecordDetail('${escapeHtml(String(r.id))}', '${escapeHtml(String(r.child_id))}')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          </button>
          <button class="btn btn-ghost btn-sm" title="Supprimer" style="color:var(--color-danger,#ef4444);" onclick="deleteRecord('${escapeHtml(String(r.id))}')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
          </button>
        </td>
      </tr>`;
    }).join('');
  }

  /* ── Create record ───────────────────────────────────────── */

  function bindCreateForm() {
    const form = document.getElementById('record-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('record-save-btn');

      const childId     = document.getElementById('record-child').value;
      const report      = document.getElementById('record-report').value.trim();
      const practitioner = document.getElementById('record-practitioner').value.trim();
      const dateVal     = document.getElementById('record-date').value;

      if (!childId || !report) {
        showToast('Veuillez remplir les champs obligatoires.', 'warning');
        return;
      }

      const body = { child_id: childId, report };
      if (practitioner) body.practitioner = practitioner;
      if (dateVal)      body.recorded_at  = dateVal;

      try {
        btn.disabled = true;
        btn.textContent = 'Enregistrement…';
        await api.post('/medical-records', body);
        showToast('Compte-rendu ajouté.', 'success');
        closeModal('record-modal');
        form.reset();
        await loadAllRecords();
      } catch (err) {
        showToast(err.message || 'Erreur lors de la création.', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Ajouter';
      }
    });
  }

  /* ── Detail modal ────────────────────────────────────────── */

  async function openRecordDetail(recordId, childId) {
    _currentRecordId = recordId;

    const record = _allRecords.find(r => String(r.id) === String(recordId));

    const titleEl = document.getElementById('detail-modal-title');
    const metaEl  = document.getElementById('detail-meta');
    const reportEl = document.getElementById('detail-report');

    if (titleEl) titleEl.textContent = record ? `Compte-rendu — ${childName(record.child_id)}` : 'Compte-rendu';
    if (metaEl && record) {
      const parts = [];
      if (record.recorded_at || record.created_at) parts.push(`Date : ${fmtDate(record.recorded_at || record.created_at)}`);
      if (record.practitioner) parts.push(`Praticien : ${record.practitioner}`);
      metaEl.textContent = parts.join('   ·   ');
    }
    if (reportEl) reportEl.textContent = (record && record.report) ? record.report : '—';

    openModal('detail-modal');
    await refreshAttachments(recordId);
  }

  async function refreshAttachments(recordId) {
    const container = document.getElementById('detail-attachments');
    if (!container) return;

    container.innerHTML = `<div class="loading-state" style="display:inline-flex;"><div class="spinner spinner-sm"></div><span>Chargement…</span></div>`;

    try {
      const res = await api.get(`/medical-records/${recordId}/attachments`);
      const list = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);

      if (list.length === 0) {
        container.innerHTML = `<p style="color:var(--color-text-muted);font-size:var(--text-sm);">Aucune pièce jointe.</p>`;
        return;
      }

      container.innerHTML = list.map(att => `
        <div style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2) 0;border-bottom:1px solid var(--color-border);">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16" style="flex-shrink:0;color:var(--color-text-muted);"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
          <a href="${escapeHtml(att.download_url || att.url || '#')}" target="_blank" rel="noopener" style="flex:1;font-size:var(--text-sm);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            ${escapeHtml(att.filename || att.file_name || `Pièce jointe #${att.id}`)}
          </a>
          <button class="btn btn-ghost btn-sm" title="Supprimer" style="color:var(--color-danger,#ef4444);flex-shrink:0;" onclick="deleteAttachment('${escapeHtml(String(att.id))}')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
          </button>
        </div>
      `).join('');
    } catch (err) {
      container.innerHTML = `<p style="color:var(--color-danger,#ef4444);font-size:var(--text-sm);">Impossible de charger les pièces jointes.</p>`;
    }
  }

  /* ── Upload attachment ───────────────────────────────────── */

  function bindUploadAttachment() {
    const btn = document.getElementById('btn-upload-attachment');
    if (!btn) return;

    btn.addEventListener('click', async () => {
      const fileInput = document.getElementById('attachment-file-input');
      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showToast('Veuillez sélectionner un fichier.', 'warning');
        return;
      }

      if (!_currentRecordId) return;

      const formData = new FormData();
      formData.append('file', fileInput.files[0]);

      try {
        btn.disabled = true;
        btn.textContent = 'Envoi…';

        const csrfToken = getCsrfToken();
        const headers = { 'Accept': 'application/json' };
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        const response = await fetch(`${window.location.origin}/medical-records/${_currentRecordId}/attachments`, {
          method: 'POST',
          credentials: 'include',
          headers,
          body: formData,
        });

        if (response.status === 401) {
          window.location.href = '/frontend/index.html';
          return;
        }

        if (!response.ok) {
          let msg = `Erreur ${response.status}`;
          try {
            const data = await response.json();
            msg = data.message || data.error || msg;
          } catch (_) {}
          throw new Error(msg);
        }

        showToast('Pièce jointe ajoutée.', 'success');
        fileInput.value = '';
        await refreshAttachments(_currentRecordId);
      } catch (err) {
        showToast(err.message || 'Erreur lors de l\'upload.', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Envoyer';
      }
    });
  }

  /* ── Delete attachment ───────────────────────────────────── */

  async function deleteAttachment(attachmentId) {
    if (!_currentRecordId) return;
    if (!confirm('Supprimer cette pièce jointe ?')) return;

    try {
      await api.del(`/medical-records/${_currentRecordId}/attachments/${attachmentId}`);
      showToast('Pièce jointe supprimée.', 'success');
      await refreshAttachments(_currentRecordId);
    } catch (err) {
      showToast(err.message || 'Erreur lors de la suppression.', 'error');
    }
  }

  /* ── Delete record ───────────────────────────────────────── */

  async function deleteRecord(recordId) {
    if (!confirm('Supprimer ce compte-rendu ?')) return;

    try {
      await api.del(`/medical-records/${recordId}`);
      showToast('Compte-rendu supprimé.', 'success');
      _allRecords = _allRecords.filter(r => String(r.id) !== String(recordId));
      renderTable();
    } catch (err) {
      // If endpoint doesn't exist (404) or not supported, inform user
      if (err.message && (err.message.includes('404') || err.message.includes('405') || err.message.toLowerCase().includes('not found'))) {
        showToast('La suppression n\'est pas disponible pour l\'instant.', 'warning');
      } else {
        showToast(err.message || 'Erreur lors de la suppression.', 'error');
      }
    }
  }

  /* ── Filter bindings ─────────────────────────────────────── */

  function bindFilters() {
    const filterChild        = document.getElementById('filter-child');
    const filterPractitioner = document.getElementById('filter-practitioner');
    const filterFrom         = document.getElementById('filter-from');
    const filterTo           = document.getElementById('filter-to');
    const btnClear           = document.getElementById('btn-clear-filters');

    // When child filter changes, reload (different endpoint per child)
    if (filterChild) {
      filterChild.addEventListener('change', () => loadAllRecords());
    }

    // Client-side filters
    [filterPractitioner, filterFrom, filterTo].forEach(el => {
      if (el) el.addEventListener('input', () => renderTable());
    });

    if (btnClear) {
      btnClear.addEventListener('click', () => {
        if (filterChild)        filterChild.value        = '';
        if (filterPractitioner) filterPractitioner.value = '';
        if (filterFrom)         filterFrom.value         = '';
        if (filterTo)           filterTo.value           = '';
        loadAllRecords();
      });
    }
  }

  /* ── New record button ───────────────────────────────────── */

  function bindNewBtn() {
    const btn = document.getElementById('btn-new-record');
    if (btn) btn.addEventListener('click', () => openModal('record-modal'));
  }

  /* ── Main init ───────────────────────────────────────────── */

  async function santeInit(user) {
    await loadChildren();
    populateChildSelects();
    bindFilters();
    bindNewBtn();
    bindCreateForm();
    bindUploadAttachment();
    await loadAllRecords();
  }

  /* ── Global exports (called from inline onclick) ─────────── */
  global.santeInit         = santeInit;
  global.openRecordDetail  = openRecordDetail;
  global.deleteRecord      = deleteRecord;
  global.deleteAttachment  = deleteAttachment;

})(window);
