import fs from 'fs';
import path from 'path';
import { DatabaseSchema, MasterAlut, MasterTim, Operasi, Korban, DetailUnsur, DetailAlut, DetailTimBerangkat, Renops, RenopsSRU, LaporanHarian, BraSar, Pejabat, AppSettings } from '../src/types';
import { generateLaporanAwalWA, generateRenopsWA, generateLapbangWA } from '../src/utils/templates';

const DATA_DIR = path.join(process.cwd(), 'data');
const DB_FILE = path.join(DATA_DIR, 'db.json');

const INITIAL_PEJABAT: Pejabat[] = [
  {
    id: 'pej-1',
    nama: 'AMIRUDDIN AS, S.Sos.',
    nip: '197405101998031002',
    pangkat: 'PEMBINA (IV/a)',
    jabatan: 'Kepala Kantor Pencarian dan Pertolongan Kelas A Kendari',
    is_default_smc: false,
  },
  {
    id: 'pej-2',
    nama: 'H. AMINUDDIN P.S., S.Sos., M.M.',
    nip: '197804122002121001',
    pangkat: 'PENATA TK. I (III/d)',
    jabatan: 'Kepala Seksi Operasi dan Siaga',
    is_default_smc: true,
  },
  {
    id: 'pej-3',
    nama: 'RUDI, S.E.',
    nip: '198207152006041003',
    pangkat: 'PENATA (III/c)',
    jabatan: 'Koordinator Pos SAR Wakatobi',
    is_default_smc: false,
  },
  {
    id: 'pej-4',
    nama: 'SUPRIADI, S.Sos.',
    nip: '198009202005021002',
    pangkat: 'PENATA (III/c)',
    jabatan: 'Koordinator Pos SAR Kolaka',
    is_default_smc: false,
  },
  {
    id: 'pej-5',
    nama: 'UMAR',
    nip: '198503142008121001',
    pangkat: 'PENGATUR TK. I (II/d)',
    jabatan: 'Koordinator Unit Siaga SAR Muna',
    is_default_smc: false,
  },
];

const INITIAL_MASTER_ALUT: MasterAlut[] = [
  { id_alut: 1, nama_alut: 'Rescue Car', satuan: 'Unit' },
  { id_alut: 2, nama_alut: 'Rescue Carrier', satuan: 'Unit' },
  { id_alut: 3, nama_alut: 'RB 210', satuan: 'Unit' },
  { id_alut: 4, nama_alut: 'RB 307', satuan: 'Unit' },
  { id_alut: 5, nama_alut: 'RIB', satuan: 'Unit' },
  { id_alut: 6, nama_alut: 'Rubber Boat', satuan: 'Unit' },
  { id_alut: 7, nama_alut: 'Longboat Masyarakat', satuan: 'Unit' },
  { id_alut: 8, nama_alut: 'Aqua Eye', satuan: 'Set' },
  { id_alut: 9, nama_alut: 'Drone Thermal', satuan: 'Unit' },
  { id_alut: 10, nama_alut: 'Drone Underwater', satuan: 'Unit' },
  { id_alut: 11, nama_alut: 'Palsar Medis', satuan: 'Set' },
  { id_alut: 12, nama_alut: 'Palsar Evakuasi', satuan: 'Set' },
  { id_alut: 13, nama_alut: 'Palsar Komunikasi', satuan: 'Set' },
  { id_alut: 14, nama_alut: 'Palsar Selam', satuan: 'Set' },
  { id_alut: 15, nama_alut: 'Palsar Mounteneering', satuan: 'Set' },
  { id_alut: 16, nama_alut: 'Kantong Jenazah', satuan: 'Lembar' },
];

const INITIAL_MASTER_TIM: MasterTim[] = [
  { id_tim: 1, nama_tim: 'Tim Rescue Kansar Kendari' },
  { id_tim: 2, nama_tim: 'Pos SAR Kolaka' },
  { id_tim: 3, nama_tim: 'Pos SAR Bau Bau' },
  { id_tim: 4, nama_tim: 'Pos SAR Wakatobi' },
  { id_tim: 5, nama_tim: 'Pos SAR Konawe Utara' },
  { id_tim: 6, nama_tim: 'Unit Siaga SAR Muna' },
  { id_tim: 7, nama_tim: 'ABK KN SAR Pacitan' },
  { id_tim: 8, nama_tim: 'ABK RB 210' },
  { id_tim: 9, nama_tim: 'ABK RB 307' },
];

