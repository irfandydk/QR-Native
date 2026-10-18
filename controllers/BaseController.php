<?php
/**
 * QR-SAR - Web Base Controller
 * KPP Kendari - Basarnas
 *
 * Provides standardized foundation for web controllers including:
 * - Authentication & authorization verification
 * - View rendering delegation
 * - Safe redirection and flash notification helpers
 * - CSRF token verification
 */

declare(strict_types=1);

abstract class BaseController {
    /**
     * Enforce active user session authentication.
     */
    protected function requireLogin(): void {
        AuthHelper::requireLogin();
    }

    /**
     * Enforce administrator privileges.
     */
    protected function requireAdmin(): void {
        AuthHelper::requireAdmin();
    }

    /**
     * Render canonical template view with parameters and options.
     *
     * @param string $view
     * @param array $data
     * @param array $options
     */
    protected function render(string $view, array $data = [], array $options = []): void {
        renderView($view, $data, $options);
    }

    /**
     * Redirect to relative or absolute application URL.
     *
     * @param string $path
     */
    protected function redirect(string $path): void {
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $path = BASE_URL . '/' . ltrim($path, '/');
        }
        header("Location: {$path}");
        exit;
    }

    /**
     * Validate CSRF token from POST or provided string.
     *
     * @param string|null $token
     * @return bool
     */
    protected function validateCsrf(?string $token = null): bool {
        return validateCsrf($token ?? ($_POST['csrf_token'] ?? null));
    }

    /**
     * Set user-facing flash alert.
     *
     * @param string $type
     * @param string $message
     */
    protected function setFlash(string $type, string $message): void {
        setFlash($type, $message);
    }
}
