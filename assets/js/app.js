/**
 * QR-SAR - Main Global Application JavaScript
 * KPP Kendari - Basarnas
 */

// ==========================================
// UNIFIED GLOBAL THEME CONTROLLER
// ==========================================

function applyThemeToDOM(theme) {
    const isDark = theme === 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    // 1. Update Navbar Icon
    const navIcon = document.getElementById('theme-icon');
    if (navIcon) {
        if (isDark) {
            navIcon.innerHTML = `<svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`;
        } else {
            navIcon.innerHTML = `<svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
        }
    }

    // 2. Update Sidebar Icon & Label
    const sidebarIcon = document.getElementById('sidebarThemeIcon');
    const sidebarLabel = document.getElementById('sidebarThemeLabel');
    if (sidebarIcon) {
        if (isDark) {
            sidebarIcon.innerHTML = `<svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>`;
        } else {
            sidebarIcon.innerHTML = `<svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>`;
        }
    }
    if (sidebarLabel) {
        sidebarLabel.textContent = isDark ? 'Terang' : 'Gelap';
    }

    // 3. Update Login Screen Toggle
    const loginText = document.getElementById('loginThemeText');
    const loginIcon = document.getElementById('loginThemeIcon');
    if (loginText) {
        loginText.textContent = isDark ? 'Mode Terang' : 'Mode Gelap';
    }
    if (loginIcon) {
        if (isDark) {
            loginIcon.innerHTML = `<svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`;
        } else {
            loginIcon.innerHTML = `<svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
        }
    }

    // 4. Update Radio Inputs in Settings
    document.querySelectorAll('input[name="default_theme"]').forEach(radio => {
        if (radio.value === theme) radio.checked = true;
    });

    window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: theme } }));
}

function setAppTheme(targetTheme, notify = false) {
    document.body.classList.add('theme-transitioning');
    localStorage.setItem('qr_sar_theme', targetTheme);
    applyThemeToDOM(targetTheme);
    if (notify) {
        showToast(targetTheme === 'dark' ? 'Mode Gelap Aktif (Taktis Basarnas)' : 'Mode Terang Aktif', 'info');
    }
    setTimeout(() => {
        document.body.classList.remove('theme-transitioning');
    }, 250);
}

function toggleAppTheme() {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    setAppTheme(next, true);
}