function createSeedData(): DatabaseSchema {
  // Current time in WITA
  const now = new Date();
  const yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);

  const waktuKejadian = new Date(yesterday.getTime() - 2 * 60 * 60 * 1000).toISOString();
  const waktuLapor = new Date(yesterday.getTime() - 1 * 60 * 60 * 1000).toISOString();
  const waktuBerangkat = yesterday.toISOString();
  const etaTiba = new Date(yesterday.getTime() + 1.5 * 60 * 60 * 1000).toISOString();

  const seedOperasi: Operasi = {
    id_operasi: 1,
    nama_operasi: 'KMM 1 Orang Nelayan Terjatuh dari Longboat di Perairan Wakatobi',
    nama_pelapor: 'Bpk. La Ode Sudirman',
    hubungan_pelapor: 'Kepala Desa Mola Selatan',
    no_telp_pelapor: '0812-4589-9821',
    waktu_kejadian: waktuKejadian,
    waktu_lapor: waktuLapor,
    waktu_berangkat: waktuBerangkat,
    tim_berangkat: 'Pos SAR Wakatobi, Tim Rescue Kansar Kendari',
    eta_tiba: etaTiba,
    lokasi_kejadian: 'Perairan Karang Kapota, Kabupaten Wakatobi, Sulawesi Tenggara',
    koordinat: '05° 18\' 24" S - 123° 34\' 10" E',
    jarak: '12 Nm',
    eta_lokasi: '1 Jam 15 Menit',
    kronologi: 'Pada hari kemarin sekitar pukul 05.30 WITA korban berangkat melaut seorang diri mencari ikan menggunakan longboat. Sekitar pukul 07.00 WITA seorang nelayan lain menemukan longboat korban dalam keadaan mesin masih menyala berputar tanpa pengemudi. Pencarian telah dilakukan oleh nelayan setempat namun hingga laporan ini dibuat korban belum ditemukan.',
    jumlah_pob: 1,
    unsur_terlibat: 'Pos SAR Wakatobi (5 Org), Polairud Wakatobi (3 Org), Pos TNI AL Wakatobi (2 Org), BPBD Wakatobi (3 Org), Nelayan Setempat (15 Org)',
    alut_palsar: '1 Unit RIB Pos SAR Wakatobi, 1 Unit Rubber Boat, 4 Unit Longboat Nelayan, 1 Set Palsar Selam, 1 Set Aqua Eye',
    faktor_penghambat: 'Arus bawah laut cukup deras',
    cuaca: 'Cerah Berawan',
    gelombang: '0.75 - 1.5 M',
    angin: 'Timur - Tenggara, 8 - 15 Knots',
    status: 'OPEN',
    waktu_selesai: null,
    full_text_wa: '',
    created_at: waktuLapor,
  };

  const seedKorban: Korban[] = [
    {
      id_korban: 1,
      id_operasi: 1,
      nama_korban: 'La Sarifuddin',
      umur: '42 Tahun',
      jk: 'Laki-laki',
      alamat: 'Desa Mola Selatan, Kec. Wangi-Wangi Selatan, Kab. Wakatobi',
      status_korban: 'DP',
    },
  ];

  const seedUnsur: DetailUnsur[] = [
    { id_detail_unsur: 1, id_operasi: 1, nama_unsur: 'Pos SAR Wakatobi', jumlah_personil: 5 },
    { id_detail_unsur: 2, id_operasi: 1, nama_unsur: 'Polairud Wakatobi', jumlah_personil: 3 },
    { id_detail_unsur: 3, id_operasi: 1, nama_unsur: 'Pos TNI AL Wakatobi', jumlah_personil: 2 },
    { id_detail_unsur: 4, id_operasi: 1, nama_unsur: 'BPBD Wakatobi', jumlah_personil: 3 },
    { id_detail_unsur: 5, id_operasi: 1, nama_unsur: 'Masyarakat & Nelayan Setempat', jumlah_personil: 15 },
  ];

  const seedAlut: DetailAlut[] = [
    { id_detail_alut: 1, id_operasi: 1, id_alut: 5, nama_alut: 'RIB', satuan: 'Unit', jumlah_alut: 1 },
    { id_detail_alut: 2, id_operasi: 1, id_alut: 6, nama_alut: 'Rubber Boat', satuan: 'Unit', jumlah_alut: 1 },
    { id_detail_alut: 3, id_operasi: 1, id_alut: 7, nama_alut: 'Longboat Masyarakat', satuan: 'Unit', jumlah_alut: 4 },
    { id_detail_alut: 4, id_operasi: 1, id_alut: 8, nama_alut: 'Aqua Eye', satuan: 'Set', jumlah_alut: 1 },
    { id_detail_alut: 5, id_operasi: 1, id_alut: 14, nama_alut: 'Palsar Selam', satuan: 'Set', jumlah_alut: 2 },
  ];

  const seedTimBerangkat: DetailTimBerangkat[] = [
    { id_detail_tim: 1, id_operasi: 1, id_tim: 4, nama_tim: 'Pos SAR Wakatobi' },
  ];

  seedOperasi.full_text_wa = generateLaporanAwalWA(seedOperasi, seedKorban, seedUnsur, seedAlut);

  const renops1: Renops = {
    id_renops: 1,
    id_operasi: 1,
    hari_ke: 'H.1',
    tanggal_laporan: yesterday.toISOString().split('T')[0],
    waktu_briefing: new Date(yesterday.getTime() + 1.5 * 3600000).toISOString(),
    waktu_mulai_ops: new Date(yesterday.getTime() + 2 * 3600000).toISOString(),
    cuaca: 'Cerah Berawan',
    unsur_text: '',
    alut_text: '',
    full_text_wa: '',
    created_at: yesterday.toISOString(),
  };

  const sru1: RenopsSRU = {
    id_sru: 1,
    id_renops: 1,
    nama_sru: 'SRU 1 (RIB Pos SAR Wakatobi)',
    koordinat_area: 'A: 05° 17\' 00" S - 123° 33\' 00" E\nB: 05° 17\' 00" S - 123° 36\' 00" E\nC: 05° 20\' 00" S - 123° 36\' 00" E\nD: 05° 20\' 00" S - 123° 33\' 00" E',
    luas_area: '9 Nm²',
    pola_pencarian: 'Parallel Track Search',
  };

  const sru2: RenopsSRU = {
    id_sru: 2,
    id_renops: 1,
    nama_sru: 'SRU 2 (Longboat Masyarakat)',
    koordinat_area: 'Penyisiran di sekitar pesisir Karang Kapota dan pulau-pulau kecil terdekat',
    luas_area: '6 Nm²',
    pola_pencarian: 'Creeping Line Search',
  };

  renops1.full_text_wa = generateRenopsWA(seedOperasi, renops1, [sru1, sru2], seedKorban, seedUnsur, seedAlut);

  const lapbang1: LaporanHarian = {
    id_laporan: 1,
    id_operasi: 1,
    hari_ke: 'H.1',
    jenis_laporan: 'PERKEMBANGAN',
    isi_laporan: 'Tim SAR Gabungan telah melaksanakan penyisiran sesuai Renops H.1 seluas 15 Nm² di sekitar LKP dan Karang Kapota. Tim juga melakukan penyelaman di 2 titik koordinat dicurigai menggunakan Aqua Eye. Hingga pukul 17.30 WITA operasi dihentikan sementara dengan hasil NIHIL dan akan dilanjutkan esok hari.',
    full_text_wa: '',
    waktu_laporan: new Date(yesterday.getTime() + 10 * 3600000).toISOString(),
  };

  lapbang1.full_text_wa = generateLapbangWA(seedOperasi, lapbang1, seedKorban, seedUnsur, seedAlut, false);

  return {
    master_alut: INITIAL_MASTER_ALUT,
    master_tim: INITIAL_MASTER_TIM,
    operasi: [seedOperasi],
    korban: seedKorban,
    detail_tim_berangkat: seedTimBerangkat,
    detail_unsur: seedUnsur,
    detail_alut: seedAlut,
    renops: [renops1],
    renops_sru: [sru1, sru2],
    laporan_harian: [lapbang1],
    bra_sar: [],
  };
}

class Database {
  private data: DatabaseSchema;

  constructor() {
    this.ensureDataDir();
    this.data = this.loadData();
  }

  private ensureDataDir() {
    if (!fs.existsSync(DATA_DIR)) {
      fs.mkdirSync(DATA_DIR, { recursive: true });
    }
  }

  private loadData(): DatabaseSchema {
    if (fs.existsSync(DB_FILE)) {
      try {
        const raw = fs.readFileSync(DB_FILE, 'utf-8');
        const parsed: DatabaseSchema = JSON.parse(raw);
        if (!parsed.bra_sar) {
          parsed.bra_sar = [];
        }
        return parsed;
      } catch (err) {
        console.error('Failed to parse db.json, generating seed data', err);
      }
    }

    const seed = createSeedData();
    this.saveData(seed);
    return seed;
  }

  public saveData(dataToSave?: DatabaseSchema) {
    if (dataToSave) {
      this.data = dataToSave;
    }
    fs.writeFileSync(DB_FILE, JSON.stringify(this.data, null, 2), 'utf-8');
  }

  // Master Alut
  public getMasterAlut(): MasterAlut[] {
    return this.data.master_alut;
  }

