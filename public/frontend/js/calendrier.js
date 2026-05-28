/* ============================================================
   ZenCoParent — Calendrier Page Logic
   Month grid calendar with event management
   ============================================================ */

(function (global) {
  'use strict';

  let currentYear  = new Date().getFullYear();
  let currentMonth = new Date().getMonth(); // 0-indexed
  let allEvents    = [];
  let children     = [];
  let selectedDay  = null;
  let editingEvent = null;

  const MONTHS_FR = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
  ];
  const DAYS_FR = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

  /* ── Init ─────────────────────────────────────────────────── */
  async function init() {
    await loadChildren();
    await loadEvents();
    renderCalendar();
    bindNavButtons();
    bindNewEventButton();
    buildChildSelect();
    bindTypeSelect();
  }

  /* ── Data loading ─────────────────────────────────────────── */
  async function loadChildren() {
    try {
      const res = await api.get('/children');
      children = Array.isArray(res) ? res : (res && res.data ? res.data : []);
    } catch (err) {
      children = [];
    }
  }

  async function loadEvents() {
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay  = new Date(currentYear, currentMonth + 1, 0);
    const from = firstDay.toISOString().slice(0, 10);
    const to   = lastDay.toISOString().slice(0, 10);
    try {
      const res = await api.get(`/events?from=${from}&to=${to}`);
      allEvents = Array.isArray(res) ? res : (res && res.data ? res.data : []);
    } catch (err) {
      toast('Impossible de charger les événements.', 'error');
      allEvents = [];
    }
  }

  /* ── Calendar Rendering ───────────────────────────────────── */
  function renderCalendar() {
    // Update header label
    document.getElementById('cal-month-label').textContent =
      `${MONTHS_FR[currentMonth]} ${currentYear}`;

    const grid = document.getElementById('calendar-grid');
    grid.innerHTML = '';

    // Weekday headers
    DAYS_FR.forEach(day => {
      const cell = document.createElement('div');
      cell.className = 'calendar-weekday';
      cell.textContent = day;
      grid.appendChild(cell);
    });

    // Find first cell: Monday of the week containing the 1st
    const firstOfMonth = new Date(currentYear, currentMonth, 1);
    // JS: 0=Sun → convert so 0=Mon
    let startDow = firstOfMonth.getDay();
    startDow = startDow === 0 ? 6 : startDow - 1;

    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();

    // Total cells: enough rows to cover the month
    const totalCells = Math.ceil((startDow + daysInMonth) / 7) * 7;

    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

    for (let i = 0; i < totalCells; i++) {
      const cell = document.createElement('div');
      cell.className = 'calendar-day';

      let dayNum, dateISO, isOtherMonth = false;

      if (i < startDow) {
        // Previous month
        dayNum = prevMonthDays - startDow + i + 1;
        const m = currentMonth === 0 ? 12 : currentMonth;
        const y = currentMonth === 0 ? currentYear - 1 : currentYear;
        dateISO = `${y}-${String(m).padStart(2,'0')}-${String(dayNum).padStart(2,'0')}`;
        isOtherMonth = true;
      } else if (i >= startDow + daysInMonth) {
        // Next month
        dayNum = i - startDow - daysInMonth + 1;
        const m = currentMonth === 11 ? 1 : currentMonth + 2;
        const y = currentMonth === 11 ? currentYear + 1 : currentYear;
        dateISO = `${y}-${String(m).padStart(2,'0')}-${String(dayNum).padStart(2,'0')}`;
        isOtherMonth = true;
      } else {
        dayNum = i - startDow + 1;
        dateISO = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(dayNum).padStart(2,'0')}`;
      }

      if (isOtherMonth) cell.classList.add('other-month');
      if (dateISO === todayStr) cell.classList.add('today');
      if (dateISO === selectedDay) cell.classList.add('selected');

      cell.dataset.date = dateISO;

      // Day number
      const numEl = document.createElement('div');
      numEl.className = 'calendar-day-number';
      numEl.textContent = dayNum;
      cell.appendChild(numEl);

      // Events for this day
      const dayEvents = allEvents.filter(ev => {
        const evDate = (ev.start_at || ev.start_date || ev.date || '').slice(0, 10);
        return evDate === dateISO;
      });

      if (dayEvents.length > 0) {
        const evContainer = document.createElement('div');
        evContainer.className = 'calendar-events';
        const shown = dayEvents.slice(0, 3);
        shown.forEach(ev => {
          const typeKey = (ev.type || 'autre').toLowerCase();
          const chip = document.createElement('div');
          chip.className = `calendar-event-chip ${typeKey}`;
          chip.textContent = ev.title || 'Événement';
          chip.title = ev.title || '';
          evContainer.appendChild(chip);
        });
        if (dayEvents.length > 3) {
          const more = document.createElement('div');
          more.style.cssText = 'font-size:10px;color:var(--color-text-muted);padding:1px 4px;';
          more.textContent = `+${dayEvents.length - 3} autre(s)`;
          evContainer.appendChild(more);
        }
        cell.appendChild(evContainer);
      }

      cell.addEventListener('click', () => {
        selectedDay = dateISO;
        document.querySelectorAll('.calendar-day').forEach(c => c.classList.remove('selected'));
        cell.classList.add('selected');
        renderDayPanel(dateISO, dayEvents);
      });

      grid.appendChild(cell);
    }

    // Auto-show today's panel
    if (!selectedDay) {
      const todayEvents = allEvents.filter(ev =>
        (ev.start_at || ev.start_date || ev.date || '').slice(0, 10) === todayStr
      );
      renderDayPanel(todayStr, todayEvents);
    }
  }

  /* ── Day Panel ────────────────────────────────────────────── */
  function renderDayPanel(dateISO, events) {
    const panel = document.getElementById('day-panel');
    const title = document.getElementById('day-panel-title');
    const body  = document.getElementById('day-panel-body');

    const d = new Date(dateISO + 'T12:00:00');
    title.textContent = d.toLocaleDateString('fr-FR', {
      weekday: 'long', day: 'numeric', month: 'long'
    });

    panel.style.display = 'block';

    if (!events || events.length === 0) {
      body.innerHTML = `
        <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
          <div class="empty-state-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
          </div>
          <p class="empty-state-desc">Aucun événement ce jour.</p>
        </div>
      `;
      return;
    }

    body.innerHTML = events.map(ev => {
      const typeKey = (ev.type || 'autre').toLowerCase();
      const evTime = ev.start_time || (ev.start_at ? ev.start_at.slice(11, 16) : '');
      const timeStr = (evTime && evTime !== '00:00') ? `<span style="font-size:var(--text-xs);color:var(--color-text-muted)">${escapeHtml(evTime)}</span>` : '';
      return `
        <div class="event-list-item">
          <div class="event-dot event-dot-${typeKey}" style="margin-top:4px;"></div>
          <div class="event-list-item-content">
            <div class="event-list-item-title">${escapeHtml(ev.title || 'Sans titre')}</div>
            <div class="event-list-item-meta">
              <span class="badge event-badge-${typeKey}">${escapeHtml(eventTypeLabel(typeKey))}</span>
              ${timeStr}
              ${ev.description ? `<div style="margin-top:4px;font-size:var(--text-xs);color:var(--color-text-muted)">${escapeHtml(ev.description)}</div>` : ''}
            </div>
          </div>
          <div class="event-list-item-actions">
            <button class="btn btn-ghost btn-sm btn-icon" title="Modifier" onclick="openEditEvent(${JSON.stringify(ev).replace(/"/g,'&quot;')})">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
            </button>
            <button class="btn btn-ghost btn-sm btn-icon" title="Supprimer" onclick="confirmDeleteEvent('${ev.id}')">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14" style="color:var(--color-error)"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  /* ── Nav buttons ──────────────────────────────────────────── */
  function bindNavButtons() {
    document.getElementById('cal-prev').addEventListener('click', async () => {
      currentMonth--;
      if (currentMonth < 0) { currentMonth = 11; currentYear--; }
      selectedDay = null;
      await loadEvents();
      renderCalendar();
    });

    document.getElementById('cal-next').addEventListener('click', async () => {
      currentMonth++;
      if (currentMonth > 11) { currentMonth = 0; currentYear++; }
      selectedDay = null;
      await loadEvents();
      renderCalendar();
    });

    document.getElementById('cal-today').addEventListener('click', async () => {
      const now = new Date();
      currentYear  = now.getFullYear();
      currentMonth = now.getMonth();
      selectedDay = null;
      await loadEvents();
      renderCalendar();
    });
  }

  /* ── Child select in modal ────────────────────────────────── */
  function buildChildSelect() {
    const sel = document.getElementById('event-child-id');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Aucun enfant —</option>' +
      children.map(c =>
        `<option value="${c.id}">${escapeHtml(c.first_name + ' ' + (c.last_name || ''))}</option>`
      ).join('');
  }

  /* ── New Event Modal ──────────────────────────────────────── */
  function bindNewEventButton() {
    document.getElementById('btn-new-event').addEventListener('click', () => {
      editingEvent = null;
      resetEventForm();
      document.getElementById('event-modal-title').textContent = 'Nouvel événement';
      document.getElementById('event-save-btn').textContent    = 'Créer';
      // Pre-fill selected date
      if (selectedDay) {
        document.getElementById('event-date').value = selectedDay;
      }
      openModal('event-modal');
    });
  }

  function toggleReportField(type) {
    const group = document.getElementById('event-report-group');
    if (group) group.style.display = (type === 'medical') ? '' : 'none';
  }

  function resetEventForm() {
    document.getElementById('event-form').reset();
    toggleReportField('');
    document.querySelectorAll('#event-form .form-error').forEach(el => {
      el.textContent = '';
      el.classList.add('hidden');
    });
    document.querySelectorAll('#event-form .form-input, #event-form .form-select, #event-form .form-textarea').forEach(el => {
      el.classList.remove('error');
    });
  }

  function bindTypeSelect() {
    const sel = document.getElementById('event-type');
    if (sel) sel.addEventListener('change', function () { toggleReportField(this.value); });
  }

  global.openEditEvent = function (ev) {
    editingEvent = ev;
    resetEventForm();
    document.getElementById('event-modal-title').textContent = 'Modifier l\'événement';
    document.getElementById('event-save-btn').textContent    = 'Enregistrer';
    document.getElementById('event-title').value       = ev.title || '';
    document.getElementById('event-type').value        = ev.type  || 'autre';
    // Support both API format (start_at) and legacy (start_date)
    const startAt = ev.start_at || ev.start_date || ev.date || '';
    document.getElementById('event-date').value        = startAt.slice(0, 10);
    document.getElementById('event-time').value        = ev.start_time || (startAt.length > 10 ? startAt.slice(11, 16) : '');
    document.getElementById('event-child-id').value   = ev.child_id || '';
    document.getElementById('event-description').value = ev.description || '';
    toggleReportField(ev.type || '');
    if (ev.type === 'medical') {
      document.getElementById('event-report').value = ev.report || '';
    }
    openModal('event-modal');
  };

  global.confirmDeleteEvent = function (id) {
    if (!confirm('Supprimer cet événement ?')) return;
    deleteEvent(id);
  };

  async function deleteEvent(id) {
    try {
      await api.del(`/events/${id}`);
      toast('Événement supprimé.', 'success');
      await loadEvents();
      renderCalendar();
      if (selectedDay) {
        const dayEvs = allEvents.filter(ev =>
          (ev.start_at || ev.start_date || ev.date || '').slice(0, 10) === selectedDay
        );
        renderDayPanel(selectedDay, dayEvs);
      }
    } catch (err) {
      toast(err.message || 'Erreur lors de la suppression.', 'error');
    }
  }

  /* ── Event form submit ────────────────────────────────────── */
  document.getElementById('event-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('event-save-btn');
    setLoading(btn, true);

    const dateVal = document.getElementById('event-date').value;
    const timeVal = document.getElementById('event-time').value || '00:00';
    const startAt = dateVal ? `${dateVal}T${timeVal}:00` : null;
    let endAt = null;
    if (startAt) {
      const startDate = new Date(`${dateVal}T${timeVal}:00`);
      startDate.setHours(startDate.getHours() + 1);
      endAt = startDate.toISOString().slice(0, 19);
    }

    const eventType = document.getElementById('event-type').value;
    const payload = {
      title:       document.getElementById('event-title').value.trim(),
      type:        eventType,
      start_at:    startAt,
      end_at:      endAt,
      child_id:    document.getElementById('event-child-id').value || null,
      description: document.getElementById('event-description').value.trim() || null,
    };
    if (eventType === 'medical') {
      payload.report = document.getElementById('event-report').value.trim() || null;
    }

    if (!payload.title) {
      toast('Le titre est requis.', 'warning');
      setLoading(btn, false);
      return;
    }
    if (!payload.start_at) {
      toast('La date est requise.', 'warning');
      setLoading(btn, false);
      return;
    }

    try {
      if (editingEvent) {
        await api.put(`/events/${editingEvent.id}`, payload);
        toast('Événement mis à jour.', 'success');
      } else {
        await api.post('/events', payload);
        toast('Événement créé.', 'success');
      }
      closeModal('event-modal');
      await loadEvents();
      renderCalendar();
      if (selectedDay) {
        const dayEvs = allEvents.filter(ev =>
          (ev.start_at || ev.start_date || ev.date || '').slice(0, 10) === selectedDay
        );
        renderDayPanel(selectedDay, dayEvs);
      }
    } catch (err) {
      toast(err.message || 'Erreur lors de la sauvegarde.', 'error');
    } finally {
      setLoading(btn, false);
    }
  });

  // Expose init
  global.calendrierInit = init;

})(window);
