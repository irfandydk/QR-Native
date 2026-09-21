import Swal, { SweetAlertIcon } from 'sweetalert2';

// Check if dark mode is active
const isDark = () => {
  return document.documentElement.classList.contains('dark') || 
         document.body.classList.contains('dark') || 
         !document.documentElement.classList.contains('light');
};

const getThemeStyles = () => {
  const dark = isDark();
  return {
    background: dark ? '#0f172a' : '#ffffff',
    color: dark ? '#f1f5f9' : '#0f172a',
    confirmButtonColor: '#ea580c', // Basarnas rescue orange
    cancelButtonColor: dark ? '#334155' : '#94a3b8',
    customClass: {
      popup: 'rounded-2xl border border-slate-700 shadow-2xl font-sans',
      title: 'text-base font-bold',
      htmlContainer: 'text-xs text-slate-400',
      confirmButton: 'px-4 py-2 rounded-xl text-xs font-semibold shadow-md',
      cancelButton: 'px-4 py-2 rounded-xl text-xs font-semibold',
    },
  };
};

/**
 * Menampilkan SweetAlert Sukses (Simpan, Edit, dll)
 */
export const showSuccessAlert = async (title: string, text?: string) => {
  const styles = getThemeStyles();
  return Swal.fire({
    icon: 'success',
    title,
    text,
    timer: 2800,
    timerProgressBar: true,
    showConfirmButton: true,
    confirmButtonText: 'OK, Lanjutkan',
    confirmButtonColor: styles.confirmButtonColor,
    background: styles.background,
    color: styles.color,
    customClass: styles.customClass,
  });
};

/**
 * Menampilkan SweetAlert Error / Gagal
 */
export const showErrorAlert = async (title: string, text?: string) => {
  const styles = getThemeStyles();
  return Swal.fire({
    icon: 'error',
    title,
    text,
    confirmButtonText: 'Tutup',
    confirmButtonColor: '#ef4444',
    background: styles.background,
    color: styles.color,
    customClass: styles.customClass,
  });
};

/**
 * Menampilkan SweetAlert Peringatan
 */
export const showWarningAlert = async (title: string, text?: string) => {
  const styles = getThemeStyles();
  return Swal.fire({
    icon: 'warning',
    title,
    text,
    confirmButtonText: 'Mengerti',
    confirmButtonColor: '#f59e0b',
    background: styles.background,
    color: styles.color,
    customClass: styles.customClass,
  });
};

/**
 * Menampilkan SweetAlert Konfirmasi Aksi (misal Hapus, Tutup, Rollback)
 */
export const showConfirmDialog = async (
  title: string,
  textOrHtml: string,
  confirmButtonText = 'Ya, Lanjutkan',
  isDanger = true
): Promise<boolean> => {
  const styles = getThemeStyles();
  const isHtml = textOrHtml.includes('<') || textOrHtml.includes('\n');
  const result = await Swal.fire({
    title,
    ...(isHtml
      ? { html: textOrHtml.includes('<') ? textOrHtml : textOrHtml.replace(/\n/g, '<br/>') }
      : { text: textOrHtml }),
    icon: isDanger ? 'warning' : 'question',
    showCancelButton: true,
    confirmButtonColor: isDanger ? '#dc2626' : styles.confirmButtonColor,
    cancelButtonColor: styles.cancelButtonColor,
    confirmButtonText,
    cancelButtonText: 'Batal',
    reverseButtons: true,
    background: styles.background,
    color: styles.color,
    customClass: styles.customClass,
  });

  return result.isConfirmed;
};

/**
 * Menampilkan SweetAlert Konfirmasi Aksi dengan Rich HTML
 */
export const showHtmlConfirmDialog = async (
  title: string,
  html: string,
  confirmButtonText = 'Ya, Lanjutkan',
  isDanger = true
): Promise<boolean> => {
  return showConfirmDialog(title, html, confirmButtonText, isDanger);
};

/**
 * Menampilkan Notifikasi Toast Cepat (Salin, Download, dsb)
 */
export const showToast = (title: string, icon: SweetAlertIcon = 'success') => {
  const dark = isDark();
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    background: dark ? '#1e293b' : '#ffffff',
    color: dark ? '#f8fafc' : '#0f172a',
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
  });

  return Toast.fire({
    icon,
    title,
  });
};
