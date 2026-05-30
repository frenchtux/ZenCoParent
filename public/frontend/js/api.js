/* ============================================================
   ZenCoParent — API Module
   Fetch wrapper with credentials, CSRF, and 401 handling
   ============================================================ */

(function (global) {
  'use strict';

  const BASE_URL = window.location.origin;

  /**
   * Read a cookie by name.
   * @param {string} name
   * @returns {string|null}
   */
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  /**
   * Core fetch wrapper.
   * @param {string} path   - e.g. '/auth/login' or '/events?from=2025-01-01'
   * @param {object} [opts] - standard RequestInit overrides
   * @returns {Promise<any>} - parsed JSON body (or null for 204)
   * @throws {Error} with a French-friendly message
   */
  async function apiFetch(path, opts = {}) {
    const url = BASE_URL + path;

    const method = (opts.method || 'GET').toUpperCase();

    const headers = new Headers(opts.headers || {});
    headers.set('Accept', 'application/json');

    if (opts.body && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    // CSRF token on state-changing methods
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      const csrfToken = getCookie('csrf_token');
      if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
      }
    }

    let response;
    try {
      response = await fetch(url, {
        ...opts,
        method,
        headers,
        credentials: 'include',
      });
    } catch (networkError) {
      throw new Error('Impossible de joindre le serveur. Vérifiez votre connexion.');
    }

    // Handle 401 — redirect to login, except on auth endpoints (login/register)
    if (response.status === 401) {
      const isAuthEndpoint = path.startsWith('/auth/login') || path.startsWith('/auth/register');
      if (!isAuthEndpoint) {
        sessionStorage.removeItem('zenco_user');
        window.location.href = '/frontend/index.html';
        return null;
      }
      // Let the caller's catch block handle auth errors on login/register
    }

    // 204 No Content
    if (response.status === 204) {
      return null;
    }

    let data;
    const contentType = response.headers.get('Content-Type') || '';
    if (contentType.includes('application/json')) {
      try {
        data = await response.json();
      } catch (parseError) {
        throw new Error('Réponse invalide du serveur.');
      }
    } else {
      data = null;
    }

    if (!response.ok) {
      // Attempt to extract a human-readable error message
      const msg =
        (data && (data.message || data.error || data.detail)) ||
        `Erreur ${response.status}: ${response.statusText}`;
      const err = new Error(msg);
      err.status = response.status;
      err.data = data;
      throw err;
    }

    return data;
  }

  /**
   * Convenience API object.
   * Usage:
   *   api.get('/events?type=medical')
   *   api.post('/events', { title: 'Visite', ... })
   *   api.put('/events/42', { title: 'Modifié' })
   *   api.patch('/threads/1/messages/5/read', {})
   *   api.del('/events/42')
   */
  const api = {
    get(path) {
      return apiFetch(path, { method: 'GET' });
    },

    post(path, body) {
      return apiFetch(path, {
        method: 'POST',
        body: body != null ? JSON.stringify(body) : undefined,
      });
    },

    put(path, body) {
      return apiFetch(path, {
        method: 'PUT',
        body: body != null ? JSON.stringify(body) : undefined,
      });
    },

    patch(path, body) {
      return apiFetch(path, {
        method: 'PATCH',
        body: body != null ? JSON.stringify(body) : undefined,
      });
    },

    del(path, body) {
      return apiFetch(path, {
        method: 'DELETE',
        body: body != null ? JSON.stringify(body) : undefined,
      });
    },
  };

  // Expose globally
  global.apiFetch = apiFetch;
  global.api = api;

})(window);
