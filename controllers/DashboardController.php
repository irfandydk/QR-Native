<?php
/**
 * QR-SAR - Dashboard Web Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class DashboardController extends BaseController {
    /**
     * Display main command-landing dashboard with live operational overview,
     * workflow guide, core features, system advantages, and operational benefits.
     */
    public function index(): void {
        $this->requireLogin();

        $stats = OperasiModel::getStats();
        $activeOperations = OperasiModel::getListSimple('OPEN', 3);
        $dokStats = class_exists('OperasiDokumentasiModel')
            ? OperasiDokumentasiModel::getArchiveStats()
            : ['total' => 0, 'umum' => 0, 'operasi' => 0, 'image' => 0, 'video' => 0];
        $appSettings = SettingsModel::getSettings();
        $currentUser = AuthHelper::getCurrentUser();
        $isAdmin = AuthHelper::isAdmin();

        $this->render('dashboard', [
            'stats' => $stats,
            'activeOperations' => $activeOperations,
            'dokStats' => $dokStats,
            'appSettings' => $appSettings,
            'currentUser' => $currentUser,
            'isAdmin' => $isAdmin,
        ], [
            'title' => 'Dashboard QR-SAR',
            'route' => 'dashboard',
            'navbarTitle' => 'Dashboard QR-SAR',
        ]);
    }
}
