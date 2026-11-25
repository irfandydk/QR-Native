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
     * Resolve strict, open-redirect-safe return navigation context for BRA pages.
     * Only allows returning to Operasi Detail BRA sub-tab when return_to=operasi_bra
     * and return_op is a positive integer.
     *
     * @param array<string, mixed>|null $source
     * @return array{returnTo: string, returnOp: int, backUrl: string, backLabel: string, returnQuery: string}
     */
    public static function resolveReturnContext(?array $source = null): array {
        $src = $source ?? $_GET;
        $returnTo = trim((string)($src['return_to'] ?? ''));
        $rawOp = trim((string)($src['return_op'] ?? ''));

        if ($returnTo === 'operasi_bra' && preg_match('/^[1-9][0-9]{0,9}$/', $rawOp) === 1) {
            $opId = (int)$rawOp;
            return [
                'returnTo' => 'operasi_bra',
                'returnOp' => $opId,
                'backUrl' => BASE_URL . '/operasi_detail.php?id=' . $opId . '&tab=bra',
                'backLabel' => 'Kembali ke BRA Operasi SAR',
                'returnQuery' => '&return_to=operasi_bra&return_op=' . $opId,
            ];
        }

        return [
            'returnTo' => '',
            'returnOp' => 0,
            'backUrl' => BASE_URL . '/bra.php',
            'backLabel' => 'Kembali ke Daftar',
            'returnQuery' => '',
        ];
    }

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

        // 1. Portal Verifikasi Khusus BRA SAR (?action=verify&id=...&token=...)
        if ($action === 'verify' || $action === 'verify_doc') {
            $this->verifyDocument($_GET['id'] ?? $_GET['view'] ?? '');
            return;
        }

        // 2. Halaman Khusus Melihat Dokumen BRA Publik Read-Only (?action=publik&id=...&token=...)
        if ($action === 'publik' || $action === 'bra_publik') {
            $this->publicDocument($_GET['id'] ?? $_GET['view'] ?? '');
            return;
        }

        // 3. View single BRA document sheet (internal) / Legacy QR URL compatibility (?view=ID&token=TOKEN)
        if ($viewId !== null) {
            $this->showDocument($viewId);
            return;
        }

        // 4. Action: Save (Create or Update)
        if ($method === 'POST' && $action === 'save') {
            $this->save();
            return;
        }

        // 5. Action: Delete (POST Only with CSRF)
        if ($action === 'delete') {
            $this->delete();
            return;
        }

        // 6. Action: Create or Edit Form
        if ($action === 'create' || $action === 'edit') {
            $this->showForm($action);
            return;
        }

        // 7. Default: List Archive
        $this->listArchive();
    }

    /**
     * Validasi ketat ID dokumen BRA & token tanda tangan digital secara terpusat.
     *
     * @param mixed $rawId
     * @return array{ok:bool, bra:?array, status:string, message:string, rawView:string, rawToken:?string, docId:int}
     */
    protected function resolveBraAccess(mixed $rawId, ?string $rawToken, bool $requireToken): array {
        $rawView = trim((string)$rawId);
        $cleanToken = $rawToken !== null ? trim($rawToken) : null;
        if ($cleanToken === '') {
            $cleanToken = null;
        }

        $isValidParam = (bool)preg_match('/^[1-9][0-9]{0,9}$/', $rawView);
        $docId = $isValidParam ? (int)$rawView : 0;
        $bra = $docId > 0 ? BraModel::getById($docId) : null;

        if (!$bra) {
            return [
                'ok' => false,
                'bra' => null,
                'status' => 'NOT_FOUND',
                'message' => 'Dokumen Berita Radio SAR dengan ID ini tidak terdaftar dalam pangkalan data resmi Basarnas KPP Kendari.',
                'rawView' => $rawView,
                'rawToken' => $cleanToken,
                'docId' => $docId,
            ];
        }

        if ($cleanToken !== null) {
            if (!BraModel::verifyToken($bra, $cleanToken)) {
                return [
                    'ok' => false,
                    'bra' => null,
                    'status' => 'INVALID_TOKEN',
                    'message' => 'Tanda tangan digital atau token verifikasi tidak valid. Dokumen diduga mengalami manipulasi URL atau tidak sah.',
                    'rawView' => $rawView,
                    'rawToken' => $cleanToken,
                    'docId' => $docId,
                ];
            }
            return [
                'ok' => true,
                'bra' => $bra,
                'status' => 'VERIFIED_SIGNATURE',
                'message' => '',
                'rawView' => $rawView,
                'rawToken' => $cleanToken,
                'docId' => $docId,
            ];
        }

        if ($requireToken) {
            return [
                'ok' => false,
                'bra' => null,
                'status' => 'INVALID_TOKEN',
                'message' => 'Token verifikasi tidak disertakan. Pindai ulang Barcode QR pada naskah resmi untuk memverifikasi keabsahan dokumen.',
                'rawView' => $rawView,
                'rawToken' => null,
                'docId' => $docId,
            ];
        }

        return [
            'ok' => true,
            'bra' => $bra,
            'status' => 'LEGACY_VERIFIED',
            'message' => '',
            'rawView' => $rawView,
            'rawToken' => null,
            'docId' => $docId,
        ];
    }

    /**
     * Render halaman verifikasi gagal (HTTP 404, zero-leakage).
     */
    protected function renderVerifyInvalid(string $rawView, ?string $rawToken, string $verificationStatus, string $verificationMessage): void {
        http_response_code(404);
        $invalidRef = htmlspecialchars($rawView !== '' ? $rawView : '-', ENT_QUOTES, 'UTF-8');
        $invalidToken = $rawToken !== null ? htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') : null;

        $this->render('bra/verify_invalid', [
            'invalidRef' => $invalidRef,
            'invalidToken' => $invalidToken,
            'verificationStatus' => $verificationStatus,
            'verificationMessage' => $verificationMessage,
        ], [
            'layout' => 'public',
            'title' => 'Verifikasi Dokumen Tidak Sah',
        ]);
    }

    /**
     * Render dokumen BRA resmi ke stream PDF vektor via Chrome Headless.
     */
    protected function outputBraPdf(array $bra, ?string $rawToken = null): void {
        $verifyUrl = BraModel::generateVerifyUrl($bra);
        $pdfDownloadUrl = $rawToken !== null
            ? BraModel::generatePublicDocUrl($bra, $rawToken) . '&format=pdf'
            : BASE_URL . '/bra.php?view=' . (int)$bra['id_bra'] . '&format=pdf';

        ob_start();
        require VIEWS_DIR . '/bra/public_print.php';
        $html = (string)ob_get_clean();

        $pdfBytes = BraPdfService::renderHtmlToPdf($html);
        if ($pdfBytes !== null) {
            $rawNo = (string)($bra['no_bra'] ?? 'BRA_SAR');
            $safeNo = preg_replace('/[^a-zA-Z0-9_\-.]+/', '_', $rawNo);
            $cleanNo = trim((string)$safeNo, '_');
            $safeName = 'BRA-SAR-' . ($cleanNo !== '' ? $cleanNo : 'DOKUMEN');
            if (!headers_sent()) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $safeName . '.pdf"');
                header('Content-Length: ' . (string)strlen($pdfBytes));
                header('Cache-Control: no-store, no-cache, must-revalidate');
            }
            echo $pdfBytes;
            exit;
        }

        http_response_code(503);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'ENGINE_UNAVAILABLE']);
        exit;
    }

    /**
     * Halaman khusus verifikasi QR Berita Radio SAR (terpisah dari lembar naskah).
     *
     * @param mixed $rawId
     */
    protected function verifyDocument(mixed $rawId): void {
        $rawToken = isset($_GET['token']) ? (string)$_GET['token'] : null;
        $access = $this->resolveBraAccess($rawId, $rawToken, true);

        if (!$access['ok'] || !$access['bra']) {
            $this->renderVerifyInvalid($access['rawView'], $access['rawToken'], $access['status'], $access['message']);
            return;
        }

        $bra = $access['bra'];
        $docHash = BraModel::generateVerifyToken($bra);
        $publicDocUrl = BraModel::generatePublicDocUrl($bra, $access['rawToken']);

        $this->render('bra/verify_public', [
            'bra' => $bra,
            'docHash' => $docHash,
            'publicDocUrl' => $publicDocUrl,
            'verificationStatus' => $access['status'],
        ], [
            'layout' => 'public',
            'title' => 'Verifikasi Berita Radio SAR',
        ]);
    }

    /**
     * Halaman khusus melihat dokumen BRA publik read-only (hanya lembar telegram + Unduh/Cetak PDF).
     *
     * @param mixed $rawId
     */
    protected function publicDocument(mixed $rawId): void {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: no-store');
        }

        $rawToken = isset($_GET['token']) ? (string)$_GET['token'] : null;
        $access = $this->resolveBraAccess($rawId, $rawToken, true);

        if (!$access['ok'] || !$access['bra']) {
            $this->renderVerifyInvalid($access['rawView'], $access['rawToken'], $access['status'], $access['message']);
            return;
        }

        $bra = $access['bra'];
        if (($_GET['format'] ?? '') === 'pdf') {
            $this->outputBraPdf($bra, $access['rawToken']);
            return;
        }

        $verifyUrl = BraModel::generateVerifyUrl($bra);
        $pdfDownloadUrl = BraModel::generatePublicDocUrl($bra, $access['rawToken']) . '&format=pdf';
        require VIEWS_DIR . '/bra/public_print.php';
    }

    /**
     * Render internal BRA document viewer, PDF vector export, or backward-compatible legacy QR redirect.
     *
     * @param mixed $viewId
     */
    protected function showDocument(mixed $viewId): void {
        $isGuest = !AuthHelper::isLoggedIn();
        $rawToken = isset($_GET['token']) ? trim((string)$_GET['token']) : null;
        if ($rawToken === '') {
            $rawToken = null;
        }

        // 1. Unduh PDF Vektor (?view=ID&format=pdf atau ?view=ID&token=...&format=pdf)
        if (($_GET['format'] ?? '') === 'pdf') {
            $access = $this->resolveBraAccess($viewId, $rawToken, $isGuest);
            if (!$access['ok'] || !$access['bra']) {
                $this->renderVerifyInvalid($access['rawView'], $access['rawToken'], $access['status'], $access['message']);
                return;
            }
            $this->outputBraPdf($access['bra'], $access['rawToken']);
            return;
        }

        // 2. Kompatibilitas penuh QR BRA lama (?view=ID&token=TOKEN): arahkan ke portal verifikasi khusus
        if ($rawToken !== null) {
            $this->verifyDocument($viewId);
            return;
        }

        // 3. Akses internal melihat BRA (wajib login; tamu tanpa token ditolak HTTP 404)
        $access = $this->resolveBraAccess($viewId, null, $isGuest);
        if (!$access['ok'] || !$access['bra']) {
            $this->renderVerifyInvalid($access['rawView'], $access['rawToken'], $access['status'], $access['message']);
            return;
        }

        $bra = $access['bra'];
        $returnCtx = self::resolveReturnContext($_GET);
        $this->render('bra/view', [
            'bra' => $bra,
            'verificationStatus' => $access['status'],
            'verificationMessage' => $access['message'],
            'pageTitle' => 'Naskah Berita Radio SAR',
            'currentRoute' => 'bra',
            'navbarTitle' => 'Lembar Dokumen Berita Radio SAR (BRA)',
            'isGuest' => false,
            'docId' => $access['docId'],
            'backUrl' => $returnCtx['backUrl'],
            'backLabel' => $returnCtx['backLabel'],
            'returnQuery' => $returnCtx['returnQuery'],
        ], [
            'layout' => 'default',
            'route' => 'bra',
            'title' => 'Naskah Berita Radio SAR',
            'navbarTitle' => 'Lembar Dokumen Berita Radio SAR (BRA)',
        ]);
    }

    /**
     * Save new or updated BRA document.
     */
    protected function save(): void {
        $this->requireLogin();
        $returnCtx = self::resolveReturnContext(!empty($_POST['return_to']) ? $_POST : $_GET);
        $returnQuery = $returnCtx['returnQuery'];

        if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
            $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa.');
            $this->redirect($returnCtx['backUrl']);
        }

        $id = (int)($_POST['id_bra'] ?? $_GET['id'] ?? 0);
        try {
            if ($id > 0) {
                BraModel::update($id, $_POST);
                $this->setFlash('success', 'Naskah Berita Radio SAR berhasil diperbarui!');
                $this->redirect("/bra.php?view={$id}{$returnQuery}");
            } else {
                $newId = BraModel::create($_POST);
                $this->setFlash('success', 'Naskah Berita Radio SAR berhasil diterbitkan!');
                $this->redirect("/bra.php?view={$newId}{$returnQuery}");
            }
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal menyimpan naskah BRA: ' . $e->getMessage());
            $this->redirect('/bra.php' . ($id > 0 ? "?action=edit&id={$id}{$returnQuery}" : "?action=create{$returnQuery}"));
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
        $returnCtx = self::resolveReturnContext($_GET);

        $this->render('bra/form', [
            'action' => $action,
            'pageTitle' => $pageTitle,
            'navbarTitle' => $navbarTitle,
            'bra' => $bra,
            'id' => $id,
            'backUrl' => $returnCtx['backUrl'],
            'backLabel' => $returnCtx['backLabel'],
            'returnTo' => $returnCtx['returnTo'],
            'returnOp' => $returnCtx['returnOp'],
            'returnQuery' => $returnCtx['returnQuery'],
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
