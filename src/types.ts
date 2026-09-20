export type StatusOperasi = 'OPEN' | 'CLOSED';
export type StatusKorban = 'S' | 'MD' | 'DP' | 'H'; // S: Selamat, MD: Meninggal Dunia, DP: Dalam Pencarian, H: Hilang / Nihil
export type JenisLaporan = 'PERKEMBANGAN' | 'PENUTUPAN';

export interface MasterAlut {
  id_alut: number;
  nama_alut: string;
  satuan: string;
}

export interface MasterTim {
  id_tim: number;
  nama_tim: string;
}

export interface Korban {
  id_korban: number;
  id_operasi: number;
  nama_korban: string;
  umur: string;
  jk: string; // 'Laki-laki' | 'Perempuan'
  alamat: string;
  status_korban: StatusKorban;
}

export interface DetailUnsur {
  id_detail_unsur?: number;
  id_operasi?: number;
  nama_unsur: string;
  jumlah_personil: number;
}

export interface DetailAlut {
  id_detail_alut?: number;
  id_operasi?: number;
  id_alut?: number;
  nama_alut: string;
  satuan: string;
  jumlah_alut: number;
}

export interface DetailTimBerangkat {
  id_detail_tim: number;
  id_operasi: number;
  id_tim: number;
  nama_tim?: string;
}

export interface RenopsSRU {
  id_sru: number;
  id_renops: number;
  nama_sru: string;
  koordinat_area: string;
  luas_area: string;
  pola_pencarian: string;
}

export interface Renops {
  id_renops: number;
  id_operasi: number;
  hari_ke: string; // e.g. "H.1", "H.2"
  tanggal_laporan: string; // YYYY-MM-DD
  waktu_briefing: string; // ISO datetime
  waktu_mulai_ops: string; // ISO datetime
  cuaca: string;
  unsur_text: string;
  alut_text: string;
  full_text_wa: string;
  created_at: string;
  srus?: RenopsSRU[];
}

export interface LaporanHarian {
  id_laporan: number;
  id_operasi: number;
  hari_ke: string;
  jenis_laporan: JenisLaporan;
  isi_laporan: string;
  waktu_debrief?: string;
  full_text_wa: string;
  waktu_laporan: string;
}

export interface Operasi {
  id_operasi: number;
  nama_operasi: string;
  nama_pelapor: string;
  hubungan_pelapor: string;
  no_telp_pelapor: string;
  waktu_kejadian: string; // ISO datetime
  waktu_lapor: string; // ISO datetime
  waktu_berangkat: string; // ISO datetime
  tim_berangkat: string; // e.g. "Tim Rescue Kansar Kendari, Pos SAR Kolaka"
  eta_tiba: string; // ISO datetime
  lokasi_kejadian: string;
  koordinat: string; // e.g. 03° 58' 30" S - 122° 35' 12" E
  jarak: string; // e.g. "15 Nm"
  eta_lokasi: string; // e.g. "2 Jam"
  kronologi: string;
  jumlah_pob: number;
  unsur_terlibat: string;
  alut_palsar: string;
  faktor_penghambat: string;
  cuaca: string;
  gelombang: string;
  angin: string;
  status: StatusOperasi;
  waktu_selesai?: string | null;
  full_text_wa: string;
  created_at: string;
  
  // Relations and computed helpers populated when fetched in detail
  korban?: Korban[];
  detail_unsur?: DetailUnsur[];
  detail_alut?: DetailAlut[];
  detail_tim_berangkat?: DetailTimBerangkat[];
  renops?: Renops[];
  laporan_harian?: LaporanHarian[];
  laporan?: LaporanHarian[];
  pob?: number;
  korban_selamat?: number;
  korban_meninggal?: number;
  korban_hilang?: number;
}

export type KategoriBra = 'MAPEL' | 'SAR_UMUM' | 'PERGERAKAN_KAPAL';

export interface BraSection {
  kode: string; // e.g. 'AAA', 'BBB', 'CCC', 'DDD', etc.
  isi: string;
}

export interface BraSar {
  id_bra: number;
  kategori: KategoriBra;
  no_bra: string; // e.g. "191/SAR-304/0926"
  nomor_urut: number;
  satker_kode: string;
  bulan_tahun: string;
  id_operasi?: number | null;
  nama_operasi?: string;

  // Header telegram fields
  panggilan?: string;
  jenis?: string;
  nr_stn?: string;
  derajat: string; // default: 'SGR'
  instruksi_penerusan?: string;

  dari: string;
  aksi: string;
  info: string;
  sifat?: string; // 'SEGERA'
  menbra?: string; // e.g. '197/SAR-304/0726'
  perihal?: string;

  tanggal_waktu: string;
  twu: string; // e.g. "0920 0700 H"
  twb: string; // e.g. "0920 0645 H"
  jumlah_kata?: string;

  // Telegram message body sections: AAA, BBB, CCC, DDD, etc.
  sections: BraSection[];

  // Pengirim / SMC
  pengirim: string;
  nama_smc: string;
  pangkat_smc: string;
  jabatan_smc: string;

  // SENKOM processing columns
  twt?: string;
  twk?: string;
  sistem?: string;
  paraf?: string;

  barcode_data?: string;
  created_at: string;
}

export interface Pejabat {
  id: string;
  nama: string;
  nip: string;
  pangkat?: string;
  jabatan: string;
  is_default_smc?: boolean;
}

export interface AppSettings {
  logo_url?: string;
  satker_kode?: string;
  satker_nama?: string;
  alamat?: string;
  no_telp?: string;
  email?: string;
  call_sign?: string;
  frekuensi_default?: string;
  pejabat_list?: Pejabat[];
  admin_username?: string;
  admin_password?: string;
  admin_email?: string;
  reset_token?: string;
  reset_token_expiry?: string;
}

export type UserRole = 'ADMIN' | 'OPERATOR_SENKOM' | 'RESCUER' | 'KASIE_OPS' | 'KEPALA_KANTOR';

export interface UserSession {
  id: string;
  nama: string;
  nip: string;
  role: UserRole;
  roleTitle: string;
  pangkat: string;
  jabatan: string;
  avatar?: string;
  loginAt: string;
}

export type ThemePalette = 'orange' | 'navy' | 'emerald' | 'gold';

export interface DatabaseSchema {
  master_alut: MasterAlut[];
  master_tim: MasterTim[];
  operasi: Operasi[];
  korban: Korban[];
  detail_tim_berangkat: DetailTimBerangkat[];
  detail_unsur: DetailUnsur[];
  detail_alut: DetailAlut[];
  renops: Renops[];
  renops_sru: RenopsSRU[];
  laporan_harian: LaporanHarian[];
  bra_sar?: BraSar[];
  app_settings?: AppSettings;
}