  public addMasterAlut(nama_alut: string, satuan: string = 'Unit'): MasterAlut {
    const nextId = this.data.master_alut.reduce((max, item) => Math.max(max, item.id_alut), 0) + 1;
    const newItem: MasterAlut = { id_alut: nextId, nama_alut, satuan };
    this.data.master_alut.push(newItem);
    this.saveData();
    return newItem;
  }

  public updateMasterAlut(id_alut: number, nama_alut: string, satuan: string = 'Unit'): MasterAlut | null {
    const item = this.data.master_alut.find(a => a.id_alut === id_alut);
    if (!item) return null;
    item.nama_alut = nama_alut;
    item.satuan = satuan;
    this.saveData();
    return item;
  }

  public deleteMasterAlut(id_alut: number): boolean {
    const initialLen = this.data.master_alut.length;
    this.data.master_alut = this.data.master_alut.filter(a => a.id_alut !== id_alut);
    if (this.data.master_alut.length === initialLen) return false;
    this.saveData();
    return true;
  }

  public reorderMasterAlut(orderedIds: number[]): MasterAlut[] {
    const map = new Map(this.data.master_alut.map(item => [item.id_alut, item]));
    const reordered: MasterAlut[] = [];
    for (const id of orderedIds) {
      const item = map.get(id);
      if (item) {
        reordered.push(item);
        map.delete(id);
      }
    }
    for (const item of map.values()) {
      reordered.push(item);
    }
    this.data.master_alut = reordered;
    this.saveData();
    return this.data.master_alut;
  }

  // Master Tim
  public getMasterTim(): MasterTim[] {
    return this.data.master_tim;
  }

  public addMasterTim(nama_tim: string): MasterTim {
    const nextId = this.data.master_tim.reduce((max, item) => Math.max(max, item.id_tim), 0) + 1;
    const newItem: MasterTim = { id_tim: nextId, nama_tim };
    this.data.master_tim.push(newItem);
    this.saveData();
    return newItem;
  }

  public updateMasterTim(id_tim: number, nama_tim: string): MasterTim | null {
    const item = this.data.master_tim.find(t => t.id_tim === id_tim);
    if (!item) return null;
    item.nama_tim = nama_tim;
    this.saveData();
    return item;
  }

  public deleteMasterTim(id_tim: number): boolean {
    const initialLen = this.data.master_tim.length;
    this.data.master_tim = this.data.master_tim.filter(t => t.id_tim !== id_tim);
    if (this.data.master_tim.length === initialLen) return false;
    this.saveData();
    return true;
  }

  public reorderMasterTim(orderedIds: number[]): MasterTim[] {
    const map = new Map(this.data.master_tim.map(item => [item.id_tim, item]));
    const reordered: MasterTim[] = [];
    for (const id of orderedIds) {
      const item = map.get(id);
      if (item) {
        reordered.push(item);
        map.delete(id);
      }
    }
    for (const item of map.values()) {
      reordered.push(item);
    }
    this.data.master_tim = reordered;
    this.saveData();
    return this.data.master_tim;
  }

  public getSettings(): AppSettings {
    return this.getAppSettings();
  }

  public updateSettings(settings: Partial<AppSettings>): AppSettings {
    return this.updateAppSettings(settings);
  }

  // App Settings & Pejabat
  public getAppSettings(): AppSettings {
    if (!this.data.app_settings) {
      this.data.app_settings = {
        satker_kode: 'SAR-304',
        satker_nama: 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI',
        pejabat_list: INITIAL_PEJABAT,
        admin_username: 'admin',
        admin_password: 'Kendari2026',
        admin_email: 'irfandydk@gmail.com',
      };
      this.saveData();
    } else {
      let shouldSave = false;
      if (!this.data.app_settings.pejabat_list || this.data.app_settings.pejabat_list.length === 0) {
        this.data.app_settings.pejabat_list = INITIAL_PEJABAT;
        shouldSave = true;
      }
      if (!this.data.app_settings.admin_username) {
        this.data.app_settings.admin_username = 'admin';
        shouldSave = true;
      }
      if (!this.data.app_settings.admin_password) {
        this.data.app_settings.admin_password = 'Kendari2026';
        shouldSave = true;
      }
      if (!this.data.app_settings.admin_email) {
        this.data.app_settings.admin_email = 'irfandydk@gmail.com';
        shouldSave = true;
      }
      if (shouldSave) {
        this.saveData();
      }
    }
    return this.data.app_settings;
  }

  public updateAppSettings(settings: Partial<AppSettings>): AppSettings {
    const current = this.getAppSettings();
    this.data.app_settings = {
      ...current,
      ...settings,
    };
    this.saveData();
    return this.data.app_settings;
  }

  // --- Authentication & Security Methods ---
  public getAdminPassword(): string {
    return this.getAppSettings().admin_password || 'Kendari2026';
  }

  public getAdminEmail(): string {
    return this.getAppSettings().admin_email || 'irfandydk@gmail.com';
  }

  public validateAdminLogin(username: string, password: string): boolean {
    const cleanUser = (username || '').trim().toLowerCase();
    const cleanPass = (password || '').trim();
    const activePass = this.getAdminPassword();
    return cleanUser === 'admin' && cleanPass === activePass;
  }

  public changeAdminPassword(oldPass: string, newPass: string): { success: boolean; error?: string } {
    const cleanOld = (oldPass || '').trim();
    const cleanNew = (newPass || '').trim();
    const currentPass = this.getAdminPassword();

    if (cleanOld !== currentPass) {
      return { success: false, error: 'Kata sandi lama yang Anda masukkan tidak sesuai.' };
    }
    if (!cleanNew || cleanNew.length < 4) {
      return { success: false, error: 'Kata sandi baru minimal harus 4 karakter.' };
    }

    this.updateAppSettings({ admin_password: cleanNew });
    return { success: true };
  }

  public generateResetToken(originHost: string = ''): { token: string; email: string; resetLink: string; expiresAt: string } {
    const token = 'SAR-' + Math.random().toString(36).substring(2, 8).toUpperCase() + '-' + Date.now().toString(36).toUpperCase();
    const expiresAt = new Date(Date.now() + 60 * 60 * 1000).toISOString(); // 1 hour expiry
    const email = this.getAdminEmail();

    this.updateAppSettings({
      reset_token: token,
      reset_token_expiry: expiresAt,
    });

    const resetLink = originHost ? `${originHost}/?resetToken=${token}` : `/?resetToken=${token}`;

    return {
      token,
      email,
      resetLink,
      expiresAt,
    };
  }

  public resetPasswordWithToken(token: string, newPassword: string): { success: boolean; error?: string } {
    const settings = this.getAppSettings();
    const cleanToken = (token || '').trim();
    const cleanPass = (newPassword || '').trim();

    if (!cleanToken || cleanToken !== settings.reset_token) {
      return { success: false, error: 'Token reset kata sandi tidak valid atau telah kadaluarsa.' };
    }

    if (settings.reset_token_expiry && new Date(settings.reset_token_expiry).getTime() < Date.now()) {
      return { success: false, error: 'Token reset kata sandi sudah kadaluarsa. Silakan ajukan permohonan baru.' };
    }

    if (!cleanPass || cleanPass.length < 4) {
      return { success: false, error: 'Kata sandi baru minimal harus 4 karakter.' };
    }

    this.updateAppSettings({
      admin_password: cleanPass,
      reset_token: undefined,
      reset_token_expiry: undefined,
    });

    return { success: true };
  }

