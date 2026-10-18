<?php
/**
 * QR-SAR - Master Data Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Handles operational master data: Alut (Armada & Palsar), Potensi (Unsur/Tim SAR), and SMC (Pejabat Berwenang).
 */

declare(strict_types=1);

class MasterController extends BaseController {
    /**
     * Dispatch and render master data management views and handle mutations.
     */
    public function index(): void {
        $this->requireLogin();

        $action = $_GET['action'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $tab = $_GET['tab'] ?? 'alut';
        if (!in_array($tab, ['alut', 'tim', 'pejabat'], true)) {
            $tab = 'alut';
        }

        // Handle POST actions
        if ($method === 'POST') {
            if (!AuthHelper::isAdmin()) {
                $this->setFlash('error', 'Akses ditolak: Hanya administrator yang dapat memodifikasi Master Data.');
                $this->redirect("/master.php?tab=" . urlencode($tab));
            }

            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa.');
                $this->redirect("/master.php?tab=" . urlencode($tab));
            }

            if ($action === 'add_alut') {
                $namaAlut = trim($_POST['nama_alut'] ?? '');
                $satuan = trim($_POST['satuan'] ?? 'Unit');
                if ($namaAlut !== '') {
                    MasterModel::createAlut([
                        'nama_alut' => $namaAlut,
                        'satuan' => $satuan,
                        'kategori' => $_POST['kategori'] ?? 'Alut'
                    ]);
                    $this->setFlash('success', "Alut/Palsar \"{$namaAlut}\" berhasil ditambahkan.");
                } else {
                    $this->setFlash('error', 'Nama Alut/Palsar wajib diisi.');
                }
                $this->redirect('/master.php?tab=alut');
            }

            if ($action === 'edit_alut') {
                $id = (int)($_POST['id_alut'] ?? 0);
                $namaAlut = trim($_POST['nama_alut'] ?? '');
                $satuan = trim($_POST['satuan'] ?? 'Unit');
                if ($id > 0 && $namaAlut !== '') {
                    MasterModel::updateAlut($id, [
                        'nama_alut' => $namaAlut,
                        'satuan' => $satuan
                    ]);
                    $this->setFlash('success', "Data Alut berhasil diperbarui.");
                }
                $this->redirect('/master.php?tab=alut');
            }

            if ($action === 'delete_alut') {
                MasterModel::deleteAlut((int)($_POST['id'] ?? 0));
                $this->setFlash('success', 'Alut berhasil dihapus dari master data.');
                $this->redirect('/master.php?tab=alut');
            }

            if ($action === 'add_tim') {
                $namaTim = trim($_POST['nama_tim'] ?? '');
                if ($namaTim !== '') {
                    MasterModel::createTim([
                        'nama_tim' => $namaTim,
                        'kategori' => $_POST['kategori'] ?? 'Potensi'
                    ]);
                    $this->setFlash('success', "Unsur potensi \"{$namaTim}\" berhasil ditambahkan.");
                } else {
                    $this->setFlash('error', 'Nama Potensi SAR wajib diisi.');
                }
                $this->redirect('/master.php?tab=tim');
            }

            if ($action === 'edit_tim') {
                $id = (int)($_POST['id_tim'] ?? 0);
                $namaTim = trim($_POST['nama_tim'] ?? '');
                if ($id > 0 && $namaTim !== '') {
                    MasterModel::updateTim($id, [
                        'nama_tim' => $namaTim
                    ]);
                    $this->setFlash('success', "Data Potensi SAR berhasil diperbarui.");
                }
                $this->redirect('/master.php?tab=tim');
            }

            if ($action === 'delete_tim') {
                MasterModel::deleteTim((int)($_POST['id'] ?? 0));
                $this->setFlash('success', 'Unsur potensi berhasil dihapus dari master data.');
                $this->redirect('/master.php?tab=tim');
            }

            if ($action === 'add_pejabat' || $action === 'edit_pejabat') {
                $id = $_POST['id'] ?? null;
                $data = [
                    'nama' => trim($_POST['nama'] ?? ''),
                    'nip' => trim($_POST['nip'] ?? ''),
                    'pangkat' => trim($_POST['pangkat'] ?? ''),
                    'jabatan' => trim($_POST['jabatan'] ?? ''),
                    'is_default_smc' => !empty($_POST['is_default_smc']) ? 1 : 0
                ];

                if ($data['nama'] === '' || $data['jabatan'] === '') {
                    $this->setFlash('error', 'Nama Pejabat dan Jabatan wajib diisi.');
                } else {
                    if ($action === 'edit_pejabat' && $id) {
                        $data['id'] = $id;
                        MasterModel::updatePejabat($id, $data);
                        $this->setFlash('success', "Data Pejabat \"{$data['nama']}\" berhasil diperbarui.");
                    } else {
                        MasterModel::createPejabat($data);
                        $this->setFlash('success', "Pejabat baru \"{$data['nama']}\" berhasil ditambahkan.");
                    }
                }
                $this->redirect('/master.php?tab=pejabat');
            }

            if ($action === 'delete_pejabat') {
                MasterModel::deletePejabat((string)($_POST['id'] ?? ''));
                $this->setFlash('success', 'Data pejabat berhasil dihapus.');
                $this->redirect('/master.php?tab=pejabat');
            }

            if ($action === 'toggle_smc') {
                $id = (string)($_POST['id'] ?? '');
                $pejabatList = MasterModel::getPejabat();
                foreach ($pejabatList as $p) {
                    if (($p['id'] ?? '') === $id) {
                        if (empty($p['is_default_smc'])) {
                            MasterModel::setDefaultSmc($id);
                            $this->setFlash('success', "Pejabat ditetapkan sebagai Default SMC.");
                        } else {
                            MasterModel::updatePejabat($id, array_merge($p, ['is_default_smc' => 0]));
                            $this->setFlash('success', "Status Default SMC dinonaktifkan.");
                        }
                        break;
                    }
                }
                $this->redirect('/master.php?tab=pejabat');
            }
        }

        // Reject any GET-based delete or toggle actions
        if (in_array($action, ['delete_alut', 'delete_tim', 'delete_pejabat', 'toggle_smc'], true) && $method !== 'POST') {
            $this->setFlash('error', 'Metode tidak diizinkan: Tindakan ini harus menggunakan metode POST dengan token keamanan.');
            $this->redirect("/master.php?tab=" . urlencode($tab));
        }

        $masterAlut = MasterModel::getAlut();
        $masterTim = MasterModel::getTim();
        $pejabatList = MasterModel::getPejabat();

        $this->render('master/index', [
            'masterAlut' => $masterAlut,
            'masterTim' => $masterTim,
            'pejabatList' => $pejabatList
        ], [
            'title' => 'Master Data Operasional SAR',
            'route' => 'master',
            'navbarTitle' => 'Database Master Operasional SAR',
            'navbarSubtitle' => 'Peralatan Alut, Potensi Tim SAR, serta Pejabat Penandatangan & SMC'
        ]);
    }
}
