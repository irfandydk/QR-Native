<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Handles BRA archive, creation/edit form, digital signature verification, and public verification portal.
 */

declare(strict_types=1);

class BraController extends BaseController {
    /**
     * Main dispatcher for BRA requests.
     */
    public function handle(): void {
        $action = $_GET['action'] ?? null;
        $viewId = $_GET['view'] ?? ($action === 'view' ? ($_GET['id'] ?? null) : null);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // If 'id' is supplied without explicit 'action' and not in view mode, default to edit form
        if (!$action && isset($_GET['id']) && $viewId === null && $method === 'GET') {
            $action = 'edit';
        }

        // 1. View single BRA document sheet / Public QR Verification Portal
        if ($viewId !== null) {
            $this->showDocument($viewId);
            return;
        }

        // 2. Action: Save (Create or Update)
        if ($method === 'POST' && $action === 'save') {
            $this->save();
            return;
        }

        // 3. Action: Delete (POST Only with CSRF)
        if ($action === 'delete') {
            $this->delete();
            return;
        }

        // 4. Action: Create or Edit Form
        if ($action === 'create' || $action === 'edit') {
            $this->showForm($action);
            return;
        }

        // 5. Default: List Archive
        $this->listArchive();
    }

    /**
     * Render single BRA document or public verification portal.
     *
     * @param mixed $viewId
     */
    protected function showDocument(mixed $viewId): void {
        $pageTitle = 'Verifikasi Dokumen Resmi Berita Radio SAR';
        $currentRoute = 'bra';
        $navbarTitle = 'Naskah Resmi BRA SAR Terverifikasi';
        $isGuest = !AuthHelper::isLoggedIn();

        $rawView = trim((string)$viewId);
        $rawToken = isset($_GET['token']) ? trim((string)$_GET['token']) : null;

        // 1. Sanitasi Ketat Parameter URL: Hanya izinkan integer positif murni (1-9999999999)
        $isValidParam = (bool)preg_match('/^[1-9][0-9]{0,9}$/', $rawView);
        $docId = $isValidParam ? (int)$rawView : 0;

        // 2. Query Record Dokumen secara aman via prepared statement
        $bra = $docId > 0 ? BraModel::getById($docId) : null;

        // 3. Evaluasi Status Keabsahan Dokumen & Token Tanda Tangan Digital
        $verificationStatus = 'VALID';
        $verificationMessage = '';

        if (!$bra) {
            $verificationStatus = 'NOT_FOUND';
            $verificationMessage = 'Dokumen Berita Radio SAR dengan ID ini tidak terdaftar dalam pangkalan data resmi Basarnas KPP Kendari.';
        } else {
            if ($rawToken !== null) {
                // Jika token disertakan di URL, verifikasi kecocokan tanda tangan digital
                if (!BraModel::verifyToken($bra, $rawToken)) {
                    $verificationStatus = 'INVALID_TOKEN';
                    $verificationMessage = 'Tanda tangan digital atau token verifikasi tidak valid. Dokumen diduga mengalami manipulasi URL atau tidak sah.';
                } else {
                    $verificationStatus = 'VERIFIED_SIGNATURE';
                }
            } else {
                // Dokumen terdaftar tanpa token di URL (pencarian internal atau QR generasi lama)
                $verificationStatus = 'LEGACY_VERIFIED';
            }
        }

        // 4. Tangani Dokumen Tidak Sah / Parameter URL Manipulasi (Zero-Leakage Policy)
        if ($verificationStatus === 'NOT_FOUND' || $verificationStatus === 'INVALID_TOKEN') {
            http_response_code(404);
            $invalidRef = htmlspecialchars($rawView, ENT_QUOTES, 'UTF-8');
            $invalidToken = $rawToken !== null ? htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') : null;

            $this->render('bra/verify_invalid', [
                'invalidRef' => $invalidRef,
                'invalidToken' => $invalidToken,
                'verificationStatus' => $verificationStatus
            ], [
                'layout' => 'public',
                'title' => 'Verifikasi Dokumen Tidak Sah'
            ]);
            return;
        }

        // 5. Dokumen Sah: Render Portal Khusus Publik jika Guest
        if ($isGuest) {
            $this->render('bra/view', [
                'bra' => $bra,
                'verificationStatus' => $verificationStatus,
                'verificationMessage' => $verificationMessage,
                'pageTitle' => $pageTitle,
                'currentRoute' => $currentRoute,
                'navbarTitle' => $navbarTitle,
                'isGuest' => $isGuest,
                'docId' => $docId
            ], [
                'layout' => 'public',
                'title' => 'Verifikasi Berita Radio SAR'
            ]);
            return;
        }

        // 6. Dokumen Sah: Render Layout Lengkap Internal jika Pengguna Sudah Login
        $this->render('bra/view', [
            'bra' => $bra,
            'verificationStatus' => $verificationStatus,
            'verificationMessage' => $verificationMessage,
            'pageTitle' => $pageTitle,
            'currentRoute' => $currentRoute,
            'navbarTitle' => $navbarTitle,
            'isGuest' => $isGuest,
            'docId' => $docId
        ], [
            'layout' => 'default',
            'route' => 'bra',
            'title' => 'Naskah Berita Radio SAR',
            'navbarTitle' => 'LEMBAR DOKUMEN BERITA RADIO SAR (BRA)'
        ]);
    }