  public getPejabatList(): Pejabat[] {
    return this.getAppSettings().pejabat_list || [];
  }

  public addPejabat(pejabat: Omit<Pejabat, 'id'>): Pejabat {
    const list = this.getPejabatList();
    const newPejabat: Pejabat = {
      ...pejabat,
      id: `pej-${Date.now()}-${Math.random().toString(36).substr(2, 4)}`,
    };
    list.push(newPejabat);
    this.updateAppSettings({ pejabat_list: list });
    return newPejabat;
  }

  public updatePejabat(id: string, updateData: Partial<Pejabat>): Pejabat | null {
    const list = this.getPejabatList();
    const idx = list.findIndex(p => p.id === id);
    if (idx === -1) return null;
    list[idx] = { ...list[idx], ...updateData };
    this.updateAppSettings({ pejabat_list: list });
    return list[idx];
  }

  public deletePejabat(id: string): boolean {
    const list = this.getPejabatList();
    const initialLen = list.length;
    const filtered = list.filter(p => p.id !== id);
    if (filtered.length === initialLen) return false;
    this.updateAppSettings({ pejabat_list: filtered });
    return true;
  }

  // Operasi
  public getAllOperasi(): Operasi[] {
    return this.data.operasi.map(op => this.populateOperasi(op));
  }

  public getOperasiById(id_operasi: number): Operasi | null {
    const op = this.data.operasi.find(o => o.id_operasi === id_operasi);
    if (!op) return null;
    return this.populateOperasi(op);
  }

  public populateOperasi(op: Operasi): Operasi {
    const korban = this.data.korban.filter(k => k.id_operasi === op.id_operasi);
    const detail_unsur = this.data.detail_unsur.filter(u => u.id_operasi === op.id_operasi);
    const detail_alut = this.data.detail_alut.filter(a => a.id_operasi === op.id_operasi);
    const detail_tim_berangkat = this.data.detail_tim_berangkat.filter(t => t.id_operasi === op.id_operasi);
    const renops = this.data.renops.filter(r => r.id_operasi === op.id_operasi).map(r => ({
      ...r,
      srus: this.data.renops_sru.filter(s => s.id_renops === r.id_renops),
    }));
    const laporan_harian = this.data.laporan_harian.filter(l => l.id_operasi === op.id_operasi);

    return {
      ...op,
      korban,
      detail_unsur,
      detail_alut,
      detail_tim_berangkat,
      renops,
      laporan_harian,
    };
  }

  public createOperasi(
    opData: Omit<Operasi, 'id_operasi' | 'created_at'>,
    korbanList: Omit<Korban, 'id_korban' | 'id_operasi'>[],
    unsurList: Omit<DetailUnsur, 'id_detail_unsur' | 'id_operasi'>[],
    alutList: Omit<DetailAlut, 'id_detail_alut' | 'id_operasi'>[],
    timBerangkatIds: number[]
  ): Operasi {
    const nextOpId = this.data.operasi.reduce((max, o) => Math.max(max, o.id_operasi), 0) + 1;
    const nowISO = new Date().toISOString();

    const newKorbanList: Korban[] = korbanList.map((k, idx) => ({
      ...k,
      id_korban: this.data.korban.reduce((max, item) => Math.max(max, item.id_korban), 0) + idx + 1,
      id_operasi: nextOpId,
    }));

    const newUnsurList: DetailUnsur[] = unsurList.map((u, idx) => ({
      ...u,
      id_detail_unsur: this.data.detail_unsur.reduce((max, item) => Math.max(max, item.id_detail_unsur || 0), 0) + idx + 1,
      id_operasi: nextOpId,
    }));

    const newAlutList: DetailAlut[] = alutList.map((a, idx) => ({
      ...a,
      id_detail_alut: this.data.detail_alut.reduce((max, item) => Math.max(max, item.id_detail_alut || 0), 0) + idx + 1,
      id_operasi: nextOpId,
    }));

    const newTimList: DetailTimBerangkat[] = timBerangkatIds.map((id_tim, idx) => {
      const mt = this.data.master_tim.find(t => t.id_tim === id_tim);
      return {
        id_detail_tim: this.data.detail_tim_berangkat.reduce((max, item) => Math.max(max, item.id_detail_tim), 0) + idx + 1,
        id_operasi: nextOpId,
        id_tim,
        nama_tim: mt?.nama_tim,
      };
    });

    const newOp: Operasi = {
      ...opData,
      id_operasi: nextOpId,
      jumlah_pob: newKorbanList.length,
      status: 'OPEN',
      created_at: nowISO,
      full_text_wa: opData.full_text_wa || generateLaporanAwalWA(
        opData,
        newKorbanList,
        newUnsurList,
        newAlutList
      ),
    };

    this.data.operasi.unshift(newOp);
    this.data.korban.push(...newKorbanList);
    this.data.detail_unsur.push(...newUnsurList);
    this.data.detail_alut.push(...newAlutList);
    this.data.detail_tim_berangkat.push(...newTimList);

    this.saveData();
    return this.populateOperasi(newOp);
  }

