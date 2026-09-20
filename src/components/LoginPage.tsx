import React, { useState } from 'react';
import {
  ShieldCheck,
  KeyRound,
  User,
  Radio,
  ArrowRight,
  Eye,
  EyeOff,
  Info,
  Sparkles,
  Lock,
  Zap,
} from 'lucide-react';
import { UserSession } from '../types';
import { showSuccessAlert, showErrorAlert } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface LoginPageProps {
  onLoginSuccess: (user: UserSession) => void;
}

const ADMIN_CREDENTIALS = {
  username: 'admin',
  passwordDefault: 'admin',
  passwordAlt: 'basarnas115',
  nama: 'Administrator SAR',
  nip: 'ADMIN-SAR-KENDARI',
  pangkat: 'Super Admin',
  jabatan: 'Pengelola Sistem & Pusat Komando KPP Kendari',
  roleTitle: 'Administrator SAR',
};

export const LoginPage: React.FC<LoginPageProps> = ({ onLoginSuccess }) => {
  const { isDarkMode, accentColors } = useTheme();

  const [username, setUsername] = useState(ADMIN_CREDENTIALS.username);
  const [password, setPassword] = useState(ADMIN_CREDENTIALS.passwordDefault);
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);
  const [loading, setLoading] = useState(false);

  // Quick fill admin credentials
  const handleAutoFill = () => {
    setUsername(ADMIN_CREDENTIALS.username);
    setPassword(ADMIN_CREDENTIALS.passwordDefault);
  };

  const processLogin = (userToLogin: UserSession) => {
    if (rememberMe) {
      localStorage.setItem('basarnas_user_session', JSON.stringify(userToLogin));
    } else {
      sessionStorage.setItem('basarnas_user_session', JSON.stringify(userToLogin));
    }
    onLoginSuccess(userToLogin);
  };

  const handleLogin = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();

    if (!username.trim()) {
      showErrorAlert('Username Wajib Diisi', 'Silakan masukkan username akun admin.');
      return;
    }
    if (!password.trim()) {
      showErrorAlert('Password Wajib Diisi', 'Silakan masukkan kata sandi akun admin.');
      return;
    }

    setLoading(true);

    setTimeout(async () => {
      const userSession: UserSession = {
        id: 'USR-ADMIN',
        nama: ADMIN_CREDENTIALS.nama,
        nip: username.trim().toLowerCase() === 'admin' ? ADMIN_CREDENTIALS.nip : username.trim(),
        role: 'ADMIN',
        roleTitle: ADMIN_CREDENTIALS.roleTitle,
        pangkat: ADMIN_CREDENTIALS.pangkat,
        jabatan: ADMIN_CREDENTIALS.jabatan,
        loginAt: new Date().toISOString(),
      };

      setLoading(false);
      await showSuccessAlert(
        'Login Berhasil!',
        `Selamat bertugas, ${userSession.nama}. Anda login sebagai Pengelola Sistem KPP Kendari.`
      );
      processLogin(userSession);
    }, 350);
  };

  const handleQuickLoginDirect = () => {
    setUsername(ADMIN_CREDENTIALS.username);
    setPassword(ADMIN_CREDENTIALS.passwordDefault);
    setLoading(true);
    setTimeout(() => {
      const userSession: UserSession = {
        id: 'USR-ADMIN',
        nama: ADMIN_CREDENTIALS.nama,
        nip: ADMIN_CREDENTIALS.nip,
        role: 'ADMIN',
        roleTitle: ADMIN_CREDENTIALS.roleTitle,
        pangkat: ADMIN_CREDENTIALS.pangkat,
        jabatan: ADMIN_CREDENTIALS.jabatan,
        loginAt: new Date().toISOString(),
      };
      setLoading(false);
      processLogin(userSession);
    }, 250);
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

      <div className="relative w-full max-w-4xl z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
        {/* Left Side: Brand Banner & Basarnas Identity */}
        <div
          className={`lg:col-span-5 rounded-3xl p-6 sm:p-8 flex flex-col justify-between shadow-2xl border ${
            isDarkMode
              ? 'bg-gradient-to-b from-slate-900 via-slate-900/90 to-slate-950 border-slate-800'
              : 'bg-gradient-to-b from-orange-600 via-orange-600 to-amber-700 text-white border-orange-500'
          }`}
        >
          <div>
            {/* Header Badge */}
            <div className="flex items-center gap-2 mb-6">
              <span
                className={`px-3 py-1 rounded-full text-[11px] font-bold tracking-wider uppercase inline-flex items-center gap-1.5 ${
                  isDarkMode
                    ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                    : 'bg-white/20 text-white border border-white/30 backdrop-blur'
                }`}
              >
                <Radio className="w-3.5 h-3.5" />
                <span>PUSAT KOMANDO SAR • KPP KENDARI</span>
              </span>
            </div>

            {/* Logo & Office Title */}
            <div className="space-y-3">
              <div className="flex items-center gap-3">
                <div className="w-14 h-14 rounded-2xl bg-white p-2 shadow-lg flex items-center justify-center shrink-0">
                  <img
                    src="https://upload.wikimedia.org/wikipedia/commons/4/4e/Basarnas.svg"
                    alt="Logo Basarnas"
                    className="w-10 h-10 object-contain"
                    onError={(e) => {
                      (e.target as HTMLImageElement).src =
                        'https://raw.githubusercontent.com/twitter/twemoji/master/assets/72x72/1f6e1.png';
                    }}
                  />
                </div>
                <div>
                  <h1 className="text-xl sm:text-2xl font-black tracking-tight leading-none">
                    QR SAR KENDARI
                  </h1>
                  <p className="text-xs font-semibold opacity-90 mt-1">
                    Sistem Operasi & Dokumen BRA Digital
                  </p>
                </div>
              </div>

              <div className="pt-3 border-t border-white/10 text-xs leading-relaxed opacity-85">
                <p className="font-medium">
                  Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari — Badan Nasional Pencarian dan Pertolongan (BASARNAS).
                </p>
              </div>
            </div>

            {/* Highlights */}
            <div className="mt-8 space-y-3 text-xs">
              <div
                className={`p-3 rounded-xl border flex items-center gap-3 ${
                  isDarkMode
                    ? 'bg-slate-850/60 border-slate-800'
                    : 'bg-white/15 border-white/20'
                }`}
              >
                <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0" />
                <span>
                  <strong>Hak Akses Penuh:</strong> Akun Administrator mengelola inisiasi operasi, Renops harian, Sitrep perkembangan, penutupan, hingga penerbitan Berita Radio (BRA SAR).
                </span>
              </div>

              <div
                className={`p-3 rounded-xl border flex items-center gap-3 ${
                  isDarkMode
                    ? 'bg-slate-850/60 border-slate-800'
                    : 'bg-white/15 border-white/20'
                }`}
              >
                <Lock className="w-4 h-4 text-amber-400 shrink-0" />
                <span>
                  <strong>Keamanan & Otorisasi:</strong> Setiap dokumen dilengkapi barcode QR verifikasi resmi berkepala KPP Kelas A Kendari.
                </span>
              </div>
            </div>
          </div>

          {/* Slogan */}
          <div className="pt-6 mt-6 border-t border-white/10 text-[11px] font-mono italic opacity-75">
            "Avignam Jagat Samagram" — Semoga Selamatlah Alam Semesta
          </div>
        </div>

        {/* Right Side: Single Admin Login Form */}
        <div
          className={`lg:col-span-7 rounded-3xl p-6 sm:p-8 shadow-2xl border flex flex-col justify-between ${
            isDarkMode
              ? 'bg-slate-900/90 border-slate-800'
              : 'bg-white border-slate-200'
          }`}
        >
          <div>
            <div className="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
              <div>
                <h2 className="text-lg font-bold">Login Administrator</h2>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  Masuk ke pusat kendali sistem dengan akun Administrator SAR
                </p>
              </div>
              <span className={`p-2.5 rounded-xl border ${accentColors.badgeBg} ${accentColors.badgeText} border-current/20`}>
                <ShieldCheck className="w-5 h-5" />
              </span>
            </div>

            {/* Single Admin Account Badge & Auto-fill */}
            <div className="mb-5">
              <div
                className={`p-3.5 rounded-2xl border flex items-center justify-between gap-3 transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800/60 border-slate-700/80 text-slate-200'
                    : 'bg-orange-50/80 border-orange-200 text-slate-800'
                }`}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center font-bold text-sm shadow-md shrink-0">
                    <User className="w-5 h-5" />
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-xs">Administrator SAR</span>
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                        Admin Tunggal
                      </span>
                    </div>
                    <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                      Username: <code className="font-bold font-mono text-orange-600 dark:text-orange-400">admin</code> • Sandi: <code className="font-bold font-mono text-orange-600 dark:text-orange-400">admin</code>
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={handleAutoFill}
                  className={`px-3 py-1.5 text-xs font-bold rounded-xl border shrink-0 transition-all active:scale-95 ${
                    isDarkMode
                      ? 'bg-slate-700 hover:bg-slate-600 text-white border-slate-600'
                      : 'bg-white hover:bg-slate-100 text-slate-800 border-slate-300 shadow-sm'
                  }`}
                  title="Klik untuk mengisi username dan password admin secara otomatis"
                >
                  Isi Otomatis
                </button>
              </div>
            </div>

            {/* Login Form */}
            <form onSubmit={handleLogin} className="space-y-4">
              {/* Username Input */}
              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                  Username Akun Admin
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
                    className={`w-full pl-10 pr-3 py-2.5 rounded-xl text-xs font-mono font-medium border transition-colors outline-none focus:ring-2 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500 focus:ring-orange-500/20'
                        : 'bg-white border-slate-300 text-slate-900 focus:border-orange-500 focus:ring-orange-500/20'
                    }`}
                  />
                </div>
              </div>

              {/* Password Input */}
              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                  Kata Sandi (Password)
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <KeyRound className="w-4 h-4" />
                  </div>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="admin"
                    className={`w-full pl-10 pr-10 py-2.5 rounded-xl text-xs font-mono font-medium border transition-colors outline-none focus:ring-2 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500 focus:ring-orange-500/20'
                        : 'bg-white border-slate-300 text-slate-900 focus:border-orange-500 focus:ring-orange-500/20'
                    }`}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                  >
                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>

              {/* Remember me & Helper info */}
              <div className="flex items-center justify-between text-xs pt-1">
                <label className="flex items-center gap-2 cursor-pointer select-none text-slate-600 dark:text-slate-400">
                  <input
                    type="checkbox"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    className="rounded border-slate-700 text-orange-600 focus:ring-orange-500"
                  />
                  <span>Ingat sesi admin di perangkat ini</span>
                </label>
                <span className="text-[11px] text-amber-600 dark:text-amber-400 font-medium">
                  Sandi: <strong>admin</strong> / <strong>basarnas115</strong>
                </span>
              </div>

              {/* Submit Buttons */}
              <div className="space-y-2 pt-2">
                <button
                  type="submit"
                  disabled={loading}
                  className={`w-full py-3 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 shadow-lg shadow-orange-600/25 flex items-center justify-center gap-2 transition-all disabled:opacity-50`}
                >
                  {loading ? (
                    <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  ) : (
                    <>
                      <span>Masuk sebagai Admin SAR</span>
                      <ArrowRight className="w-4 h-4" />
                    </>
                  )}
                </button>

                {/* Instant 1-Click Fast Login for Operations */}
                <button
                  type="button"
                  disabled={loading}
                  onClick={handleQuickLoginDirect}
                  className={`w-full py-2.5 px-3 rounded-xl text-xs font-semibold border flex items-center justify-center gap-1.5 transition-colors ${
                    isDarkMode
                      ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                      : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-300'
                  }`}
                >
                  <Zap className="w-3.5 h-3.5 text-amber-500" />
                  <span>1-Klik Masuk Langsung (Bypass Cepat)</span>
                </button>
              </div>
            </form>
          </div>

          {/* Quick Notice */}
          <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
            <Info className="w-4 h-4 text-orange-500 shrink-0" />
            <span>
              Sistem beroperasi di lingkungan KPP Kelas A Kendari. Akun Administrator memiliki kendali penuh terhadap seluruh arsip operasi.
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};