    /**
     * Save new or updated BRA document.
     */
    protected function save(): void {
        $this->requireLogin();

        if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa.');
            $this->redirect('/bra.php');
        }

        $id = (int)($_POST['id_bra'] ?? $_GET['id'] ?? 0);
        try {
            if ($id > 0) {
                BraModel::update($id, $_POST);
                $this->setFlash('success', 'Naskah Berita Radio SAR berhasil diperbarui!');
                $this->redirect("/bra.php?view={$id}");
            } else {
                $newId = BraModel::create($_POST);
                $this->setFlash('success', 'Naskah Berita Radio SAR berhasil diterbitkan!');
                $this->redirect("/bra.php?view={$newId}");
            }
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal menyimpan naskah BRA: ' . $e->getMessage());
            $this->redirect('/bra.php' . ($id > 0 ? "?action=edit&id={$id}" : "?action=create"));
        }
    }

    /**
     * Delete BRA document from archive.
     */
    protected function delete(): void {
        $this->requireLogin();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== 'POST') {
            $this->setFlash('error', 'Metode tidak diizinkan: Penghapusan naskah BRA harus menggunakan metode POST.');
            $this->redirect('/bra.php');
        }

        if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa.');
            $this->redirect('/bra.php');
        }

        if (!AuthHelper::canDelete()) {
            $this->setFlash('error', 'Akses ditolak: Hanya administrator yang berwenang menghapus arsip BRA.');
            $this->redirect('/bra.php');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            BraModel::delete($id);
            $this->setFlash('success', 'Dokumen Berita Radio SAR berhasil dihapus dari arsip.');
        }
        $this->redirect('/bra.php');
    }

    /**
     * Show form for creating or editing BRA document.
     *
     * @param string $action
     */
    protected function showForm(string $action): void {
        $this->requireLogin();

        $pageTitle = $action === 'edit' ? 'Edit Naskah BRA' : 'Buat Naskah BRA Baru';
        $navbarTitle = $pageTitle;

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $bra = $id > 0 ? BraModel::getById($id) : null;

        $this->render('bra/form', [
            'action' => $action,
            'pageTitle' => $pageTitle,
            'navbarTitle' => $navbarTitle,
            'bra' => $bra,
            'id' => $id
        ], [
            'title' => $pageTitle,
            'route' => 'bra',
            'navbarTitle' => $navbarTitle
        ]);
    }

    /**
     * Display list archive of BRA documents.
     */
    protected function listArchive(): void {
        $this->requireLogin();

        $this->render('bra/list', [], [
            'title' => 'Arsip Berita Radio SAR (BRA)',
            'route' => 'bra',
            'navbarTitle' => 'Arsip Berita Radio SAR KPP Kendari'
        ]);
    }
}
