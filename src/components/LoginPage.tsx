import React, { useState, useEffect } from 'react';
import {
  ShieldCheck,
  KeyRound,
  User,
  Radio,
  ArrowRight,
  Eye,
  EyeOff,
  Lock,
  Mail,
  HelpCircle,
  CheckCircle2,
  X,
  RefreshCw,
  Send,
} from 'lucide-react';
import { UserSession } from '../types';
import { showSuccessAlert, showErrorAlert, showToast } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';
import { BasarnasLogo } from './BasarnasLogo';

interface LoginPageProps {
  onLoginSuccess: (user: UserSession) => void;
}

export const LoginPage: React.FC<LoginPageProps> = ({ onLoginSuccess }) => {
  const { isDarkMode, accentColors } = useTheme();

  const [username, setUsername] = useState('admin');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);
  const [loading, setLoading] = useState(false);
  const [satkerLogo, setSatkerLogo] = useState<string | null>(null);
  const [satkerName, setSatkerName] = useState<string>('KPP KENDARI');

  // Forgot password & Reset states
  const [showForgotModal, setShowForgotModal] = useState(false);
  const [isSendingReset, setIsSendingReset] = useState(false);
  const [resetSentInfo, setResetSentInfo] = useState<{
    email: string;
    resetToken?: string;
    resetLink?: string;
    message?: string;
  } | null>(null);

  // Direct reset inputs
  const [inputResetToken, setInputResetToken] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [isSubmittingReset, setIsSubmittingReset] = useState(false);

  // Load global satker settings & check for URL reset token
  useEffect(() => {
    const loadSettings = async () => {
      try {
        const localLogo = localStorage.getItem('sar_custom_logo_url');
        if (localLogo) setSatkerLogo(localLogo);

        const res = await fetch('/api/settings');
        const data = await res.json();
        if (data.success && data.data) {
          if (data.data.logo_url) setSatkerLogo(data.data.logo_url);
          if (data.data.satker_nama) setSatkerName(data.data.satker_nama);
        }
      } catch (err) {
        console.error('Failed to load satker settings on login:', err);
      }
    };

    loadSettings();
    const handleUpdate = () => loadSettings();
    window.addEventListener('satker-settings-updated', handleUpdate);

    // Check if ?resetToken= is present in URL
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      const urlToken = params.get('resetToken');
      if (urlToken) {
        setInputResetToken(urlToken);
        setShowForgotModal(true);
        setResetSentInfo({
          email: 'irfandydk@gmail.com',
          resetToken: urlToken,
          message: 'Token reset otomatis terdeteksi dari tautan email. Silakan buat kata sandi baru Anda.',
        });
      }
    }

    return () => window.removeEventListener('satker-settings-updated', handleUpdate);
  }, []);

  const processLogin = (userToLogin: UserSession) => {
    if (rememberMe) {
      localStorage.setItem('basarnas_user_session', JSON.stringify(userToLogin));
    } else {
      sessionStorage.setItem('basarnas_user_session', JSON.stringify(userToLogin));
    }
    onLoginSuccess(userToLogin);
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();

    const u = username.trim();
    const p = password.trim();

    if (!u) {
      showErrorAlert('Username Wajib Diisi', 'Silakan masukkan username admin.');
      return;
    }
    if (!p) {
      showErrorAlert('Password Wajib Diisi', 'Silakan masukkan kata sandi.');
      return;
    }

    setLoading(true);

    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: u, password: p }),
      });

      const data = await res.json();

      if (!res.ok || !data.success) {
        setLoading(false);
        showErrorAlert(
          'Login Gagal',
          data.error || 'Username atau kata sandi yang Anda masukkan salah. Pastikan menggunakan user "admin" dengan kata sandi yang sesuai.'
        );
        return;
      }

      setLoading(false);
      await showSuccessAlert(
        'Login Berhasil',
        `Selamat bertugas di Sistem QR SAR ${satkerName}.`
      );
      processLogin(data.user);
    } catch (err: any) {
      setLoading(false);
      showErrorAlert('Kesalahan Koneksi', 'Tidak dapat terhubung ke server autentikasi.');
    }
  };

  // Handler for Forgot Password request
  const handleRequestPasswordReset = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    setIsSendingReset(true);
    try {
      const res = await fetch('/api/auth/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: 'irfandydk@gmail.com' }),
      });

      const data = await res.json();

      if (data.success) {
        setResetSentInfo({
          email: data.email || 'irfandydk@gmail.com',
          resetToken: data.resetToken,
          resetLink: data.resetLink,
          message: data.message,
        });
        if (data.resetToken) {
          setInputResetToken(data.resetToken);
        }
        showToast('Link reset password berhasil dikirim ke irfandydk@gmail.com', 'success');
      } else {
        throw new Error(data.error || 'Gagal memproses permintaan reset.');
      }
    } catch (err: any) {
      showErrorAlert('Gagal Mengirim Link Reset', err.message || 'Terjadi kesalahan sistem.');
    } finally {
      setIsSendingReset(false);
    }
  };

  // Handler for Submitting New Password using Token
  const handleSubmitNewPassword = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!inputResetToken.trim()) {
      showErrorAlert('Token Wajib Diisi', 'Silakan masukkan token reset yang dikirim ke email.');
      return;
    }

    if (!newPassword || newPassword.length < 4) {
      showErrorAlert('Kata Sandi Terlalu Pendek', 'Kata sandi baru minimal harus 4 karakter.');
      return;
    }

    if (newPassword !== confirmPassword) {
      showErrorAlert('Konfirmasi Tidak Sesuai', 'Konfirmasi kata sandi baru tidak sama.');
      return;
    }

    setIsSubmittingReset(true);
    try {
      const res = await fetch('/api/auth/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token: inputResetToken.trim(),
          newPassword: newPassword.trim(),
        }),
      });

      const data = await res.json();

      if (data.success) {
        // Clear query parameters in URL if any
        if (typeof window !== 'undefined' && window.location.search) {
          window.history.replaceState({}, document.title, window.location.pathname);
        }
        setShowForgotModal(false);
        setResetSentInfo(null);
        setInputResetToken('');
        setNewPassword('');
        setConfirmPassword('');
        setPassword(newPassword.trim());
        await showSuccessAlert(
          'Kata Sandi Berhasil Direset',
          'Kata sandi administrator telah berhasil diperbarui. Silakan login sekarang.'
        );
      } else {
        throw new Error(data.error || 'Gagal mereset kata sandi.');
      }
    } catch (err: any) {
      showErrorAlert('Gagal Reset Kata Sandi', err.message || 'Terjadi kesalahan server.');
    } finally {
      setIsSubmittingReset(false);
    }
  };

  return (
    <div
      className={`min-h-screen flex items-center justify-center p-4 sm:p-6 transition-colors duration-200 ${
        isDarkMode
          ? 'bg-slate-950 text-slate-100'
          : 'bg-slate-100 text-slate-900'
      }`}
    >
      {/* Background Decorative Rings */}
      <div className="fixed inset-0 pointer-events-none overflow-hidden flex items-center justify-center opacity-30 dark:opacity-20">
        <div className="w-[600px] h-[600px] rounded-full border border-orange-500/30 animate-pulse" />
        <div className="absolute w-[900px] h-[900px] rounded-full border border-cyan-500/20" />
      </div>

      {/* Clean Centered Login Modal */}
      <div
        className={`relative w-full max-w-md z-10 rounded-3xl p-6 sm:p-8 shadow-2xl border transition-all ${
          isDarkMode
            ? 'bg-slate-900/95 border-slate-800'
            : 'bg-white border-slate-200 shadow-xl'
        }`}
      >
        {/* Top Header with Global Logo */}
        <div className="text-center pb-6 border-b border-slate-200 dark:border-slate-800">
          <div className="flex justify-center mb-3.5">
            <div className="p-3 rounded-2xl bg-white dark:bg-slate-800 shadow-md border border-slate-200 dark:border-slate-700 flex items-center justify-center">
              <BasarnasLogo size={56} logoUrl={satkerLogo || undefined} />
            </div>
          </div>

          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase mb-2 bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
            <Radio className="w-3 h-3" />
            <span>SISTEM OPERASI SAR • {satkerName}</span>
          </div>

          <h1 className="text-xl sm:text-2xl font-black tracking-tight leading-tight">
            QR SAR SYSTEM
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Kantor Pencarian dan Pertolongan Kelas A Kendari
          </p>
        </div>

        {/* Login Form */}
        <form onSubmit={handleLogin} className="space-y-4.5 pt-6">
          {/* Username Input */}
          <div>
            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
              Username Administrator
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <User className="w-4 h-4" />
              </div>
              <input
                type="text"
                required
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="admin"
                className={`w-full pl-10 pr-3 py-2.5 rounded-xl text-xs font-medium border transition-colors outline-none focus:ring-2 font-mono ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500 focus:ring-orange-500/20 placeholder:text-slate-500'
                    : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500 focus:ring-orange-500/20 placeholder:text-slate-400'
                }`}
              />
            </div>
          </div>

          {/* Password Input */}
          <div>
            <div className="flex items-center justify-between mb-1.5">
              <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                Kata Sandi
              </label>
              <button
                type="button"
                onClick={() => setShowForgotModal(true)}
                className="text-[11px] font-semibold text-orange-600 dark:text-orange-400 hover:underline cursor-pointer"
              >
                Lupa Password?
              </button>
            </div>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <KeyRound className="w-4 h-4" />
              </div>
              <input
                type={showPassword ? 'text' : 'password'}
                required
                autoFocus
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Masukkan kata sandi"
                className={`w-full pl-10 pr-10 py-2.5 rounded-xl text-xs font-medium border transition-colors outline-none focus:ring-2 ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500 focus:ring-orange-500/20 placeholder:text-slate-500'
                    : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500 focus:ring-orange-500/20 placeholder:text-slate-400'
                }`}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                title={showPassword ? 'Sembunyikan sandi' : 'Tampilkan sandi'}
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          {/* Remember me checkbox */}
          <div className="flex items-center justify-between text-xs pt-1">
            <label className="flex items-center gap-2 cursor-pointer select-none text-slate-600 dark:text-slate-400">
              <input
                type="checkbox"
                checked={rememberMe}
                onChange={(e) => setRememberMe(e.target.checked)}
                className="rounded border-slate-700 text-orange-600 focus:ring-orange-500"
              />
              <span>Ingat sesi masuk</span>
            </label>
          </div>

          {/* Submit Button */}
          <div className="pt-2">
            <button
              type="submit"
              disabled={loading}
              className={`w-full py-3 px-4 rounded-xl text-xs font-bold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 shadow-lg shadow-orange-600/25 flex items-center justify-center gap-2 transition-all hover:scale-[1.01] active:scale-[0.99] disabled:opacity-50 cursor-pointer`}
            >
              {loading ? (
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
              ) : (
                <>
                  <Lock className="w-3.5 h-3.5" />
                  <span>Masuk ke Sistem SAR</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </>
              )}
            </button>
          </div>
        </form>

        {/* Footer Slogan */}
        <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 text-center text-[10px] font-mono text-slate-400">
          "Avignam Jagat Samagram" — Semoga Selamatlah Alam Semesta
        </div>
      </div>

      {/* Forgot Password & Reset Modal */}
      {showForgotModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-150">
          <div
            className={`relative w-full max-w-md rounded-2xl border p-6 shadow-2xl space-y-4 ${
              isDarkMode
                ? 'bg-slate-900 border-slate-700 text-white'
                : 'bg-white border-slate-200 text-slate-900'
            }`}
          >
            {/* Modal Header */}
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                  <KeyRound className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="font-bold text-sm">Pemulihan Kata Sandi</h3>
                  <p className="text-[11px] text-slate-400">Reset password admin QR SAR Kendari</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => {
                  setShowForgotModal(false);
                  setResetSentInfo(null);
                }}
                className="text-slate-400 hover:text-slate-200 p-1 rounded-lg"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* If Link Not Sent Yet, Show Email Dispatch Form */}
            {!resetSentInfo ? (
              <div className="space-y-4 text-xs">
                <div className="p-3.5 rounded-xl bg-orange-500/10 border border-orange-500/20 space-y-2">
                  <div className="flex items-center gap-2 text-orange-600 dark:text-orange-400 font-bold">
                    <Mail className="w-4 h-4" />
                    <span>Kirim Tautan Reset ke Email</span>
                  </div>
                  <p className="text-slate-600 dark:text-slate-300 leading-relaxed">
                    Tautan dan kode verifikasi reset kata sandi akan otomatis dikirimkan ke alamat email administrator:
                  </p>
                  <div className="p-2.5 rounded-lg bg-white/70 dark:bg-slate-950/60 font-mono font-bold text-orange-600 dark:text-orange-400 border border-orange-500/20 flex items-center justify-between">
                    <span>irfandydk@gmail.com</span>
                    <ShieldCheck className="w-4 h-4 text-emerald-500" />
                  </div>
                </div>

                <div className="flex items-center justify-end gap-2 pt-2">
                  <button
                    type="button"
                    onClick={() => setShowForgotModal(false)}
                    className={`px-4 py-2 rounded-xl font-semibold border ${
                      isDarkMode
                        ? 'border-slate-700 text-slate-300 hover:bg-slate-800'
                        : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                    }`}
                  >
                    Batal
                  </button>
                  <button
                    type="button"
                    onClick={() => handleRequestPasswordReset()}
                    disabled={isSendingReset}
                    className="inline-flex items-center gap-1.5 px-5 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl font-bold shadow-md shadow-orange-600/25 disabled:opacity-50 cursor-pointer"
                  >
                    {isSendingReset ? (
                      <>
                        <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                        <span>Mengirim...</span>
                      </>
                    ) : (
                      <>
                        <Send className="w-3.5 h-3.5" />
                        <span>Kirim Link Reset Password</span>
                      </>
                    )}
                  </button>
                </div>
              </div>
            ) : (
              /* If Sent, Show Direct Reset Form */
              <div className="space-y-4 text-xs">
                <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-2.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                  <div className="space-y-1">
                    <p className="font-bold text-emerald-600 dark:text-emerald-400">
                      Link Reset Telah Dikirim!
                    </p>
                    <p className="text-slate-600 dark:text-slate-300 text-[11px] leading-relaxed">
                      Link dan token pemulihan telah dikirim ke <strong>{resetSentInfo.email}</strong>. Anda dapat langsung memasukkan kata sandi baru di bawah:
                    </p>
                  </div>
                </div>

                <form onSubmit={handleSubmitNewPassword} className="space-y-3">
                  <div>
                    <label className="block text-slate-400 mb-1 font-semibold">
                      Token / Kode Verifikasi
                    </label>
                    <input
                      type="text"
                      required
                      value={inputResetToken}
                      onChange={(e) => setInputResetToken(e.target.value)}
                      placeholder="Masukkan token dari email (contoh: SAR-XYZ-123)"
                      className={`w-full px-3 py-2 rounded-xl border font-mono font-bold ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white focus:border-orange-500'
                          : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500'
                      }`}
                    />
                  </div>

                  <div>
                    <label className="block text-slate-400 mb-1 font-semibold">
                      Kata Sandi Baru
                    </label>
                    <div className="relative">
                      <input
                        type={showNewPassword ? 'text' : 'password'}
                        required
                        value={newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                        placeholder="Minimal 4 karakter"
                        className={`w-full pl-3 pr-9 py-2 rounded-xl border ${
                          isDarkMode
                            ? 'bg-slate-950 border-slate-700 text-white focus:border-orange-500'
                            : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500'
                        }`}
                      />
                      <button
                        type="button"
                        onClick={() => setShowNewPassword(!showNewPassword)}
                        className="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-200 cursor-pointer"
                      >
                        {showNewPassword ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                      </button>
                    </div>
                  </div>

                  <div>
                    <label className="block text-slate-400 mb-1 font-semibold">
                      Konfirmasi Kata Sandi Baru
                    </label>
                    <input
                      type={showNewPassword ? 'text' : 'password'}
                      required
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      placeholder="Ulangi kata sandi baru"
                      className={`w-full px-3 py-2 rounded-xl border ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white focus:border-orange-500'
                          : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500'
                      }`}
                    />
                  </div>

                  <div className="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800">
                    <button
                      type="button"
                      onClick={() => handleRequestPasswordReset()}
                      disabled={isSendingReset}
                      className="text-[11px] text-orange-600 dark:text-orange-400 hover:underline flex items-center gap-1"
                    >
                      <RefreshCw className={`w-3 h-3 ${isSendingReset ? 'animate-spin' : ''}`} />
                      <span>Kirim ulang link</span>
                    </button>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setShowForgotModal(false)}
                        className={`px-3.5 py-1.5 rounded-xl font-semibold border ${
                          isDarkMode
                            ? 'border-slate-700 text-slate-300'
                            : 'border-slate-300 text-slate-700'
                        }`}
                      >
                        Batal
                      </button>
                      <button
                        type="submit"
                        disabled={isSubmittingReset}
                        className="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold shadow-md shadow-emerald-600/25 disabled:opacity-50 cursor-pointer"
                      >
                        {isSubmittingReset ? 'Menyimpan...' : 'Simpan Sandi Baru'}
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

