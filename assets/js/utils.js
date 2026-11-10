// Apollon Media - shared namespace, state and utilities.
'use strict';

window.Apollon = window.Apollon || {};

Apollon.state = {
  csrfToken: '',
  currentUser: null,
  currentView: { type: 'get_songs', param: '', sort: 'title_asc' },
  currentSong: null,
  queue: [],
  originalQueue: [],
  queueIndex: -1,
  isPlaying: false,
  isShuffle: false,
  repeatMode: 'none',
  previousVolume: 1,
  songIdForPlaylist: null,
  isAddingToPlaylist: false,
  webradios: [],
  sortable: null,
  contextMenuItemEl: null,
  currentPage: 1,
  isLoadingMore: false,
  allContentloaded: false,
};

Apollon.PAGE_SIZE = 25;
// Upload chunk size: 1 MB per request, so uploads stay under the default
// post_max_size (8 MB) without any php.ini changes.
Apollon.UPLOAD_CHUNK_SIZE = 1024 * 1024;
Apollon.config = window.apollonConfig || { guestListen: true };
// Theme: dark by default, light only when <html data-theme="light"> (user preference).
Apollon.theme = {
  current: document.documentElement.getAttribute('data-theme') !== 'light' ? 'dark' : 'light',

  apply(theme) {
    this.current = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', this.current);
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', this.current === 'dark' ? '#121212' : '#f5f5f5');
  }
};
// i18n: catalog embedded by the server (window.apollonI18n, see src/Language.php).
Apollon.i18n = {
  lang: (window.apollonI18n && window.apollonI18n.lang) || 'en',
  strings: (window.apollonI18n && window.apollonI18n.strings) || {},

  /**
   * Translate a key, substituting ":name" placeholders from params.
   * Falls back to the key itself when it is missing from the catalog.
   */
  t(key, params = {}) {
    let text = Apollon.i18n.strings[key];
    if (text === undefined) text = key;
    for (const name of Object.keys(params)) {
      text = text.split(':' + name).join(String(params[name]));
    }
    return text;
  },

  byteUnits() {
    return Apollon.i18n.lang === 'fr' ? ['o', 'Ko', 'Mo', 'Go', 'To'] : ['B', 'KB', 'MB', 'GB', 'TB'];
  },

  locale() {
    return Apollon.i18n.lang === 'fr' ? 'fr-FR' : 'en-US';
  }
};
Apollon.VIEW_STORAGE_KEY = 'apollon_media_last_view';
// One-time migration of the saved view from the pre-rename key (Apollon Music).
try {
  const legacyViewKey = 'apollon_music_last_view';
  if (localStorage.getItem(Apollon.VIEW_STORAGE_KEY) === null && localStorage.getItem(legacyViewKey) !== null) {
    localStorage.setItem(Apollon.VIEW_STORAGE_KEY, localStorage.getItem(legacyViewKey));
    localStorage.removeItem(legacyViewKey);
  }
} catch (e) {}

Apollon.utils = {
  escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },

  escapeAttr(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
  },

  formatTime(seconds) {
    if (isNaN(seconds) || seconds < 0) return '0:00';
    const min = Math.floor(seconds / 60);
    const sec = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${min}:${sec}`;
  },

  formatSize(bytes) {
    if (!bytes || bytes <= 0) return '0 ' + Apollon.i18n.byteUnits()[0];
    const units = Apollon.i18n.byteUnits();
    let size = bytes;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
      size /= 1024;
      unit++;
    }
    return size.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
  },

  async fetchData(url, options = {}) {
    try {
      options.cache = 'no-store';
      options.headers = options.headers || {};
      if (Apollon.state.csrfToken) {
        options.headers['X-CSRF-Token'] = Apollon.state.csrfToken;
      }
      const res = await fetch(url, options);
      if (!res.ok) {
        const err = await res.json().catch(() => null);
        throw new Error(err ? err.message : Apollon.i18n.t('error.http_status', { status: res.status }));
      }
      if (res.headers.get("content-type")?.includes("application/json")) {
        return await res.json();
      }
      return await res.text();
    } catch (error) {
      Apollon.utils.showToast(error.message, 'error');
      return null;
    }
  },

  showToast(message, type = 'info') {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = "1100";
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : 'secondary'} border-0 rounded-4`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body text-truncate">${Apollon.utils.escapeHtml(message)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    document.body.appendChild(container);
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => container.remove());
  },

  /**
   * Persistent toast for long operations. Returns update(message) with a
   * .close() method.
   */
  showProgressToast(message) {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = "1100";
    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-white bg-secondary border-0 rounded-4 show';
    toastEl.setAttribute('role', 'status');
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body text-truncate">${Apollon.utils.escapeHtml(message)}</div>
      </div>`;
    document.body.appendChild(container);
    const toast = new bootstrap.Toast(toastEl, { autohide: false });
    toast.show();
    const update = (msg) => {
      const body = toastEl.querySelector('.toast-body');
      if (body) body.textContent = msg;
    };
    update.close = () => {
      toast.hide();
      toastEl.addEventListener('hidden.bs.toast', () => container.remove());
    };
    return update;
  },
};

// Origin Private File System cache for streamed audio blobs.
Apollon.opfs = {
  root: null,
  init() {
    if (navigator.storage && navigator.storage.getDirectory) {
      navigator.storage.getDirectory()
        .then(root => { this.root = root; })
        .catch(e => console.warn('OPFS warning:', e));
    }
  },
  async getBlob(name) {
    if (!this.root) return null;
    try {
      const handle = await this.root.getFileHandle(name);
      return await handle.getFile();
    } catch {
      return null;
    }
  },
  async saveBlob(name, blob) {
    if (!this.root) return;
    try {
      const handle = await this.root.getFileHandle(name, { create: true });
      const writable = await handle.createWritable();
      await writable.write(blob);
      await writable.close();
    } catch (e) {
      console.warn('OPFS save error:', e);
    }
  },
  async clear() {
    if (!this.root) return;
    try {
      for await (const [name] of this.root.entries()) {
        await this.root.removeEntry(name, { recursive: true });
      }
    } catch (e) {
      console.warn('OPFS clear error:', e);
    }
  },
};
Apollon.opfs.init();