  public updateOperasi(
    id_operasi: number,
    opData: Partial<Operasi>,
    korbanList?: Omit<Korban, 'id_korban' | 'id_operasi'>[],
    unsurList?: Omit<DetailUnsur, 'id_detail_unsur' | 'id_operasi'>[],
    alutList?: Omit<DetailAlut, 'id_detail_alut' | 'id_operasi'>[],
    timBerangkatIds?: number[]
  ): Operasi | null {
    const opIndex = this.data.operasi.findIndex(o => o.id_operasi === id_operasi);
    if (opIndex === -1) return null;

    if (korbanList) {
      this.data.korban = this.data.korban.filter(k => k.id_operasi !== id_operasi);
      const newKorban: Korban[] = korbanList.map((k, idx) => ({
        ...k,
        id_korban: this.data.korban.reduce((max, item) => Math.max(max, item.id_korban), 0) + idx + 1,
        id_operasi,
      }));
      this.data.korban.push(...newKorban);
      opData.jumlah_pob = newKorban.length;
    }

    if (unsurList) {
      this.data.detail_unsur = this.data.detail_unsur.filter(u => u.id_operasi !== id_operasi);
      const newUnsur: DetailUnsur[] = unsurList.map((u, idx) => ({
        ...u,
        id_detail_unsur: this.data.detail_unsur.reduce((max, item) => Math.max(max, item.id_detail_unsur || 0), 0) + idx + 1,
        id_operasi,
      }));
      this.data.detail_unsur.push(...newUnsur);
    }

    if (alutList) {
      this.data.detail_alut = this.data.detail_alut.filter(a => a.id_operasi !== id_operasi);
      const newAlut: DetailAlut[] = alutList.map((a, idx) => ({
        ...a,
        id_detail_alut: this.data.detail_alut.reduce((max, item) => Math.max(max, item.id_detail_alut || 0), 0) + idx + 1,
        id_operasi,
      }));
      this.data.detail_alut.push(...newAlut);
    }

    if (timBerangkatIds) {
      this.data.detail_tim_berangkat = this.data.detail_tim_berangkat.filter(t => t.id_operasi !== id_operasi);
      const newTims: DetailTimBerangkat[] = timBerangkatIds.map((id_tim, idx) => {
        const mt = this.data.master_tim.find(t => t.id_tim === id_tim);
        return {
          id_detail_tim: this.data.detail_tim_berangkat.reduce((max, item) => Math.max(max, item.id_detail_tim), 0) + idx + 1,
          id_operasi,
          id_tim,
          nama_tim: mt?.nama_tim,
        };
      });
      this.data.detail_tim_berangkat.push(...newTims);
    }

    this.data.operasi[opIndex] = {
      ...this.data.operasi[opIndex],
      ...opData,
    };

    // If full_text_wa not explicitly updated, regenerate
    if (!opData.full_text_wa) {
      const currentOp = this.populateOperasi(this.data.operasi[opIndex]);
      this.data.operasi[opIndex].full_text_wa = generateLaporanAwalWA(
        this.data.operasi[opIndex],
        currentOp.korban,
        currentOp.detail_unsur,
        currentOp.detail_alut
      );
    }

    this.saveData();
    return this.populateOperasi(this.data.operasi[opIndex]);
  }

  public deleteOperasi(id_operasi: number): boolean {
    const initialLen = this.data.operasi.length;
    this.data.operasi = this.data.operasi.filter(o => o.id_operasi !== id_operasi);
    if (this.data.operasi.length === initialLen) return false;

    this.data.korban = this.data.korban.filter(k => k.id_operasi !== id_operasi);
    this.data.detail_unsur = this.data.detail_unsur.filter(u => u.id_operasi !== id_operasi);
    this.data.detail_alut = this.data.detail_alut.filter(a => a.id_operasi !== id_operasi);
    this.data.detail_tim_berangkat = this.data.detail_tim_berangkat.filter(t => t.id_operasi !== id_operasi);

    const renopsIds = this.data.renops.filter(r => r.id_operasi === id_operasi).map(r => r.id_renops);
    this.data.renops = this.data.renops.filter(r => r.id_operasi !== id_operasi);
    this.data.renops_sru = this.data.renops_sru.filter(s => !renopsIds.includes(s.id_renops));
    this.data.laporan_harian = this.data.laporan_harian.filter(l => l.id_operasi !== id_operasi);

    this.saveData();
    return true;
  }

  // Renops
  public createRenops(
    id_operasi: number,
    renopsData: Omit<Renops, 'id_renops' | 'id_operasi' | 'created_at'>,
    sruList: Omit<RenopsSRU, 'id_sru' | 'id_renops'>[],
    updatedUnsur?: DetailUnsur[],
    updatedAlut?: DetailAlut[]
  ): Renops {
    const nextRenopsId = this.data.renops.reduce((max, r) => Math.max(max, r.id_renops), 0) + 1;
    const nowISO = new Date().toISOString();

    const newSrus: RenopsSRU[] = sruList.map((s, idx) => ({
      ...s,
      id_sru: this.data.renops_sru.reduce((max, item) => Math.max(max, item.id_sru), 0) + idx + 1,
      id_renops: nextRenopsId,
    }));

    // If updated unsur/alut provided, update state in detail_unsur & detail_alut so next day inherits it!
    if (updatedUnsur && updatedUnsur.length > 0) {
      this.data.detail_unsur = this.data.detail_unsur.filter(u => u.id_operasi !== id_operasi);
      updatedUnsur.forEach((u, idx) => {
        this.data.detail_unsur.push({
          id_detail_unsur: this.data.detail_unsur.reduce((max, item) => Math.max(max, item.id_detail_unsur || 0), 0) + idx + 1,
          id_operasi,
          nama_unsur: u.nama_unsur,
          jumlah_personil: Number(u.jumlah_personil) || 1,
        });
      });
    }

    if (updatedAlut && updatedAlut.length > 0) {
      this.data.detail_alut = this.data.detail_alut.filter(a => a.id_operasi !== id_operasi);
      updatedAlut.forEach((a, idx) => {
        this.data.detail_alut.push({
          id_detail_alut: this.data.detail_alut.reduce((max, item) => Math.max(max, item.id_detail_alut || 0), 0) + idx + 1,
          id_operasi,
          id_alut: a.id_alut,
          nama_alut: a.nama_alut,
          satuan: a.satuan || 'Unit',
          jumlah_alut: Number(a.jumlah_alut) || 1,
        });
      });
    }

    const op = this.populateOperasi(this.data.operasi.find(o => o.id_operasi === id_operasi)!);

    const full_text_wa = renopsData.full_text_wa || generateRenopsWA(
      op,
      renopsData,
      newSrus,
      op.korban,
      op.detail_unsur,
      op.detail_alut
    );

    const newRenops: Renops = {
      ...renopsData,
      id_renops: nextRenopsId,
      id_operasi,
      full_text_wa,
      created_at: nowISO,
    };

    this.data.renops.unshift(newRenops);
    this.data.renops_sru.push(...newSrus);
    this.saveData();

    return {
      ...newRenops,
      srus: newSrus,
    };
  }

  public deleteRenops(id_renops: number): boolean {
    const initialLen = this.data.renops.length;
    this.data.renops = this.data.renops.filter(r => r.id_renops !== id_renops);
    if (this.data.renops.length === initialLen) return false;

    this.data.renops_sru = this.data.renops_sru.filter(s => s.id_renops !== id_renops);
    this.saveData();
    return true;
  }

