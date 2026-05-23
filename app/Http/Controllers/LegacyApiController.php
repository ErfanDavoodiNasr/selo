<?php

namespace App\Http\Controllers;

use App\Core\LogContext;
use App\Core\Logger;
use App\Core\Database;
use Illuminate\Http\Response as LaravelResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class LegacyApiController
{
    public static function call(callable $handler): SymfonyResponse
    {
        global $config;

        if (!is_array($config) || empty($config['installed'])) {
            return response()->json([
                'ok' => false,
                'error' => 'Application is not installed.',
            ], 503);
        }

        LogContext::setIsApi(true);
        Logger::info('request_start', [], 'api');
        Database::init($config);

        ob_start();
        $status = 200;
        try {
            $handler();
            $content = ob_get_clean();
            $status = http_response_code() ?: 200;
        } catch (Throwable $throwable) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw $throwable;
        }

        return new LaravelResponse($content, $status, [
            'Content-Type' => self::contentTypeFromHeaders() ?: 'application/json; charset=UTF-8',
        ]);
    }

    private static function contentTypeFromHeaders(): ?string
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                return trim(substr($header, strlen('Content-Type:')));
            }
        }

        return null;
    }
}
