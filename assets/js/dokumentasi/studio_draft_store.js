/**
 * QR-SAR - Penyimpanan Draf Otomatis Studio Dokumentasi (IndexedDB)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menyimpan seluruh berkas foto/video mentah (File/Blob), serverPath yang sudah terunggah,
 * pengaturan trim/zoom/pan, isian form, serta job_id render aktif ke IndexedDB secara lokal
 * sehingga halaman yang di-refresh saat loading/stuck otomatis pulih tanpa memilih ulang media.
 */
(function (root) {
  'use strict';

  const DB_NAME = 'qr_sar_dok_studio_v1';
  const DB_VERSION = 1;
  const STORE_NAME = 'drafts';
  const MAX_DRAFT_AGE_MS = 48 * 60 * 60 * 1000; // 48 jam

  let dbPromise = null;

  function isSupported() {
    return typeof indexedDB !== 'undefined';
  }

  function openDb() {
    if (!isSupported()) {
      return Promise.resolve(null);
    }
    if (dbPromise) {
      return dbPromise;
    }
    dbPromise = new Promise((resolve) => {
      try {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
          const db = req.result;
          if (!db.objectStoreNames.contains(STORE_NAME)) {
            db.createObjectStore(STORE_NAME, { keyPath: 'scopeKey' });
          }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve(null);
      } catch (err) {
        resolve(null);
      }
    });
    return dbPromise;
  }

  async function save(scopeKey, data) {
    const db = await openDb();
    if (!db || !scopeKey) return false;
    return new Promise((resolve) => {
      try {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const record = Object.assign({}, data, {
          scopeKey: String(scopeKey),
          updatedAt: Date.now(),
        });
        const req = store.put(record);
        req.onsuccess = () => resolve(true);
        req.onerror = () => resolve(false);
      } catch (err) {
        resolve(false);
      }
    });
  }

  async function load(scopeKey) {
    const db = await openDb();
    if (!db || !scopeKey) return null;
    return new Promise((resolve) => {
      try {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);
        const req = store.get(String(scopeKey));
        req.onsuccess = () => {
          const rec = req.result || null;
          if (!rec) {
            resolve(null);
            return;
          }
          if (rec.updatedAt && (Date.now() - rec.updatedAt) > MAX_DRAFT_AGE_MS) {
            clear(scopeKey);
            resolve(null);
            return;
          }
          resolve(rec);
        };
        req.onerror = () => resolve(null);
      } catch (err) {
        resolve(null);
      }
    });
  }

  async function clear(scopeKey) {
    const db = await openDb();
    if (!db || !scopeKey) return false;
    return new Promise((resolve) => {
      try {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const req = store.delete(String(scopeKey));
        req.onsuccess = () => resolve(true);
        req.onerror = () => resolve(false);
      } catch (err) {
        resolve(false);
      }
    });
  }

  async function patchActiveJob(scopeKey, activeJob) {
    const existing = await load(scopeKey);
    if (!existing) return false;
    existing.activeJob = activeJob || null;
    return save(scopeKey, existing);
  }

  root.QrSarDokDraftStore = {
    isSupported,
    save,
    load,
    clear,
    patchActiveJob,
  };
})(typeof globalThis !== 'undefined' ? globalThis : window);