  // Laporan Harian (Lapbang & Penutupan)
  public createLaporanHarian(
    id_operasi: number,
    laporanData: Omit<LaporanHarian, 'id_laporan' | 'id_operasi'>,
    updatedKorban?: { id_korban: number; status_korban: Korban['status_korban'] }[],
    updatedUnsur?: DetailUnsur[],
    updatedAlut?: DetailAlut[]
  ): LaporanHarian {
    const nextLaporanId = this.data.laporan_harian.reduce((max, l) => Math.max(max, l.id_laporan), 0) + 1;
    const isPenutupan = laporanData.jenis_laporan === 'PENUTUPAN';

    // Update victim statuses if provided
    if (updatedKorban && updatedKorban.length > 0) {
      updatedKorban.forEach(uk => {
        const k = this.data.korban.find(item => item.id_korban === uk.id_korban);
        if (k) {
          k.status_korban = uk.status_korban;
        }
      });
    }

    // Update unsur & alut state
    if (updatedUnsur && updatedUnsur.length > 0) {
      this.data.detail_unsur = this.data.detail_unsur.filter(u => u.id_operasi !== id_operasi);
      updatedUnsur.forEach((u, idx) => {
        this.data.detail_unsur.push({
          id_detail_unsur: this.data.detail_unsur.reduce((max, item) => Math.max(max, item.id_detail_unsur || 0), 0) + idx + 1,
          id_operasi,
          nama_unsur: u.nama_unsur,
          jumlah_personil: Number(u.jumlah_personil) || 1,
        });
      });
    }

    if (updatedAlut && updatedAlut.length > 0) {
      this.data.detail_alut = this.data.detail_alut.filter(a => a.id_operasi !== id_operasi);
      updatedAlut.forEach((a, idx) => {
        this.data.detail_alut.push({
          id_detail_alut: this.data.detail_alut.reduce((max, item) => Math.max(max, item.id_detail_alut || 0), 0) + idx + 1,
          id_operasi,
          id_alut: a.id_alut,
          nama_alut: a.nama_alut,
          satuan: a.satuan || 'Unit',
          jumlah_alut: Number(a.jumlah_alut) || 1,
        });
      });
    }

    // If penutupan, update operasi status to CLOSED and save waktu_selesai
    const op = this.data.operasi.find(o => o.id_operasi === id_operasi);
    if (op && isPenutupan) {
      op.status = 'CLOSED';
      op.waktu_selesai = laporanData.waktu_debrief || new Date().toISOString();
    }

    const populatedOp = this.populateOperasi(op!);

    const full_text_wa = laporanData.full_text_wa || generateLapbangWA(
      populatedOp,
      laporanData,
      populatedOp.korban,
      populatedOp.detail_unsur,
      populatedOp.detail_alut,
      isPenutupan
    );

    const newLaporan: LaporanHarian = {
      ...laporanData,
      id_laporan: nextLaporanId,
      id_operasi,
      full_text_wa,
      waktu_laporan: laporanData.waktu_laporan || new Date().toISOString(),
    };

    this.data.laporan_harian.unshift(newLaporan);
    this.saveData();

    return newLaporan;
  }

  public deleteLaporanHarian(id_laporan: number): { success: boolean; rolledBack: boolean } {
    const laporan = this.data.laporan_harian.find(l => l.id_laporan === id_laporan);
    if (!laporan) return { success: false, rolledBack: false };

    const wasPenutupan = laporan.jenis_laporan === 'PENUTUPAN';
    const id_operasi = laporan.id_operasi;

    this.data.laporan_harian = this.data.laporan_harian.filter(l => l.id_laporan !== id_laporan);

    let rolledBack = false;
    if (wasPenutupan) {
      // Check if there is another penutupan report
      const remainingPenutupan = this.data.laporan_harian.some(
        l => l.id_operasi === id_operasi && l.jenis_laporan === 'PENUTUPAN'
      );
      if (!remainingPenutupan) {
        const op = this.data.operasi.find(o => o.id_operasi === id_operasi);
        if (op) {
          op.status = 'OPEN';
          op.waktu_selesai = null;
          rolledBack = true;
        }
      }
    }

    this.saveData();
    return { success: true, rolledBack };
  }

  public rollbackPenutupan(id_operasi: number): boolean {
    const op = this.data.operasi.find(o => o.id_operasi === id_operasi);
    if (!op) return false;

    op.status = 'OPEN';
    op.waktu_selesai = null;

    // Remove any penutupan reports or change to perkembangan
    this.data.laporan_harian = this.data.laporan_harian.filter(
      l => !(l.id_operasi === id_operasi && l.jenis_laporan === 'PENUTUPAN')
    );

    this.saveData();
    return true;
  }

  // --- BRA SAR methods ---
  public getAllBraSar(): BraSar[] {
    if (!this.data.bra_sar) {
      this.data.bra_sar = [];
    }
    // Return sorted newest first
    return [...this.data.bra_sar].sort((a, b) => b.id_bra - a.id_bra);
  }

  public getBraSarById(id_bra: number): BraSar | null {
    if (!this.data.bra_sar) {
      this.data.bra_sar = [];
    }
    return this.data.bra_sar.find(b => b.id_bra === id_bra) || null;
  }

  /**
   * Atomically computes the next sequence number for BRA SAR
   * Format: [nomor_urut]/[satker]/[bulan_tahun]
   * Example: 191/SAR-304/0926
   * Guarantees no race condition / duplicate even if multiple operators create concurrently.
   */
  public getNextBraNumber(satkerKode: string = 'SAR-304'): { no_bra: string; nomor_urut: number; bulan_tahun: string } {
    if (!this.data.bra_sar) {
      this.data.bra_sar = [];
    }
    const now = new Date();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const yy = String(now.getFullYear()).slice(-2);
    const bulan_tahun = `${mm}${yy}`;

    let maxNum = 190; // Default base so the very first generated is 191 as requested in example
    for (const b of this.data.bra_sar) {
      if (b.nomor_urut && b.nomor_urut > maxNum) {
        maxNum = b.nomor_urut;
      }
    }

    const nextUrut = maxNum + 1;
    const no_bra = `${nextUrut}/${satkerKode}/${bulan_tahun}`;
    return { no_bra, nomor_urut: nextUrut, bulan_tahun };
  }

  public createBraSar(braData: Omit<BraSar, 'id_bra'>, originHost: string = ''): BraSar {
    if (!this.data.bra_sar) {
      this.data.bra_sar = [];
    }

    const nextId = this.data.bra_sar.reduce((max, b) => Math.max(max, b.id_bra), 0) + 1;
    
    // Always compute atomic sequence number on server to avoid any collision
    const nextSeq = this.getNextBraNumber(braData.satker_kode || 'SAR-304');
    
    let finalNoBra = braData.no_bra;
    let finalUrut = braData.nomor_urut;
    const exists = this.data.bra_sar.some(b => b.no_bra === finalNoBra);
    if (!finalNoBra || exists) {
      finalNoBra = nextSeq.no_bra;
      finalUrut = nextSeq.nomor_urut;
    }

    const barcodeUrl = originHost 
      ? `${originHost}/?viewBra=${nextId}`
      : `/?viewBra=${nextId}`;

    const newBra: BraSar = {
      ...braData,
      id_bra: nextId,
      no_bra: finalNoBra,
      nomor_urut: finalUrut || nextSeq.nomor_urut,
      bulan_tahun: braData.bulan_tahun || nextSeq.bulan_tahun,
      satker_kode: braData.satker_kode || 'SAR-304',
      barcode_data: braData.barcode_data || barcodeUrl,
      created_at: braData.created_at || new Date().toISOString(),
    };

    this.data.bra_sar.push(newBra);
    this.saveData();
    return newBra;
  }

  public updateBraSar(id_bra: number, updateData: Partial<BraSar>): BraSar | null {
    if (!this.data.bra_sar) return null;
    const idx = this.data.bra_sar.findIndex(b => b.id_bra === id_bra);
    if (idx === -1) return null;

    const existing = this.data.bra_sar[idx];
    const updated: BraSar = {
      ...existing,
      ...updateData,
      id_bra, // protect primary key
    };

    this.data.bra_sar[idx] = updated;
    this.saveData();
    return updated;
  }

