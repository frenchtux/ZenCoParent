/* ============================================================
   ZenCoParent — Photos
   Gallery: list, upload (multipart), delete
   ============================================================ */
async function photosInit(user) {
  'use strict';

  let children = [];
  let photos = [];

  const grid    = document.getElementById('photo-grid');
  const loading = document.getElementById('loading-state');
  const empty   = document.getElementById('empty-state');

  // ── Load children for filter + upload select ──────────────────────────────
  async function loadChildren() {
    try {
      const res = await api.get('/children');
      children = (res && res.data) ? res.data : [];
    } catch { children = []; }

    const opts = children.map(c => {
      const name = `${c.first_name || ''} ${c.last_name || ''}`.trim();
      return `<option value="${c.id}">${escapeHtml(name)}</option>`;
    }).join('');
    document.getElementById('filter-child').insertAdjacentHTML('beforeend', opts);
    document.getElementById('photo-child').insertAdjacentHTML('beforeend', opts);
  }

  function childName(childId) {
    const c = children.find(x => x.id === childId);
    return c ? `${c.first_name || ''} ${c.last_name || ''}`.trim() : '';
  }

  // ── Load photos ────────────────────────────────────────────────────────────
  async function loadPhotos(childId) {
    loading.style.display = 'flex';
    grid.style.display = 'none';
    empty.style.display = 'none';

    try {
      const url = childId ? `/photos?child_id=${encodeURIComponent(childId)}` : '/photos';
      const res = await api.get(url);
      photos = (res && res.data) ? res.data : [];
    } catch (e) {
      photos = [];
      toast(e.message || 'Erreur lors du chargement.', 'error');
    }

    loading.style.display = 'none';
    renderGrid();
  }

  function renderGrid() {
    if (!photos.length) {
      empty.style.display = '';
      grid.style.display = 'none';
      return;
    }
    empty.style.display = 'none';
    grid.style.display = 'grid';

    grid.innerHTML = photos.map(p => {
      const cap = p.caption ? escapeHtml(p.caption) : '';
      const cn  = p.child_id ? escapeHtml(childName(p.child_id)) : '';
      const label = [cap, cn].filter(Boolean).join(' · ');
      return `<div class="photo-card">
        <img src="${escapeHtml(p.url)}" alt="${escapeHtml(p.filename || 'photo')}" loading="lazy" />
        <button class="photo-card-delete" title="Supprimer" onclick="window._deletePhoto('${p.id}')">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
        ${label ? `<div class="photo-card-overlay"><div class="photo-card-caption">${label}</div></div>` : ''}
      </div>`;
    }).join('');
  }

  // ── Delete ───────────────────────────────────────────────────────────────
  window._deletePhoto = async function (id) {
    if (!confirm('Supprimer cette photo ?')) return;
    try {
      await api.del(`/photos/${id}`);
      toast('Photo supprimée.', 'success');
      await loadPhotos(document.getElementById('filter-child').value || null);
    } catch (e) {
      toast(e.message || 'Erreur lors de la suppression.', 'error');
    }
  };

  // ── Upload ───────────────────────────────────────────────────────────────
  document.getElementById('btn-new-photo').addEventListener('click', () => {
    document.getElementById('photo-form').reset();
    openModal('photo-modal');
  });

  document.getElementById('photo-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn  = document.getElementById('photo-save-btn');
    const file = document.getElementById('photo-file').files[0];
    if (!file) { toast('Sélectionnez une image.', 'warning'); return; }
    if (file.size > 10 * 1024 * 1024) { toast('Fichier trop volumineux (10 Mo max).', 'warning'); return; }

    setLoading(btn, true);

    const fd = new FormData();
    fd.append('file', file);
    const childId = document.getElementById('photo-child').value;
    const caption = document.getElementById('photo-caption').value.trim();
    if (childId) fd.append('child_id', childId);
    if (caption) fd.append('caption', caption);

    try {
      const csrf = document.cookie.match(/csrf_token=([^;]+)/)?.[1] || '';
      const res = await fetch('/photos', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrf },
        body: fd,
      });
      const json = await res.json().catch(() => ({}));
      if (res.ok && (json.success !== false)) {
        toast('Photo ajoutée.', 'success');
        closeModal('photo-modal');
        await loadPhotos(document.getElementById('filter-child').value || null);
      } else {
        toast(json.error || 'Erreur lors de l\'envoi.', 'error');
      }
    } catch (e) {
      toast('Erreur réseau lors de l\'envoi.', 'error');
    } finally {
      setLoading(btn, false);
    }
  });

  // ── Filter ───────────────────────────────────────────────────────────────
  document.getElementById('filter-child').addEventListener('change', function () {
    loadPhotos(this.value || null);
  });

  // ── Init ─────────────────────────────────────────────────────────────────
  await loadChildren();
  await loadPhotos(null);
}
