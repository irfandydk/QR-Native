<?php
/**
 * QR-SAR - Centralized View Rendering Helper
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Standarisasi layout rendering untuk seluruh controller (Header, Sidebar, Navbar, Content, Footer)
 */

declare(strict_types=1);

class ViewHelper
{
    /**
     * Render a view within an optional layout wrapper.
     *
     * @param string $view Relative view name (e.g. 'dashboard', 'operasi_form', 'admin', 'errors/500')
     * @param array<string, mixed> $data Variables to extract into the view's scope
     * @param array<string, mixed> $options Layout options:
     *        - 'layout': 'default' | 'public' | 'none' (default: 'default')
     *        - 'title': Page title (sets $pageTitle)
     *        - 'route': Route identifier for navigation highlight (sets $currentRoute)
     *        - 'navbarTitle': Navbar title text (sets $navbarTitle)
     *        - 'navbarSubtitle': Navbar subtitle text (sets $navbarSubtitle)
     */
    /**
     * Resolve view file path with support for both modular subdirectories and legacy flat aliases
     */
    public static function resolve(string $view): string
    {
        return self::resolveViewFile($view);
    }

    public static function resolveViewFile(string $view): string
    {
        if (!str_ends_with($view, '.php')) {
            $view .= '.php';
        }

        $clean = ltrim($view, '/');
        if (str_starts_with($clean, 'views/')) {
            $clean = substr($clean, 6);
        }

        // 1. Domain alias mapping for flat legacy view names (Prefer canonical modular views)
        $aliasMap = [
            'operasi_detail.php'     => 'operasi/detail.php',
            'operasi_form.php'       => 'operasi/form.php',
            'renops_form.php'        => 'operasi/renops_form.php',
            'sitrep_form.php'        => 'operasi/sitrep_form.php',
            'bra_form.php'           => 'bra/form.php',
            'bra_list.php'           => 'bra/list.php',
            'bra_view.php'           => 'bra/view.php',
            'bra_verify_invalid.php' => 'bra/verify_invalid.php',
            'master.php'             => 'master/index.php',
            'master_settings.php'    => 'admin/index.php',
            'admin.php'              => 'admin/index.php',
            'login.php'              => 'auth/login.php',
        ];

        if (isset($aliasMap[$clean])) {
            $mapped = VIEWS_DIR . '/' . $aliasMap[$clean];
            if (file_exists($mapped)) {
                return $mapped;
            }
        }

        // 2. Direct path check (e.g. 'operasi/detail.php' or 'dashboard.php')
        $direct = VIEWS_DIR . '/' . $clean;
        if (file_exists($direct)) {
            return $direct;
        }

        throw new RuntimeException("Berkas view tidak ditemukan: {$direct}");
    }

    public static function render(string $view, array $data = [], array $options = []): void
    {
        $viewFile = self::resolveViewFile($view);

        $layout = $options['layout'] ?? ($data['layout'] ?? 'default');

        // Map options to standard layout variables if not explicitly provided in $data
        if (isset($options['title']) && !isset($data['pageTitle'])) {
            $data['pageTitle'] = $options['title'];
        }
        if (isset($options['route']) && !isset($data['currentRoute'])) {
            $data['currentRoute'] = $options['route'];
        }
        if (isset($options['navbarTitle']) && !isset($data['navbarTitle'])) {
            $data['navbarTitle'] = $options['navbarTitle'];
        }
        if (isset($options['navbarSubtitle']) && !isset($data['navbarSubtitle'])) {
            $data['navbarSubtitle'] = $options['navbarSubtitle'];
        }
        if (isset($options['useGis']) && !isset($data['useGis'])) {
            $data['useGis'] = (bool)$options['useGis'];
        }
        if (isset($options['useQr']) && !isset($data['useQr'])) {
            $data['useQr'] = (bool)$options['useQr'];
        }
        if (isset($options['useBra']) && !isset($data['useBra'])) {
            $data['useBra'] = (bool)$options['useBra'];
        }
        if (isset($options['pageScript']) && !isset($data['pageScript'])) {
            $data['pageScript'] = $options['pageScript'];
        }
        if (isset($options['pageScripts']) && !isset($data['pageScripts'])) {
            $data['pageScripts'] = (array)$options['pageScripts'];
        }

        // Extract variables to local scope
        extract($data, EXTR_OVERWRITE);

        switch ($layout) {
            case 'default':
                require VIEWS_DIR . '/layout/header.php';
                require VIEWS_DIR . '/layout/sidebar.php';
                require VIEWS_DIR . '/layout/navbar.php';
                require $viewFile;
                require VIEWS_DIR . '/layout/footer.php';
                break;

            case 'public':
                require VIEWS_DIR . '/layout/public_header.php';
                require $viewFile;
                require VIEWS_DIR . '/layout/public_footer.php';
                break;

            case 'none':
                require $viewFile;
                break;

            default:
                throw new InvalidArgumentException("Tata letak (layout) tidak dikenal: {$layout}");
        }
    }
}

/**
 * Global procedural shortcut for ViewHelper::render
 */
if (!function_exists('renderView')) {
    function renderView(string $view, array $data = [], array $options = []): void
    {
        ViewHelper::render($view, $data, $options);
    }
}
