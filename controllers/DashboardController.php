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
        $activeOperations = array_slice(OperasiModel::getListSimple('OPEN'), 0, 3);
        $appSettings = SettingsModel::getSettings();
        $currentUser = AuthHelper::getCurrentUser();
        $isAdmin = AuthHelper::isAdmin();

        $this->render('dashboard', [
            'stats' => $stats,
            'activeOperations' => $activeOperations,
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