// Global Indonesian Date Formatter: "12 Oktober 2026"
function formatTanggalIndo(dateStr) {
    if (!dateStr) return '-';
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    if (dateStr instanceof Date) {
        if (isNaN(dateStr.getTime())) return '-';
        return `${dateStr.getDate()} ${months[dateStr.getMonth()]} ${dateStr.getFullYear()}`;
    }
    const str = String(dateStr).trim();
    // Check YYYY-MM-DD (with optional time)
    const isoMatch = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (isoMatch) {
        const y = parseInt(isoMatch[1], 10);
        const m = parseInt(isoMatch[2], 10) - 1;
        const d = parseInt(isoMatch[3], 10);
        if (m >= 0 && m < 12) return `${d} ${months[m]} ${y}`;
    }
    // Check DD/MM/YYYY or DD-MM-YYYY
    const slashMatch = str.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
    if (slashMatch) {
        const d = parseInt(slashMatch[1], 10);
        const m = parseInt(slashMatch[2], 10) - 1;
        const y = parseInt(slashMatch[3], 10);
        if (m >= 0 && m < 12) return `${d} ${months[m]} ${y}`;
    }
    const cleanStr = str.replace(' ', 'T');
    const d = new Date(cleanStr);
    if (isNaN(d.getTime())) return str;
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

function formatJamWita(dateStr) {
    if (!dateStr) return '00:00';
    if (dateStr instanceof Date) {
        if (isNaN(dateStr.getTime())) return '00:00';
        const hh = String(dateStr.getHours()).padStart(2, '0');
        const mm = String(dateStr.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    }
    const cleanStr = String(dateStr).trim().replace(' ', 'T');
    const d = new Date(cleanStr.includes('T') ? cleanStr : dateStr);
    if (isNaN(d.getTime())) return '00:00';
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${hh}:${mm}`;
}

function setAppPalette(palette, notify = false) {
    const validPalettes = ['orange', 'navy', 'emerald', 'gold'];
    const p = validPalettes.includes(palette) ? palette : 'orange';
    document.documentElement.setAttribute('data-palette', p);
    localStorage.setItem('qr_sar_palette', p);

    // Update palette choices checkmarks & active states
    document.querySelectorAll('.palette-choice').forEach(btn => {
        const choicePal = btn.getAttribute('data-palette');
        const check = btn.querySelector('.palette-check');
        if (choicePal === p) {
            btn.classList.add('bg-orange-500/15', 'text-orange-600', 'dark:text-orange-400', 'font-bold');
            if (check) check.classList.remove('hidden');
        } else {
            btn.classList.remove('bg-orange-500/15', 'text-orange-600', 'dark:text-orange-400', 'font-bold');
            if (check) check.classList.add('hidden');
        }
    });

    window.dispatchEvent(new CustomEvent('paletteChanged', { detail: { palette: p } }));
    if (notify && typeof showToast === 'function') {
        const names = {
            orange: 'Basarnas Orange',
            navy: 'Operasi Laut (Navy)',
            emerald: 'SAR Hutan (Emerald)',
            gold: 'Taktis Emas (Gold)'
        };
        showToast(`Tema warna ${names[p] || p} aktif`, 'success');
    }
}

window.setAppTheme = setAppTheme;
window.toggleAppTheme = toggleAppTheme;
window.setAppPalette = setAppPalette;
window.formatTanggalIndo = formatTanggalIndo;
window.formatJamWita = formatJamWita;

document.addEventListener('DOMContentLoaded', () => {
    // Read and apply saved theme immediately
    const savedTheme = localStorage.getItem('qr_sar_theme') || document.documentElement.getAttribute('data-theme') || 'dark';
    applyThemeToDOM(savedTheme);

    // Read and apply saved palette immediately
    const savedPalette = localStorage.getItem('qr_sar_palette') || document.documentElement.getAttribute('data-palette') || 'orange';
    setAppPalette(savedPalette, false);

    // Navbar Toggle Listener
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', toggleAppTheme);
    }

    // Sidebar Toggle Listener (Single unified listener)
    const sidebarThemeToggleBtn = document.getElementById('sidebarThemeToggleBtn');
    if (sidebarThemeToggleBtn) {
        sidebarThemeToggleBtn.addEventListener('click', toggleAppTheme);
    }

    // Mobile sidebar toggle fallback if backdrop handler not active
    const menuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarMobileBackdrop');
    if (menuBtn && sidebar && !backdrop) {
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // Modal close triggers
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay') || btn.closest('[id^="modal-"]');
            if (modal) closeModal(modal);
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open, [id^="modal-"]:not(.hidden)').forEach(modal => {
                if (modal.classList.contains('open') || (!modal.classList.contains('hidden') && modal.style.display !== 'none')) {
                    closeModal(modal);
                }
            });
        }
    });

    // Close modal on backdrop click (outside modal dialog container)
    document.addEventListener('click', (e) => {
        if (e.target && e.target.classList && (e.target.classList.contains('modal-overlay') || e.target.classList.contains('bra-modal-overlay'))) {
            closeModal(e.target);
        }
    });
});

function updateThemeIcon(theme) {
    applyThemeToDOM(theme);
}

// Check if dark mode is active
function isAppDarkMode() {
    return document.documentElement.classList.contains('dark') ||
           document.documentElement.getAttribute('data-theme') === 'dark' ||
           document.body.classList.contains('dark');
}

// SweetAlert2 Theme Configuration Factory
function getSwalTheme() {
    const dark = isAppDarkMode();
    return {
        background: dark ? '#0f172a' : '#ffffff',
        color: dark ? '#f8fafc' : '#0f172a',
        confirmButtonColor: '#ea580c',
        cancelButtonColor: dark ? '#334155' : '#94a3b8',
        customClass: {
            popup: 'rounded-2xl border ' + (dark ? 'border-slate-800' : 'border-slate-200') + ' shadow-2xl font-sans',
            title: 'text-base font-bold ' + (dark ? 'text-white' : 'text-slate-900'),
            htmlContainer: 'text-xs ' + (dark ? 'text-slate-400' : 'text-slate-600'),
            confirmButton: 'px-4 py-2 rounded-xl text-xs font-bold shadow-md cursor-pointer',
            cancelButton: 'px-4 py-2 rounded-xl text-xs font-bold cursor-pointer'
        }
    };
}

// Global Toast Manager (Powered by SweetAlert2 with fallback)
function showToast(message, type = 'info') {
    if (window.Swal) {
        const theme = getSwalTheme();
        const iconType = type === 'error' ? 'error' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true,
            background: theme.background,
            color: theme.color,
            customClass: {
                popup: 'rounded-2xl border ' + (isAppDarkMode() ? 'border-slate-800' : 'border-slate-200') + ' shadow-2xl font-sans text-xs'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        Toast.fire({
            icon: iconType,
            title: message
        });
        return;
    }

    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.style.cssText = `
        pointer-events:auto;min-width:280px;max-width:400px;padding:12px 18px;border-radius:12px;
        font-size:13px;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 10px 25px rgba(0,0,0,0.3);
        transform:translateY(20px);opacity:0;transition:all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    `;

    const safeMessage = typeof escapeHtml === 'function' ? escapeHtml(message) : message;

    if (type === 'success') {
        toast.style.backgroundColor = '#10B981';
        toast.style.color = '#FFFFFF';
        toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <span>${safeMessage}</span>`;
    } else if (type === 'error') {
        toast.style.backgroundColor = '#EF4444';
        toast.style.color = '#FFFFFF';
        toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> <span>${safeMessage}</span>`;
    } else {
        toast.style.backgroundColor = '#F97316';
        toast.style.color = '#FFFFFF';
        toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> <span>${safeMessage}</span>`;
    }

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateY(10px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 250);
    }, 3500);
}

