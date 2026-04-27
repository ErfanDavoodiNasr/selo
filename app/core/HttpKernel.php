<?php
declare(strict_types=1);

namespace App\Core;

class HttpKernel
{
    public static function handleApi(array $config, string $path): void
    {
        LogContext::setIsApi(true);
        Logger::info('request_start', [], 'api');

        require BASE_PATH . '/app/routes.php';
    }
}
