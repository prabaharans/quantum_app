<?php

declare(strict_types=1);

namespace QuantumApp\Controller;

/**
 * HomeController — renders the main dashboard UI view.
 */
class HomeController
{
    /**
     * Render the dashboard HTML page.
     */
    public function index(): void
    {
        $viewPath = dirname(__DIR__, 2) . '/views/dashboard.php';
        if (!file_exists($viewPath)) {
            http_response_code(500);
            echo 'View not found: views/dashboard.php';
            return;
        }
        require $viewPath;
    }
}
