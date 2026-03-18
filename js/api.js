/**
 * ProWay Lab — API Client
 * Conecta el frontend con el backend PHP (api/)
 * Fallback: localStorage para modo offline/demo
 */

const PW_API = (() => {
  const BASE = '/api';
  let _token = localStorage.getItem('pw_token') || null;

  async function request(method, path, body = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (_token) headers['Authorization'] = 'Bearer ' + _token;
    try {
      const res = await fetch(BASE + path, {
        method,
        headers,
        credentials: 'include', // sends httpOnly pw_access cookie automatically
        body: body ? JSON.stringify(body) : null
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error ' + res.status);
      return json.data;
    } catch (err) {
      console.warn('[PW_API] offline/error:', err.message);
      return null;
    }
  }

  // withFallback(apiFn, localFallbackFn) — usa API si disponible, sino localStorage
  async function withFallback(apiFn, localFn) {
    const result = await apiFn();
    if (result !== null) return result;
    return localFn ? localFn() : null;
  }

  return {
    // AUTH
    auth: {
      async login(email, password, type = 'client') {
        const data = await request('POST', '/auth/login.php', { email, password, type });
        if (data && data.token) {
          _token = data.token;
          localStorage.setItem('pw_token', data.token);
          // Mirror legacy localStorage keys
          if (type === 'client') {
            localStorage.setItem('pw_client_auth', 'true');
            localStorage.setItem('pw_client_name', data.user.name);
            localStorage.setItem('pw_client_email', data.user.email);
            localStorage.setItem('pw_client_plan', data.user.plan_type);
            localStorage.setItem('pw_client_code', data.user.code);
          } else {
            localStorage.setItem('pw_admin_auth', btoa(email + ':' + Date.now()));
          }
        }
        return data;
      },
      async logout() {
        await request('POST', '/auth/logout.php');
        _token = null;
        ['pw_token','pw_client_auth','pw_client_name','pw_client_email','pw_client_plan','pw_client_code','pw_admin_auth'].forEach(k => localStorage.removeItem(k));
      },
      async me() {
        return request('GET', '/auth/me.php');
      }
    },

    // CLIENT PROFILE
    profile: {
      async get() {
        return withFallback(
          () => request('GET', '/clients/profile.php'),
          () => JSON.parse(localStorage.getItem('pw_profile') || '{}')
        );
      },
      async update(data) {
        const result = await request('PUT', '/clients/profile.php', data);
        if (result) localStorage.setItem('pw_profile', JSON.stringify(data));
        return result;
      }
    },

    // PROJECTS
    projects: {
      async list() {
        return withFallback(
          () => request('GET', '/projects/index.php'),
          () => JSON.parse(localStorage.getItem('pw_projects') || '[]')
        );
      },
      async get(id) {
        return request('GET', '/projects/index.php?id=' + id);
      }
    },

    // DELIVERABLES
    deliverables: {
      async list(projectId) {
        return withFallback(
          () => request('GET', '/deliverables/index.php?project_id=' + projectId),
          () => []
        );
      }
    },

    // INVOICES
    invoices: {
      async list() {
        return withFallback(
          () => request('GET', '/invoices/index.php'),
          () => JSON.parse(localStorage.getItem('pw_invoices') || '[]')
        );
      }
    },

    // ADMIN
    admin: {
      clients: {
        list: () => request('GET', '/admin/clients.php'),
        get: (id) => request('GET', '/admin/clients.php?id=' + id),
        create: (data) => request('POST', '/admin/clients.php', data),
        update: (id, data) => request('PUT', '/admin/clients.php?id=' + id, data)
      },
      projects: {
        list: (filters = {}) => {
          const qs = new URLSearchParams(filters).toString();
          return request('GET', '/admin/projects.php' + (qs ? '?' + qs : ''));
        },
        update: (id, data) => request('PUT', '/admin/projects.php?id=' + id, data)
      },
      invoices: {
        list: (filters = {}) => {
          const qs = new URLSearchParams(filters).toString();
          return request('GET', '/admin/invoices.php' + (qs ? '?' + qs : ''));
        },
        markPaid: (id, method) => request('PUT', '/admin/invoices.php?id=' + id, { status: 'pagada', payment_method: method })
      }
    }
  };
})();
