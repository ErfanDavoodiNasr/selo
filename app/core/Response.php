<?php
namespace App\Core;

class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $rid = LogContext::getRequestId();
        if ($rid && !headers_sent()) {
            header('X-Request-Id: ' . $rid);
        }
        if (is_array($data)) {
            $shouldAttach = $status >= 400 || (isset($data['ok']) && $data['ok'] === false) || isset($data['error']);
            if ($shouldAttach && !isset($data['request_id']) && $rid) {
                $data['request_id'] = $rid;
            }
            if ($shouldAttach && isset($data['error']) && !isset($data['message'])) {
                $data['message'] = $data['error'];
            }
        }

        if (LogContext::isApi() && !LogContext::isResponseLogged()) {
            Logger::info('request_end', [
                'status' => $status,
                'dur_ms' => LogContext::getDurationMs(),
            ], 'api');
            LogContext::markResponseLogged();
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
