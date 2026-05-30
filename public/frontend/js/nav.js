/* ============================================================
   ZenCoParent — Nav Module
   Renders sidebar into #app-shell
   ============================================================ */

(function (global) {
  'use strict';

  const PARENT_NAV_LINKS = [
    {
      page: 'dashboard.html',
      label: 'Tableau de bord',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>`,
    },
    {
      page: 'calendrier.html',
      label: 'Calendrier',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" /></svg>`,
    },
    {
      page: 'enfants.html',
      label: 'Enfants',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>`,
    },
    {
      page: 'messagerie.html',
      label: 'Messagerie',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>`,
    },
    {
      page: 'depenses.html',
      label: 'Dépenses',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>`,
    },
    {
      page: 'sante.html',
      label: 'Santé',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>`,
    },
    {
      page: 'photos.html',
      label: 'Photos',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>`,
    },
    {
      page: 'abonnement.html',
      label: 'Abonnement',
      saasOnly: true,
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>`,
    },
    {
      page: 'mon-profil.html',
      label: 'Mon profil',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>`,
    },
  ];

  const CHILD_NAV_LINKS = [
    {
      page: 'dashboard.html',
      label: 'Tableau de bord',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>`,
    },
    {
      page: 'calendrier.html',
      label: 'Calendrier',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>`,
    },
    {
      page: 'depenses.html',
      label: 'Dépenses',
      badge: 'lecture',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>`,
    },
    {
      page: 'mon-profil.html',
      label: 'Mon profil',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>`,
    },
  ];

  const ADMIN_NAV_LINKS = [
    {
      page: 'dashboard.html',
      label: 'Tableau de bord',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>`,
    },
    {
      page: 'admin.html',
      label: 'Administration',
      saasOnly: true,
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`,
    },
    {
      page: 'utilisateurs.html',
      label: 'Utilisateurs',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>`,
    },
    {
      page: 'license.html',
      label: 'Licence',
      saasOnly: true,
      navId: 'nav-license-link',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>`,
    },
    {
      page: 'admin-parametres.html',
      label: 'Paramètres',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`,
    },
    {
      page: 'mon-profil.html',
      label: 'Mon profil',
      icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>`,
    },
  ];

  const LOGOUT_ICON = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>`;

  const INVITE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>`;

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function initials(first, last) {
    const f = (first || '').trim()[0] || '';
    const l = (last  || '').trim()[0] || '';
    return (f + l).toUpperCase() || '?';
  }

  /* ── Theme injection ─────────────────────────────────────── */
  // Le mode est détecté via l'API /mode et mis en cache localStorage
  // pour éviter le flash visuel (FOUC) lors des chargements suivants.

  let IS_SAAS = localStorage.getItem('zenco_mode') === 'saas';
  window.IS_SAAS = IS_SAAS;

  function applyTheme() {
    if (!IS_SAAS) return;
    if (document.getElementById('zenco-theme-saas')) return;
    const link = document.createElement('link');
    link.id   = 'zenco-theme-saas';
    link.rel  = 'stylesheet';
    link.href = '/frontend/css/theme-saas.css';
    document.head.appendChild(link);
  }

  function removeTheme() {
    const existing = document.getElementById('zenco-theme-saas');
    if (existing) existing.remove();
  }

  // Application immédiate depuis le cache
  applyTheme();

  // Rafraîchissement depuis l'API (sans bloquer le rendu)
  fetch('/mode', { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (data) {
      var mode    = (data && data.data && data.data.mode) || 'community';
      var nowSaas = mode === 'saas';
      localStorage.setItem('zenco_mode', mode);
      if (nowSaas !== IS_SAAS) {
        IS_SAAS = nowSaas;
        window.IS_SAAS = nowSaas;
        nowSaas ? applyTheme() : removeTheme();
      }
    })
    .catch(function () { /* réseau indisponible — conserver le cache */ });

  /**
   * Render the sidebar into #app-shell before .main-content.
   * @param {string} activePage - filename like 'dashboard.html'
   */
  function renderNav(activePage) {
    const shell = document.getElementById('app-shell');
    if (!shell) return;

    const user = auth.getUser();
    if (!user) return;

    const fullName     = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.email;
    const userInitials = initials(user.first_name, user.last_name);

    // Select nav links based on role
    let navLinks;
    let roleLabel;
    let showInviteBtn = false;

    if (user.role === 'admin') {
      navLinks   = ADMIN_NAV_LINKS;
      roleLabel  = 'Administrateur';
    } else if (user.role === 'child') {
      navLinks   = CHILD_NAV_LINKS;
      roleLabel  = 'Enfant';
    } else {
      // parent (default)
      navLinks       = PARENT_NAV_LINKS;
      roleLabel      = 'Parent';
      showInviteBtn  = true;
    }

    const linksHtml = navLinks
      .filter(link => !link.saasOnly || IS_SAAS)
      .map(link => {
        const isActive = link.page === activePage;
        const staticBadge = link.badge
          ? `<span style="font-size:0.65rem;background:var(--color-border);color:var(--color-text-muted);padding:1px 6px;border-radius:999px;margin-left:auto;">${escapeHtml(link.badge)}</span>`
          : '';
        const unreadAttr = link.page === 'messagerie.html' ? ' id="nav-msg-link"' : '';
        const idAttr = link.navId ? ` id="${link.navId}"` : '';
        return `<a href="/frontend/${link.page}" class="nav-link${isActive ? ' active' : ''}" title="${escapeHtml(link.label)}"${unreadAttr}${idAttr}>
          ${link.icon}
          <span>${escapeHtml(link.label)}</span>
          ${staticBadge}
          ${link.page === 'messagerie.html' ? '<span id="nav-msg-badge" style="display:none;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:999px;margin-left:auto;"></span>' : ''}
        </a>`;
      }).join('');

    const inviteBtnHtml = showInviteBtn ? `
      <button class="sidebar-invite-btn" id="sidebar-invite-btn" type="button" title="Inviter un membre">
        ${INVITE_ICON}
        <span>Inviter</span>
      </button>` : '';

    const sidebarHtml = `
    <nav class="sidebar" role="navigation" aria-label="Navigation principale">
      <div class="sidebar-brand">
        <div class="sidebar-brand-name">ZenCoParent</div>
        <div class="sidebar-brand-tagline">Co-parentalité sereine</div>
      </div>

      <div class="sidebar-user">
        <div class="sidebar-user-avatar">${escapeHtml(userInitials)}</div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name" title="${escapeHtml(fullName)}">${escapeHtml(fullName)}</div>
          <div class="sidebar-user-role">${escapeHtml(roleLabel)}</div>
        </div>
      </div>
      <div id="sidebar-tenant-switcher" style="display:none;padding:0 var(--space-4) var(--space-2);">
        <select id="tenant-select" style="width:100%;font-size:var(--text-xs);padding:4px 6px;border-radius:var(--radius-md);border:1px solid var(--color-border);background:var(--color-bg);color:var(--color-text);cursor:pointer;" title="Changer d'espace famille"></select>
      </div>

      <div class="sidebar-nav">
        <div class="nav-section-label">Navigation</div>
        ${linksHtml}
      </div>

      <div class="sidebar-footer">
        ${inviteBtnHtml}
        <button class="sidebar-logout-btn" id="sidebar-logout-btn" type="button">
          ${LOGOUT_ICON}
          <span>Déconnexion</span>
        </button>
      </div>
    </nav>
    `;

    // Insert sidebar before first child (main-content)
    shell.insertAdjacentHTML('afterbegin', sidebarHtml);

    // Bind logout
    const logoutBtn = document.getElementById('sidebar-logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => auth.logout());
    }

    // Masquer le lien Licence si l'installation est déjà activée (SaaS)
    if (IS_SAAS) {
      fetch('/license', { cache: 'no-store' })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
          // Masqué seulement si une clé d'activation a été appliquée (customer_email présent)
          const licensed = data && data.data && data.data.customer_email;
          if (licensed) {
            const licLink = document.getElementById('nav-license-link');
            if (licLink) licLink.style.display = 'none';
          }
        })
        .catch(() => {});
    }

    // Load tenant list for switcher (async, non-blocking)
    (async () => {
      try {
        const result = await api.get('/admin/users/' + user.id + '/tenants');
        const tenants = (result && result.data) ? result.data : [];
        if (Array.isArray(tenants) && tenants.length > 1) {
          const switcher = document.getElementById('sidebar-tenant-switcher');
          const sel      = document.getElementById('tenant-select');
          if (switcher && sel) {
            sel.innerHTML = tenants.map(t =>
              `<option value="${escapeHtml(t.id)}"${t.id === user.tenant_id ? ' selected' : ''}>${escapeHtml(t.name)}</option>`
            ).join('');
            switcher.style.display = '';
            sel.addEventListener('change', async function () {
              try {
                const r = await api.post('/auth/switch-tenant', { tenant_id: this.value });
                if (r && r.success) {
                  // Update stored user with new tenant context
                  const u = auth.getUser();
                  if (u) { u.tenant_id = r.data.tenant_id; auth.setUser(u); }
                  window.location.reload();
                }
              } catch (e) {
                alert('Erreur lors du changement de tenant : ' + (e.message || ''));
              }
            });
          }
        }
      } catch {
        // User may not have multi-tenant access — silently ignore
      }
    })();

    // Bind invite button
    const inviteBtn = document.getElementById('sidebar-invite-btn');
    if (inviteBtn && typeof openInviteModal === 'function') {
      inviteBtn.addEventListener('click', () => openInviteModal());
    } else if (inviteBtn) {
      // inviter.js not loaded yet — wait
      inviteBtn.addEventListener('click', () => {
        if (typeof openInviteModal === 'function') openInviteModal();
      });
    }
  }

  // ── Notification badge polling ────────────────────────────────────────────

  let _notifTimer = null;

  async function refreshUnreadBadge() {
    try {
      const res  = await api.get('/notifications/summary');
      const data = res?.data ?? res ?? {};
      const count = data.unread_messages ?? 0;
      const badge = document.getElementById('nav-msg-badge');
      if (!badge) return;
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = '';
      } else {
        badge.style.display = 'none';
      }
    } catch (_) {
      // Silent — network errors don't break the UI
    }
  }

  function startNotifPolling() {
    refreshUnreadBadge();
    _notifTimer = setInterval(refreshUnreadBadge, 30_000);
  }

  function stopNotifPolling() {
    if (_notifTimer) {
      clearInterval(_notifTimer);
      _notifTimer = null;
    }
  }

  global.renderNav         = renderNav;
  global.escapeHtml        = escapeHtml;
  global.initials          = initials;
  global.startNotifPolling = startNotifPolling;
  global.stopNotifPolling  = stopNotifPolling;

})(window);
