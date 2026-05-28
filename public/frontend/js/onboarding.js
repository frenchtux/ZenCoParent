/* ============================================================
   ZenCoParent — Onboarding wizard
   ============================================================ */

(async function () {
  'use strict';

  const user = await requireAuth();
  if (!user) return;

  // Children and admins only — redirect others to dashboard
  if (user.role === 'child') {
    window.location.replace('/frontend/dashboard.html');
    return;
  }

  let currentStep = 1;
  const TOTAL_STEPS = 3;

  function goTo(step) {
    // Update dots
    for (let i = 1; i <= TOTAL_STEPS; i++) {
      const dot = document.getElementById('dot-' + i);
      if (i < step)       dot.className = 'step-dot done';
      else if (i === step) dot.className = 'step-dot active';
      else                 dot.className = 'step-dot';
    }
    // Show/hide panels
    for (let i = 1; i <= TOTAL_STEPS; i++) {
      const panel = document.getElementById('step-' + i);
      panel.classList.toggle('active', i === step);
    }
    currentStep = step;
  }

  function finish() {
    sessionStorage.setItem('zenco_onboarding_dismissed', '1');
    window.location.replace('/frontend/dashboard.html');
  }

  async function sendInvite() {
    const email = document.getElementById('invite-email').value.trim();
    const fb    = document.getElementById('invite-feedback');
    const btn   = document.getElementById('btn-invite');

    if (!email) {
      showFeedback(fb, 'Veuillez saisir une adresse e-mail.', 'error');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Envoi…';

    try {
      await api.post('/invitations', { email, role: 'parent' });
      document.getElementById('invite-form').style.display  = 'none';
      document.getElementById('invite-success').style.display = '';
      document.getElementById('btn-invite').style.display    = 'none';

      // Auto-advance after 1.5 s
      setTimeout(() => goTo(3), 1500);
    } catch (err) {
      showFeedback(fb, err?.message ?? 'Erreur lors de l\'envoi.', 'error');
      btn.disabled = false;
      btn.textContent = 'Envoyer l\'invitation';
    }
  }

  async function saveChild() {
    const firstName = document.getElementById('child-first').value.trim();
    const lastName  = document.getElementById('child-last').value.trim();
    const dob       = document.getElementById('child-dob').value;
    const fb        = document.getElementById('child-feedback');
    const btn       = document.getElementById('btn-child');

    if (!firstName) {
      showFeedback(fb, 'Le prénom est requis.', 'error');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Enregistrement…';

    try {
      await api.post('/children', {
        first_name:    firstName,
        last_name:     lastName  || '',
        birthdate:     dob       || null,
      });
      finish();
    } catch (err) {
      showFeedback(fb, err?.message ?? 'Erreur lors de l\'enregistrement.', 'error');
      btn.disabled = false;
      btn.textContent = 'Ajouter l\'enfant';
    }
  }

  function showFeedback(el, message, type) {
    el.style.display = '';
    el.className = type === 'error' ? 'alert alert-danger' : 'alert alert-success';
    el.textContent = message;
  }

  // Expose to inline onclick handlers
  window.goTo      = goTo;
  window.finish    = finish;
  window.sendInvite = sendInvite;
  window.saveChild  = saveChild;
})();
