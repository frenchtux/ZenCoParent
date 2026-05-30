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

    grid.innerHTML = photos.map((p, idx) => {
      const cap = p.caption ? escapeHtml(p.caption) : '';
      const cn  = p.child_id ? escapeHtml(childName(p.child_id)) : '';
      const label = [cap, cn].filter(Boolean).join(' · ');
      return `<div class="photo-card" onclick="window._openLightbox(${idx})" style="cursor:pointer;">
        <img src="${escapeHtml(p.url)}" alt="${escapeHtml(p.filename || 'photo')}" loading="lazy" />
        <button class="photo-card-delete" title="Supprimer" onclick="event.stopPropagation();window._deletePhoto('${p.id}')">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
        ${label ? `<div class="photo-card-overlay"><div class="photo-card-caption">${label}</div></div>` : ''}
      </div>`;
    }).join('');
  }

  // ── Lightbox ─────────────────────────────────────────────────────────────
  let _lbIdx = 0;

  window._openLightbox = function (idx) {
    _lbIdx = idx;
    _renderLightbox();
    document.getElementById('lb-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  };

  function _closeLightbox() {
    document.getElementById('lb-overlay').style.display = 'none';
    document.body.style.overflow = '';
  }

  function _renderLightbox() {
    const p   = photos[_lbIdx];
    if (!p) return;
    const cap = p.caption ? escapeHtml(p.caption) : '';
    const cn  = p.child_id ? escapeHtml(childName(p.child_id)) : '';
    const label = [cap, cn].filter(Boolean).join(' · ');
    document.getElementById('lb-img').src = p.url;
    document.getElementById('lb-img').alt = p.filename || 'photo';
    document.getElementById('lb-caption').textContent = label;
    document.getElementById('lb-counter').textContent = `${_lbIdx + 1} / ${photos.length}`;
    document.getElementById('lb-prev').style.visibility = _lbIdx > 0 ? 'visible' : 'hidden';
    document.getElementById('lb-next').style.visibility = _lbIdx < photos.length - 1 ? 'visible' : 'hidden';
  }

  // Inject lightbox DOM once
  if (!document.getElementById('lb-overlay')) {
    document.body.insertAdjacentHTML('beforeend', `
      <div id="lb-overlay" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,0.92);align-items:center;justify-content:center;flex-direction:column;">
        <button id="lb-close" style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:32px;cursor:pointer;line-height:1;" aria-label="Fermer">&times;</button>
        <button id="lb-prev" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;cursor:pointer;border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;" aria-label="Précédent">&#8249;</button>
        <button id="lb-next" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:28px;cursor:pointer;border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;" aria-label="Suivant">&#8250;</button>
        <img id="lb-img" src="" alt="" style="max-height:80vh;max-width:90vw;object-fit:contain;border-radius:4px;" />
        <div style="margin-top:12px;text-align:center;">
          <div id="lb-caption" style="color:#e5e7eb;font-size:14px;"></div>
          <div id="lb-counter" style="color:#9ca3af;font-size:12px;margin-top:4px;"></div>
        </div>
      </div>`);

    document.getElementById('lb-close').addEventListener('click', _closeLightbox);
    document.getElementById('lb-prev').addEventListener('click', () => { if (_lbIdx > 0) { _lbIdx--; _renderLightbox(); } });
    document.getElementById('lb-next').addEventListener('click', () => { if (_lbIdx < photos.length - 1) { _lbIdx++; _renderLightbox(); } });
    document.getElementById('lb-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) _closeLightbox(); });
    document.addEventListener('keydown', e => {
      if (document.getElementById('lb-overlay').style.display === 'none') return;
      if (e.key === 'Escape')      _closeLightbox();
      if (e.key === 'ArrowLeft'  && _lbIdx > 0)               { _lbIdx--; _renderLightbox(); }
      if (e.key === 'ArrowRight' && _lbIdx < photos.length - 1) { _lbIdx++; _renderLightbox(); }
    });
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
