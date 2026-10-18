<?php
/**
 * QR-SAR - Dashboard Web Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class DashboardController extends BaseController {
    /**
     * Display main operational dashboard.
     */
    public function index(): void {
        $this->requireLogin();

        $this->render('dashboard', [], [
            'title' => 'Dashboard Operasi SAR',
            'route' => 'dashboard',
            'navbarTitle' => 'Dashboard Operasi SAR'
        ]);
    }
}
