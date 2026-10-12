<?php
/**
 * QR-SAR - Master Data Controller
 * KPP Kendari - Basarnas
 * Sub-Menu: 1. Alut (Armada & Palsar), 2. Potensi (Unsur / Tim SAR), 3. SMC (Pejabat Berwenang)
 * 100% Faithful to Reference MasterDataPage.tsx
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$tab = $_GET['tab'] ?? 'alut';
if (!in_array($tab, ['alut', 'tim', 'pejabat'])) {
    $tab = 'alut';
}

// Handle POST actions
if ($method === 'POST') {
    if (!AuthHelper::isAdmin()) {
        setFlash('error', 'Akses ditolak: Hanya administrator yang dapat memodifikasi Master Data.');
        header("Location: " . BASE_URL . "/master.php?tab=" . urlencode($tab));
        exit;
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
            setFlash('success', "Alut/Palsar \"{$namaAlut}\" berhasil ditambahkan.");
        } else {
            setFlash('error', 'Nama Alut/Palsar wajib diisi.');
        }
        header("Location: " . BASE_URL . "/master.php?tab=alut");
        exit;
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
            setFlash('success', "Data Alut berhasil diperbarui.");
        }
        header("Location: " . BASE_URL . "/master.php?tab=alut");
        exit;
    }

    if ($action === 'add_tim') {
        $namaTim = trim($_POST['nama_tim'] ?? '');
        if ($namaTim !== '') {
            MasterModel::createTim([
                'nama_tim' => $namaTim,
                'kategori' => $_POST['kategori'] ?? 'Potensi'
            ]);
            setFlash('success', "Unsur potensi \"{$namaTim}\" berhasil ditambahkan.");
        } else {
            setFlash('error', 'Nama Potensi SAR wajib diisi.');
        }
        header("Location: " . BASE_URL . "/master.php?tab=tim");
        exit;
    }

    if ($action === 'edit_tim') {
        $id = (int)($_POST['id_tim'] ?? 0);
        $namaTim = trim($_POST['nama_tim'] ?? '');
        if ($id > 0 && $namaTim !== '') {
            MasterModel::updateTim($id, [
                'nama_tim' => $namaTim
            ]);
            setFlash('success', "Data Potensi SAR berhasil diperbarui.");
        }
        header("Location: " . BASE_URL . "/master.php?tab=tim");
        exit;
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
            setFlash('error', 'Nama Pejabat dan Jabatan wajib diisi.');
        } else {
            if ($action === 'edit_pejabat' && $id) {
                $data['id'] = $id;
                MasterModel::updatePejabat($id, $data);
                setFlash('success', "Data Pejabat \"{$data['nama']}\" berhasil diperbarui.");
            } else {
                MasterModel::createPejabat($data);
                setFlash('success', "Pejabat baru \"{$data['nama']}\" berhasil ditambahkan.");
            }
        }
        header("Location: " . BASE_URL . "/master.php?tab=pejabat");
        exit;
    }
}

// Handle GET actions (delete & toggle)
if ($action === 'delete_alut') {
    if (AuthHelper::isAdmin()) {
        MasterModel::deleteAlut((int)($_GET['id'] ?? 0));
        setFlash('success', 'Alut berhasil dihapus dari master data.');
    }
    header("Location: " . BASE_URL . "/master.php?tab=alut");
    exit;
}

if ($action === 'delete_tim') {
    if (AuthHelper::isAdmin()) {
        MasterModel::deleteTim((int)($_GET['id'] ?? 0));
        setFlash('success', 'Unsur potensi berhasil dihapus dari master data.');
    }
    header("Location: " . BASE_URL . "/master.php?tab=tim");
    exit;
}

if ($action === 'delete_pejabat') {
    if (AuthHelper::isAdmin()) {
        MasterModel::deletePejabat($_GET['id'] ?? '');
        setFlash('success', 'Data pejabat berhasil dihapus.');
    }
    header("Location: " . BASE_URL . "/master.php?tab=pejabat");
    exit;
}

if ($action === 'toggle_smc') {
    if (AuthHelper::isAdmin()) {
        $id = $_GET['id'] ?? '';
        $pejabatList = MasterModel::getPejabat();
        foreach ($pejabatList as $p) {
            if (($p['id'] ?? '') === $id) {
                if (empty($p['is_default_smc'])) {
                    MasterModel::setDefaultSmc($id);
                    setFlash('success', "Pejabat ditetapkan sebagai Default SMC.");
                } else {
                    MasterModel::updatePejabat($id, array_merge($p, ['is_default_smc' => 0]));
                    setFlash('success', "Status Default SMC dinonaktifkan.");
                }
                break;
            }
        }
    }
    header("Location: " . BASE_URL . "/master.php?tab=pejabat");
    exit;
}

// Render Master Data View
$pageTitle = 'Master Data Operasional SAR';
$currentRoute = 'master';
$navbarTitle = 'Database Master Operasional SAR';
$navbarSubtitle = 'Peralatan Alut, Potensi Tim SAR, serta Pejabat Penandatangan & SMC';

$masterAlut = MasterModel::getAlut();
$masterTim = MasterModel::getTim();
$pejabatList = MasterModel::getPejabat();

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/master.php';
require_once VIEWS_DIR . '/layout/footer.php';
