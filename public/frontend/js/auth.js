/* ============================================================
   ZenCoParent — Auth Module
   User session management via sessionStorage + requireAuth guard
   ============================================================ */

(function (global) {
  'use strict';

  const STORAGE_KEY = 'zenco_user';

  const auth = {
    /**
     * Get the currently stored user object, or null.
     * @returns {object|null}
     */
    getUser() {
      try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        return JSON.parse(raw);
      } catch {
        return null;
      }
    },

    /**
     * Persist a user object to sessionStorage.
     * @param {object} user
     */
    setUser(user) {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    },

    /**
     * Remove the user from sessionStorage.
     */
    clearUser() {
      sessionStorage.removeItem(STORAGE_KEY);
    },

    /**
     * POST /auth/logout, clear session, redirect to login.
     */
    async logout() {
      try {
        await api.post('/auth/logout', {});
      } catch {
        // Ignore logout errors — we always redirect
      }
      auth.clearUser();
      window.location.href = '/frontend/index.html';
    },
  };

  /**
   * Auth guard for protected pages.
   *
   * Reads the user from sessionStorage. If no user is found,
   * redirects to /frontend/index.html and returns null.
   *
   * Usage at the top of each page init script:
   *   const user = await requireAuth();
   *   if (!user) return;
   *
   * @returns {Promise<object|null>}
   */
  async function requireAuth() {
    const user = auth.getUser();
    if (!user) {
      window.location.href = '/frontend/index.html';
      return null;
    }
    return user;
  }

  // Expose globally
  global.auth = auth;
  global.requireAuth = requireAuth;

})(window);
