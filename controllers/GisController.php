<?php
/**
 * QR-SAR - Tactical GIS Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Handles maritime GIS tactical map plotting, layer controls, and operational spatial visualization.
 */

declare(strict_types=1);

class GisController extends BaseController {
    /**
     * Display tactical GIS page.
     */
    public function index(): void {
        $this->requireLogin();

        $this->render('gis', [], [
            'title' => 'Tactical Maritime GIS & Map Plotting',
            'route' => 'gis',
            'useGis' => true,
            'navbarTitle' => 'Plotting Taktis Operasi & Wilayah Kerja SAR Kendari'
        ]);
    }
}
