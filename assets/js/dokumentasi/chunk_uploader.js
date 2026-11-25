/**
 * QR-SAR - Pengunggah Chunked Resumable (4 MB/Potongan) dengan Progress Byte Riil & Retry
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Memecah unggahan media besar menjadi potongan kecil agar lolos batas body Cloudflare (100 MB),
 * Nginx, dan PHP lokal, serta tahan gangguan jaringan dengan retry eksponensial per potongan.
 */
(function (root) {
  'use strict';

  const DEFAULT_CHUNK_BYTES = 4 * 1024 * 1024; // 4 MB
  const MAX_CHUNK_RETRIES = 3;

  function makeAbortError() {
    try {
      return new DOMException('Proses dibatalkan oleh pengguna.', 'AbortError');
    } catch (e) {
      const err = new Error('Proses dibatalkan oleh pengguna.');
      err.name = 'AbortError';
      return err;
    }
  }

  function sleep(ms, signal) {
    return new Promise((resolve, reject) => {
      if (signal && signal.aborted) {
        reject(makeAbortError());
        return;
      }
      const timer = setTimeout(() => {
        if (signal) signal.removeEventListener('abort', onAbort);
        resolve();
      }, ms);
      const onAbort = () => {
        clearTimeout(timer);
        reject(makeAbortError());
      };
      if (signal) signal.addEventListener('abort', onAbort, { once: true });
    });
  }

  function sendXhrFormData(url, formData, csrfToken, signal, onChunkProgress) {
    return new Promise((resolve, reject) => {
      if (signal && signal.aborted) {
        reject(makeAbortError());
        return;
      }
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      xhr.withCredentials = true;
      xhr.timeout = 120000; // 2 menit per potongan 4 MB (sangat lega untuk jaringan lambat)
      if (csrfToken) {
        xhr.setRequestHeader('X-CSRF-Token', csrfToken);
      }
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      const onAbort = () => {
        try { xhr.abort(); } catch (e) {}
        reject(makeAbortError());
      };
      if (signal) {
        signal.addEventListener('abort', onAbort, { once: true });
      }
      const cleanup = () => {
        if (signal) signal.removeEventListener('abort', onAbort);
      };

      if (xhr.upload && typeof onChunkProgress === 'function') {
        xhr.upload.onprogress = (ev) => {
          if (ev.lengthComputable && ev.total > 0) {
            onChunkProgress(ev.loaded, ev.total);
          }
        };
      }

      xhr.onload = () => {
        cleanup();
        let data = null;
        try {
          data = JSON.parse(xhr.responseText || '{}');
        } catch (e) {
          data = null;
        }
        if (xhr.status >= 200 && xhr.status < 300 && data && data.success) {
          resolve(data);
        } else {
          const msg = (data && (data.message || data.error))
            || `Gagal mengunggah potongan (HTTP ${xhr.status}).`;
          const err = new Error(msg);
          err.httpStatus = xhr.status;
          reject(err);
        }
      };

      xhr.onerror = () => {
        cleanup();
        const err = new Error('Koneksi jaringan terputus saat mengunggah.');
        err.isNetwork = true;
        reject(err);
      };

      xhr.ontimeout = () => {
        cleanup();
        const err = new Error('Waktu tunggu unggah potongan habis.');
        err.isNetwork = true;
        reject(err);
      };

      xhr.send(formData);
    });
  }

  async function postJson(url, payload, csrfToken, signal) {
    const resp = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      signal,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    });
    let data = null;
    try {
      data = await resp.json();
    } catch (e) {
      data = null;
    }
    if (!resp.ok || !data || !data.success) {
      const err = new Error((data && (data.message || data.error)) || `Permintaan gagal (HTTP ${resp.status}).`);
      err.httpStatus = resp.status;
      throw err;
    }
    return data;
  }

  async function uploadBlobChunked(opts) {
    const blob = opts && opts.blob;
    if (!blob || !blob.size) {
      throw new Error('Berkas media kosong atau tidak dapat dibaca.');
    }
    const baseApi = (opts.uploadApiBase || ((window.BASE_URL || '') + '/api/index.php?path=/dokumentasi/upload')).replace(/\/+$/, '');
    const chunkSize = Math.max(512 * 1024, Number(opts.chunkSize) || DEFAULT_CHUNK_BYTES);
    const totalSize = blob.size;
    const totalChunks = Math.max(1, Math.ceil(totalSize / chunkSize));
    const csrfToken = opts.csrfToken || '';
    const signal = opts.signal;
    const onProgress = typeof opts.onProgress === 'function' ? opts.onProgress : null;

    if (signal && signal.aborted) {
      throw makeAbortError();
    }

    // 1. Inisialisasi sesi unggahan
    const initData = await postJson(
      `${baseApi}/init`,
      {
        purpose: opts.purpose || 'source',
        id_operasi: Number(opts.idOperasi) || 0,
        file_name: opts.fileName || 'media.bin',
        total_size: totalSize,
        total_chunks: totalChunks,
        csrf_token: csrfToken,
      },
      csrfToken,
      signal
    );

    const uploadId = initData.upload_id;
    let uploadedBytesCompleted = 0;

    // 2. Kirim tiap potongan berurutan dengan progress byte riil + retry eksponensial
    for (let idx = 0; idx < totalChunks; idx++) {
      if (signal && signal.aborted) {
        throw makeAbortError();
      }
      const start = idx * chunkSize;
      const end = Math.min(totalSize, start + chunkSize);
      const slice = blob.slice(start, end);
      const partBytes = slice.size;

      let attempt = 0;
      while (true) {
        attempt++;
        try {
          const fd = new FormData();
          fd.append('upload_id', uploadId);
          fd.append('chunk_index', String(idx));
          fd.append('chunk', slice, `part_${idx}.bin`);
          if (csrfToken) fd.append('csrf_token', csrfToken);

          await sendXhrFormData(
            `${baseApi}/chunk`,
            fd,
            csrfToken,
            signal,
            (loaded, total) => {
              if (onProgress) {
                const ratio = total > 0 ? Math.min(1, loaded / total) : 0;
                const currentBytes = uploadedBytesCompleted + Math.round(ratio * partBytes);
                onProgress(Math.min(totalSize, currentBytes), totalSize);
              }
            }
          );
          break;
        } catch (err) {
          if (err && err.name === 'AbortError') {
            throw err;
          }
          const retryable = !err.httpStatus || err.httpStatus >= 500 || err.httpStatus === 429 || err.isNetwork;
          if (!retryable || attempt >= MAX_CHUNK_RETRIES) {
            throw err;
          }
          await sleep(600 * Math.pow(2, attempt - 1), signal);
        }
      }

      uploadedBytesCompleted += partBytes;
      if (onProgress) {
        onProgress(Math.min(totalSize, uploadedBytesCompleted), totalSize);
      }
    }

    // 3. Rakit berkas di server
    return postJson(
      `${baseApi}/complete`,
      {
        upload_id: uploadId,
        csrf_token: csrfToken,
      },
      csrfToken,
      signal
    );
  }

  root.QrSarDokChunkUploader = {
    DEFAULT_CHUNK_BYTES,
    uploadBlobChunked,
  };
})(typeof globalThis !== 'undefined' ? globalThis : window);