  public deleteBraSar(id_bra: number): boolean {
    if (!this.data.bra_sar) return false;
    const initialLen = this.data.bra_sar.length;
    this.data.bra_sar = this.data.bra_sar.filter(b => b.id_bra !== id_bra);
    if (this.data.bra_sar.length !== initialLen) {
      this.saveData();
      return true;
    }
    return false;
  }

  // Generate MySQL dump text
  public generateSQLDump(): string {
    const lines: string[] = [];
    lines.push(`-- ========================================================`);
    lines.push(`-- DUMP DATABASE db_sar_qr - SISTEM QR SAR KANTOR SAR KENDARI`);
    lines.push(`-- Generated on: ${new Date().toISOString()}`);
    lines.push(`-- ========================================================`);
    lines.push(``);
    lines.push(`CREATE DATABASE IF NOT EXISTS \`db_sar_qr\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`);
    lines.push(`USE \`db_sar_qr\`;`);
    lines.push(``);

    // master_alut
    lines.push(`-- Table: master_alut`);
    lines.push(`DROP TABLE IF EXISTS \`master_alut\`;`);
    lines.push(`CREATE TABLE \`master_alut\` (`);
    lines.push(`  \`id_alut\` INT AUTO_INCREMENT PRIMARY KEY,`);
    lines.push(`  \`nama_alut\` VARCHAR(100) NOT NULL,`);
    lines.push(`  \`satuan\` VARCHAR(50) NOT NULL DEFAULT 'Unit'`);
    lines.push(`) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`);
    lines.push(``);
    if (this.data.master_alut.length > 0) {
      lines.push(`INSERT INTO \`master_alut\` (\`id_alut\`, \`nama_alut\`, \`satuan\`) VALUES`);
      const valLines = this.data.master_alut.map(
        a => `(${a.id_alut}, '${escapeSQL(a.nama_alut)}', '${escapeSQL(a.satuan)}')`
      );
      lines.push(valLines.join(',\n') + ';');
    }
    lines.push(``);

    // master_tim
    lines.push(`-- Table: master_tim`);
    lines.push(`DROP TABLE IF EXISTS \`master_tim\`;`);
    lines.push(`CREATE TABLE \`master_tim\` (`);
    lines.push(`  \`id_tim\` INT AUTO_INCREMENT PRIMARY KEY,`);
    lines.push(`  \`nama_tim\` VARCHAR(100) NOT NULL`);
    lines.push(`) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`);
    lines.push(``);
    if (this.data.master_tim.length > 0) {
      lines.push(`INSERT INTO \`master_tim\` (\`id_tim\`, \`nama_tim\`) VALUES`);
      const valLines = this.data.master_tim.map(
        t => `(${t.id_tim}, '${escapeSQL(t.nama_tim)}')`
      );
      lines.push(valLines.join(',\n') + ';');
    }
    lines.push(``);

    // operasi
    lines.push(`-- Table: operasi`);
    lines.push(`DROP TABLE IF EXISTS \`operasi\`;`);
    lines.push(`CREATE TABLE \`operasi\` (`);
    lines.push(`  \`id_operasi\` INT AUTO_INCREMENT PRIMARY KEY,`);
    lines.push(`  \`nama_operasi\` VARCHAR(255) NOT NULL,`);
    lines.push(`  \`nama_pelapor\` VARCHAR(150) DEFAULT NULL,`);
    lines.push(`  \`hubungan_pelapor\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`no_telp_pelapor\` VARCHAR(50) DEFAULT NULL,`);
    lines.push(`  \`waktu_kejadian\` DATETIME NOT NULL,`);
    lines.push(`  \`waktu_lapor\` DATETIME NOT NULL,`);
    lines.push(`  \`waktu_berangkat\` DATETIME DEFAULT NULL,`);
    lines.push(`  \`tim_berangkat\` VARCHAR(255) DEFAULT NULL,`);
    lines.push(`  \`eta_tiba\` DATETIME DEFAULT NULL,`);
    lines.push(`  \`lokasi_kejadian\` TEXT NOT NULL,`);
    lines.push(`  \`koordinat\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`jarak\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`eta_lokasi\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`kronologi\` TEXT NOT NULL,`);
    lines.push(`  \`jumlah_pob\` INT DEFAULT 0,`);
    lines.push(`  \`unsur_terlibat\` TEXT DEFAULT NULL,`);
    lines.push(`  \`alut_palsar\` TEXT DEFAULT NULL,`);
    lines.push(`  \`faktor_penghambat\` TEXT DEFAULT NULL,`);
    lines.push(`  \`cuaca\` TEXT DEFAULT NULL,`);
    lines.push(`  \`gelombang\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`angin\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`status\` ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',`);
    lines.push(`  \`waktu_selesai\` DATETIME DEFAULT NULL,`);
    lines.push(`  \`full_text_wa\` MEDIUMTEXT DEFAULT NULL,`);
    lines.push(`  \`created_at\` TIMESTAMP DEFAULT CURRENT_TIMESTAMP`);
    lines.push(`) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`);
    lines.push(``);

    if (this.data.operasi.length > 0) {
      lines.push(`INSERT INTO \`operasi\` (\`id_operasi\`, \`nama_operasi\`, \`nama_pelapor\`, \`hubungan_pelapor\`, \`no_telp_pelapor\`, \`waktu_kejadian\`, \`waktu_lapor\`, \`waktu_berangkat\`, \`tim_berangkat\`, \`eta_tiba\`, \`lokasi_kejadian\`, \`koordinat\`, \`jarak\`, \`eta_lokasi\`, \`kronologi\`, \`jumlah_pob\`, \`unsur_terlibat\`, \`alut_palsar\`, \`faktor_penghambat\`, \`cuaca\`, \`gelombang\`, \`angin\`, \`status\`, \`waktu_selesai\`, \`full_text_wa\`) VALUES`);
      const valLines = this.data.operasi.map(o => {
        const seles = o.waktu_selesai ? `'${o.waktu_selesai}'` : 'NULL';
        return `(${o.id_operasi}, '${escapeSQL(o.nama_operasi)}', '${escapeSQL(o.nama_pelapor)}', '${escapeSQL(o.hubungan_pelapor)}', '${escapeSQL(o.no_telp_pelapor)}', '${o.waktu_kejadian}', '${o.waktu_lapor}', '${o.waktu_berangkat}', '${escapeSQL(o.tim_berangkat)}', '${o.eta_tiba}', '${escapeSQL(o.lokasi_kejadian)}', '${escapeSQL(o.koordinat)}', '${escapeSQL(o.jarak)}', '${escapeSQL(o.eta_lokasi)}', '${escapeSQL(o.kronologi)}', ${o.jumlah_pob}, '${escapeSQL(o.unsur_terlibat)}', '${escapeSQL(o.alut_palsar)}', '${escapeSQL(o.faktor_penghambat)}', '${escapeSQL(o.cuaca)}', '${escapeSQL(o.gelombang)}', '${escapeSQL(o.angin)}', '${o.status}', ${seles}, '${escapeSQL(o.full_text_wa)}')`;
      });
      lines.push(valLines.join(',\n') + ';');
    }
    lines.push(``);

    // korban
    lines.push(`-- Table: korban`);
    lines.push(`DROP TABLE IF EXISTS \`korban\`;`);
    lines.push(`CREATE TABLE \`korban\` (`);
    lines.push(`  \`id_korban\` INT AUTO_INCREMENT PRIMARY KEY,`);
    lines.push(`  \`id_operasi\` INT NOT NULL,`);
    lines.push(`  \`nama_korban\` VARCHAR(100) NOT NULL,`);
    lines.push(`  \`umur\` VARCHAR(50) DEFAULT NULL,`);
    lines.push(`  \`jk\` VARCHAR(20) DEFAULT NULL,`);
    lines.push(`  \`alamat\` TEXT DEFAULT NULL,`);
    lines.push(`  \`status_korban\` ENUM('S','MD','DP','H') NOT NULL DEFAULT 'DP',`);
    lines.push(`  FOREIGN KEY (\`id_operasi\`) REFERENCES \`operasi\` (\`id_operasi\`) ON DELETE CASCADE`);
    lines.push(`) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`);
    lines.push(``);

    if (this.data.korban.length > 0) {
      lines.push(`INSERT INTO \`korban\` (\`id_korban\`, \`id_operasi\`, \`nama_korban\`, \`umur\`, \`jk\`, \`alamat\`, \`status_korban\`) VALUES`);
      const valLines = this.data.korban.map(
        k => `(${k.id_korban}, ${k.id_operasi}, '${escapeSQL(k.nama_korban)}', '${escapeSQL(k.umur)}', '${escapeSQL(k.jk)}', '${escapeSQL(k.alamat)}', '${k.status_korban}')`
      );
      lines.push(valLines.join(',\n') + ';');
    }
    lines.push(``);

    // bra_sar
    lines.push(`-- Table: bra_sar`);
    lines.push(`DROP TABLE IF EXISTS \`bra_sar\`;`);
    lines.push(`CREATE TABLE \`bra_sar\` (`);
    lines.push(`  \`id_bra\` INT AUTO_INCREMENT PRIMARY KEY,`);
    lines.push(`  \`kategori\` ENUM('MAPEL','SAR_UMUM','PERGERAKAN_KAPAL') NOT NULL,`);
    lines.push(`  \`no_bra\` VARCHAR(100) NOT NULL UNIQUE,`);
    lines.push(`  \`nomor_urut\` INT NOT NULL,`);
    lines.push(`  \`satker_kode\` VARCHAR(50) NOT NULL,`);
    lines.push(`  \`bulan_tahun\` VARCHAR(20) NOT NULL,`);
    lines.push(`  \`id_operasi\` INT DEFAULT NULL,`);
    lines.push(`  \`nama_operasi\` VARCHAR(255) DEFAULT NULL,`);
    lines.push(`  \`derajat\` VARCHAR(20) NOT NULL DEFAULT 'SGR',`);
    lines.push(`  \`dari\` TEXT NOT NULL,`);
    lines.push(`  \`aksi\` TEXT NOT NULL,`);
    lines.push(`  \`info\` TEXT NOT NULL,`);
    lines.push(`  \`sifat\` VARCHAR(50) DEFAULT NULL,`);
    lines.push(`  \`menbra\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`perihal\` TEXT DEFAULT NULL,`);
    lines.push(`  \`tanggal_waktu\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`twu\` VARCHAR(50) NOT NULL,`);
    lines.push(`  \`twb\` VARCHAR(50) NOT NULL,`);
    lines.push(`  \`jumlah_kata\` VARCHAR(50) DEFAULT NULL,`);
    lines.push(`  \`sections_json\` LONGTEXT NOT NULL,`);
    lines.push(`  \`pengirim\` VARCHAR(255) NOT NULL,`);
    lines.push(`  \`nama_smc\` VARCHAR(150) NOT NULL,`);
    lines.push(`  \`pangkat_smc\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`jabatan_smc\` VARCHAR(100) DEFAULT NULL,`);
    lines.push(`  \`barcode_data\` TEXT DEFAULT NULL,`);
    lines.push(`  \`created_at\` TIMESTAMP DEFAULT CURRENT_TIMESTAMP`);
    lines.push(`) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`);
    lines.push(``);

    if (this.data.bra_sar && this.data.bra_sar.length > 0) {
      lines.push(`INSERT INTO \`bra_sar\` (\`id_bra\`, \`kategori\`, \`no_bra\`, \`nomor_urut\`, \`satker_kode\`, \`bulan_tahun\`, \`id_operasi\`, \`nama_operasi\`, \`derajat\`, \`dari\`, \`aksi\`, \`info\`, \`sifat\`, \`menbra\`, \`perihal\`, \`tanggal_waktu\`, \`twu\`, \`twb\`, \`jumlah_kata\`, \`sections_json\`, \`pengirim\`, \`nama_smc\`, \`pangkat_smc\`, \`jabatan_smc\`, \`barcode_data\`) VALUES`);
      const valLines = this.data.bra_sar.map(b => {
        const idOp = b.id_operasi ? b.id_operasi : 'NULL';
        const secJson = escapeSQL(JSON.stringify(b.sections || []));
        return `(${b.id_bra}, '${b.kategori}', '${escapeSQL(b.no_bra)}', ${b.nomor_urut}, '${escapeSQL(b.satker_kode)}', '${escapeSQL(b.bulan_tahun)}', ${idOp}, '${escapeSQL(b.nama_operasi || '')}', '${escapeSQL(b.derajat || 'SGR')}', '${escapeSQL(b.dari)}', '${escapeSQL(b.aksi)}', '${escapeSQL(b.info)}', '${escapeSQL(b.sifat || '')}', '${escapeSQL(b.menbra || '')}', '${escapeSQL(b.perihal || '')}', '${escapeSQL(b.tanggal_waktu || '')}', '${escapeSQL(b.twu)}', '${escapeSQL(b.twb)}', '${escapeSQL(b.jumlah_kata || '')}', '${secJson}', '${escapeSQL(b.pengirim)}', '${escapeSQL(b.nama_smc)}', '${escapeSQL(b.pangkat_smc || '')}', '${escapeSQL(b.jabatan_smc || '')}', '${escapeSQL(b.barcode_data || '')}')`;
      });
      lines.push(valLines.join(',\n') + ';');
      lines.push(``);
    }

    return lines.join('\n');
  }
}

function escapeSQL(val: string | null | undefined): string {
  if (val === null || val === undefined) return '';
  return val.replace(/'/g, "''").replace(/\\/g, '\\\\');
}

export const db = new Database();