// Copy Text to Clipboard
async function copyToClipboard(text, successMsg = 'Teks Berhasil Disalin ke Clipboard!') {
    try {
        await navigator.clipboard.writeText(text);
        showToast(successMsg, 'success');
        return true;
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast(successMsg, 'success');
        return true;
    }
}

// Modal helper
function openModal(id) {
    const m = typeof id === 'string' ? document.getElementById(id) : id;
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('open');
    if (m.style.display === 'none' || !m.style.display) {
        m.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const m = typeof id === 'string' ? document.getElementById(id) : id;
    if (!m) return;
    m.classList.remove('open');
    m.classList.add('hidden');
    m.style.display = 'none';

    // If specific custom close callback exists for bra paper preview
    if ((m.id === 'modalFullBraPreview' || m.classList.contains('bra-modal-overlay')) && typeof window.closeModalPaperPreview === 'function') {
        window.closeModalPaperPreview();
    }

    // Check if any modal is still actively open
    const openModals = document.querySelectorAll('.modal-overlay.open, .bra-modal-overlay.open, [id^="modal-"]:not(.hidden)');
    let stillOpen = false;
    openModals.forEach(el => {
        if (el !== m && (el.classList.contains('open') || (el.style.display !== 'none' && !el.classList.contains('hidden')))) {
            stillOpen = true;
        }
    });

    if (!stillOpen) {
        document.body.style.overflow = '';
    }
}

window.openModal = openModal;
window.closeModal = closeModal;

// Global SweetAlert2 Confirmation helper
async function confirmAction(title, message, confirmBtnText = 'Ya, Lanjutkan', isDanger = false) {
    if (window.Swal) {
        const theme = getSwalTheme();
        const result = await Swal.fire({
            title: title || 'Konfirmasi',
            text: message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
            icon: isDanger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDanger ? '#EF4444' : theme.confirmButtonColor,
            cancelButtonColor: theme.cancelButtonColor,
            confirmButtonText: confirmBtnText,
            cancelButtonText: 'Batal',
            reverseButtons: true,
            allowOutsideClick: false,
            background: theme.background,
            color: theme.color,
            customClass: theme.customClass
        });
        return result.isConfirmed;
    }
    return confirm(message || title);
}

async function showConfirmDialog(title, message, isDanger = true) {
    return await confirmAction(title, message, 'Ya, Hapus Data', isDanger);
}

function showSuccessAlert(title, text = '') {
    if (window.Swal) {
        const theme = getSwalTheme();
        return Swal.fire({
            icon: 'success',
            title,
            text,
            confirmButtonText: 'OK, Lanjutkan',
            confirmButtonColor: theme.confirmButtonColor,
            background: theme.background,
            color: theme.color,
            customClass: theme.customClass
        });
    }
    showToast(title, 'success');
}

function showErrorAlert(title, text = '') {
    if (window.Swal) {
        const theme = getSwalTheme();
        return Swal.fire({
            icon: 'error',
            title,
            text,
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#ef4444',
            background: theme.background,
            color: theme.color,
            customClass: theme.customClass
        });
    }
    showToast(title, 'error');
}

// Global click delegation for [data-confirm] elements (links & buttons)
document.addEventListener('click', async (e) => {
    // Check if target or its ancestor has data-confirm (excluding forms, forms handled via submit or fallback)
    const triggerBtn = e.target.closest('a[data-confirm], button[data-confirm]');
    if (triggerBtn) {
        if (triggerBtn.dataset.swalApproved === 'true') return;

        e.preventDefault();
        e.stopPropagation();

        const message = triggerBtn.getAttribute('data-confirm') || 'Apakah Anda yakin?';
        const title = triggerBtn.getAttribute('data-confirm-title') || 'Konfirmasi Tindakan';
        const btnText = triggerBtn.getAttribute('data-confirm-btn') || 'Ya, Lanjutkan';
        const isDanger = triggerBtn.classList.contains('text-red-500') || 
                         triggerBtn.classList.contains('text-red-600') || 
                         triggerBtn.classList.contains('bg-red-500') || 
                         triggerBtn.getAttribute('data-confirm-danger') === 'true';

        const ok = await confirmAction(title, message, btnText, isDanger);
        if (ok) {
            triggerBtn.dataset.swalApproved = 'true';
            if (triggerBtn.tagName === 'A' && triggerBtn.href) {
                window.location.href = triggerBtn.href;
            } else if (triggerBtn.tagName === 'BUTTON' && triggerBtn.type === 'submit' && triggerBtn.form) {
                if (typeof triggerBtn.form.requestSubmit === 'function') {
                    triggerBtn.form.requestSubmit(triggerBtn);
                } else {
                    triggerBtn.form.submit();
                }
            } else if (typeof triggerBtn.onclick === 'function') {
                triggerBtn.click();
            }
            delete triggerBtn.dataset.swalApproved;
        }
        return;
    }

    // If button inside form without data-confirm, let submit delegation handle it
    const formTrigger = e.target.closest('form[data-confirm]');
    if (formTrigger && e.target.closest('button[type="submit"]')) {
        // Let natural submit fire so document 'submit' listener handles it cleanly
        return;
    }
}, true);

// Global submit delegation for forms with [data-confirm]
document.addEventListener('submit', async (e) => {
    const form = e.target;
    const confirmMsg = form.getAttribute('data-confirm');
    if (!confirmMsg || form.dataset.swalApproved === 'true') return;

    e.preventDefault();
    e.stopPropagation();

    const title = form.getAttribute('data-confirm-title') || 'Konfirmasi Formulir';
    const btnText = form.getAttribute('data-confirm-btn') || 'Ya, Lanjutkan';
    const isDanger = form.getAttribute('data-confirm-danger') === 'true' || 
                     form.querySelector('.bg-red-500, .bg-red-600, .text-red-500') !== null;
    const ok = await confirmAction(title, confirmMsg, btnText, isDanger);
    if (ok) {
        form.dataset.swalApproved = 'true';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }
}, true);

// Global Form Auto-Submit Loading Feedback & Double-Submission Guard
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented) return;

    const submitBtn = form.querySelector('button[type="submit"]:not([data-no-loading])');
    if (submitBtn && !submitBtn.disabled) {
        setTimeout(() => {
            if (!submitBtn.dataset.originalHtml) {
                submitBtn.dataset.originalHtml = submitBtn.innerHTML;
            }
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-80', 'cursor-wait');
            const svg = submitBtn.querySelector('svg');
            if (svg) {
                svg.classList.add('animate-spin');
            } else {
                submitBtn.insertAdjacentHTML('afterbegin', '<span class="inline-block animate-spin mr-1.5 font-bold">⟳</span> ');
            }
        }, 15);
    }
});

// ==========================================
// GLOBAL HTML SANITIZATION UTILITY
// ==========================================
window.escapeHtml = function (str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

// ==========================================
// GLOBAL AUTOMATIC FETCH CSRF INTERCEPTOR
// ==========================================
(function() {
    const originalFetch = window.fetch;
    if (!originalFetch) return;

    window.fetch = function(input, init) {
        init = init || {};
        const method = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();

        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const token = window.CSRF_TOKEN || 
                          (document.querySelector('meta[name="csrf-token"]') ? 
                           document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null);

            if (token) {
                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-Token')) {
                        init.headers.set('X-CSRF-Token', token);
                    }
                } else if (Array.isArray(init.headers)) {
                    const hasCsrf = init.headers.some(h => (h[0] || '').toLowerCase() === 'x-csrf-token');
                    if (!hasCsrf) {
                        init.headers.push(['X-CSRF-Token', token]);
                    }
                } else {
                    init.headers = init.headers || {};
                    if (!init.headers['X-CSRF-Token'] && !init.headers['x-csrf-token']) {
                        init.headers['X-CSRF-Token'] = token;
                    }
                }
            }
        }

        return originalFetch.call(this, input, init);
    };
})();

