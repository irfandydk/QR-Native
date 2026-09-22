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
  HelpCircle,
  CheckCircle2,
  X,
  RefreshCw,
} from 'lucide-react';
import { UserSession } from '../types';
import { showSuccessAlert, showErrorAlert } from '../utils/alerts';
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

  // Direct default password reset state
  const [showForgotModal, setShowForgotModal] = useState(false);
  const [isResettingDefault, setIsResettingDefault] = useState(false);

  // Load global satker settings
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

  // Handler for direct Reset to Default Password 'Kendari2026'
  const handleResetToDefault = async () => {
    setIsResettingDefault(true);
    try {
      const res = await fetch('/api/auth/reset-default', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
      });

      const data = await res.json();

      if (data.success) {
        setPassword('Kendari2026');
        setShowForgotModal(false);
        await showSuccessAlert(
          'Kata Sandi Direset ke Default',
          'Kata sandi akun administrator telah dikembalikan ke kata sandi standar sistem: "Kendari2026". Form login telah diisi secara otomatis.'
        );
      } else {
        throw new Error(data.error || 'Gagal mereset kata sandi.');
      }
    } catch (err: any) {
      showErrorAlert('Gagal Reset Kata Sandi', err.message || 'Terjadi kesalahan sistem.');
    } finally {
      setIsResettingDefault(false);
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
                  <h3 className="font-bold text-sm">Pemulihan Kata Sandi Administrator</h3>
                  <p className="text-[11px] text-slate-400">Kembalikan kata sandi ke standar sistem</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowForgotModal(false)}
                className="text-slate-400 hover:text-slate-200 p-1 rounded-lg cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Direct Reset to Default Kendari2026 */}
            <div className="space-y-4 text-xs">
              <div className="p-4 rounded-xl bg-orange-500/10 border border-orange-500/20 space-y-3">
                <div className="flex items-center gap-2 text-orange-600 dark:text-orange-400 font-bold text-sm">
                  <ShieldCheck className="w-4 h-4" />
                  <span>Reset ke Password Standar</span>
                </div>
                <p className="text-slate-600 dark:text-slate-300 leading-relaxed text-xs">
                  Untuk kelancaran penugasan operasional SAR, Anda dapat langsung mengembalikan kata sandi akun administrator (<strong>admin</strong>) ke kata sandi standar sistem:
                </p>
                <div className="p-3 rounded-lg bg-white dark:bg-slate-950 font-mono text-center border border-orange-500/30">
                  <span className="text-[11px] text-slate-400 uppercase tracking-wider block mb-0.5 font-sans font-medium">Password Default Sistem:</span>
                  <span className="text-base font-black text-orange-600 dark:text-orange-400 tracking-wider">Kendari2026</span>
                </div>
                <p className="text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
                  Setelah menekan tombol reset, kata sandi di sistem akan otomatis diperbarui dan form login akan langsung terisi dengan kata sandi tersebut.
                </p>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowForgotModal(false)}
                  className={`px-4 py-2 rounded-xl font-semibold border cursor-pointer ${
                    isDarkMode
                      ? 'border-slate-700 text-slate-300 hover:bg-slate-800'
                      : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                  }`}
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={handleResetToDefault}
                  disabled={isResettingDefault}
                  className="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl font-bold shadow-md shadow-orange-600/25 disabled:opacity-50 cursor-pointer transition-all"
                >
                  {isResettingDefault ? (
                    <>
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                      <span>Mereset Sandi...</span>
                    </>
                  ) : (
                    <>
                      <KeyRound className="w-3.5 h-3.5" />
                      <span>Reset ke "Kendari2026"</span>
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

