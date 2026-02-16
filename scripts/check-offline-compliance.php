#!/usr/bin/env php
<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$scanDirs = [
    $repoRoot . '/public',
    $repoRoot . '/app',
    $repoRoot . '/config',
    $repoRoot . '/signaling',
];

$textExtensions = ['php', 'js', 'css', 'html', 'htm', 'json', 'txt'];
$urlPattern = '/\b(?:https?|wss?|stun|turns?):\/\/[^\s"\'\`<>()]+|\b(?:stun|turns?):[^\s"\'\`<>()]+/i';

$violations = [];

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $basename = $fileInfo->getBasename();
        if (strpos($basename, '._') === 0) {
            continue;
        }

        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, $textExtensions, true)) {
            continue;
        }

        $path = $fileInfo->getPathname();
        $relative = relativePath($repoRoot, $path);
        if ($relative === 'config/config.php') {
            continue;
        }
        $content = @file_get_contents($path);
        if (!is_string($content) || $content === '') {
            continue;
        }

        if (!preg_match_all($urlPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        $lines = preg_split('/\R/', $content) ?: [];
        foreach ($matches[0] as $matchData) {
            [$rawMatch, $offset] = $matchData;
            $candidate = normalizeCandidate($rawMatch);
            if ($candidate === '' || isAllowedUrl($candidate)) {
                continue;
            }

            $line = substr_count(substr($content, 0, (int)$offset), "\n") + 1;
            $snippet = $lines[$line - 1] ?? '';
            $violations[] = [
                'file' => $relative,
                'line' => $line,
                'url' => $candidate,
                'snippet' => trim($snippet),
            ];
        }
    }
}

if ($violations) {
    fwrite(STDERR, "Offline compliance check failed. External URLs found:\n\n");
    foreach ($violations as $violation) {
        fwrite(STDERR, sprintf(
            "%s:%d\n  URL: %s\n  Snippet: %s\n\n",
            $violation['file'],
            $violation['line'],
            $violation['url'],
            $violation['snippet']
        ));
    }
    exit(1);
}

echo "Offline compliance check passed. No external internet URLs found in scanned source files.\n";
exit(0);

function normalizeCandidate(string $value): string
{
    $candidate = trim($value);
    $candidate = preg_replace('/["\'\`),.;]+$/', '', $candidate) ?? $candidate;
    return $candidate;
}

function isAllowedUrl(string $candidate): bool
{
    $lower = strtolower($candidate);
    if (preg_match('/^(https?|wss?):\/\/$/', $lower)) {
        return true;
    }

    if (strpos($lower, 'stun:') === 0 || strpos($lower, 'turn:') === 0 || strpos($lower, 'turns:') === 0) {
        $host = extractStunTurnHost($candidate);
        if ($host === '') {
            return true;
        }
        return isLocalHost($host);
    }

    $parts = @parse_url($candidate);
    if (!is_array($parts) || empty($parts['host'])) {
        return true;
    }

    return isLocalHost((string)$parts['host']);
}

function extractStunTurnHost(string $candidate): string
{
    $value = preg_replace('/^(stun|turns?):/i', '', $candidate);
    if (!is_string($value)) {
        return '';
    }
    $value = ltrim($value, '/');
    if ($value === '') {
        return '';
    }

    $hostPart = preg_split('/[/?]/', $value, 2)[0] ?? '';
    if ($hostPart === '') {
        return '';
    }

    if ($hostPart[0] === '[') {
        $end = strpos($hostPart, ']');
        if ($end !== false) {
            return substr($hostPart, 1, $end - 1);
        }
    }

    $segments = explode(':', $hostPart);
    return $segments[0] ?? '';
}

function isLocalHost(string $host): bool
{
    $host = trim($host, "[]");
    $lower = strtolower($host);

    if ($lower === '' || $lower === 'localhost' || $lower === '127.0.0.1' || $lower === '::1') {
        return true;
    }

    if (endsWith($lower, '.local') || endsWith($lower, '.lan') || endsWith($lower, '.internal')) {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return isPrivateIpv4($host);
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return isPrivateIpv6($host);
    }

    return false;
}

function isPrivateIpv4(string $ip): bool
{
    $parts = array_map('intval', explode('.', $ip));
    if (count($parts) !== 4) {
        return false;
    }

    if ($parts[0] === 10 || $parts[0] === 127) {
        return true;
    }
    if ($parts[0] === 192 && $parts[1] === 168) {
        return true;
    }
    if ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31) {
        return true;
    }
    if ($parts[0] === 169 && $parts[1] === 254) {
        return true;
    }

    return false;
}

function isPrivateIpv6(string $ip): bool
{
    $packed = @inet_pton($ip);
    if ($packed === false || strlen($packed) !== 16) {
        return false;
    }

    if ($ip === '::1') {
        return true;
    }

    $firstByte = ord($packed[0]);
    $secondByte = ord($packed[1]);

    // fc00::/7 (ULA)
    if (($firstByte & 0xfe) === 0xfc) {
        return true;
    }

    // fe80::/10 (link-local)
    if ($firstByte === 0xfe && ($secondByte & 0xc0) === 0x80) {
        return true;
    }

    return false;
}

function relativePath(string $base, string $path): string
{
    $base = rtrim(str_replace('\\', '/', $base), '/');
    $path = str_replace('\\', '/', $path);
    if (strpos($path, $base . '/') === 0) {
        return substr($path, strlen($base) + 1);
    }
    return $path;
}

function endsWith(string $value, string $suffix): bool
{
    $suffixLength = strlen($suffix);
    if ($suffixLength === 0) {
        return true;
    }
    return substr($value, -$suffixLength) === $suffix;
}
