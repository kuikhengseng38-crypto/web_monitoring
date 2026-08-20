<?php

function telegram_credentials(): array
{
    $token = setting('telegram_bot_token', TELEGRAM_BOT_TOKEN);
    $chatId = setting('telegram_chat_id', TELEGRAM_CHAT_ID);

    $token = trim((string) $token);
    $token = preg_replace('/^bot/i', '', $token);
    $chatId = trim((string) $chatId);

    return [$token, $chatId];
}

function telegram_last_error(?string $message = null): string
{
    if ($message !== null) {
        setting_set('telegram_last_error', $message);
        return $message;
    }
    return (string) setting('telegram_last_error', '');
}

function telegram_http_post(string $url, string $payload): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response !== false) {
            return ['ok' => true, 'body' => $response, 'http' => $httpCode];
        }
        if ($error) {
            return ['ok' => false, 'error' => 'cURL: ' . $error];
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['ok' => false, 'error' => 'HTTP request to Telegram failed (no cURL/SSL).'];
    }

    return ['ok' => true, 'body' => $response, 'http' => 0];
}

function telegram_send(string $text): bool
{
    return telegram_send_result($text)['ok'];
}

function telegram_send_result(string $text): array
{
    [$token, $chatId] = telegram_credentials();

    if ($token === '' || $chatId === '') {
        $msg = 'Bot token or Chat ID is empty. Save them in Settings.';
        telegram_last_error($msg);
        return ['ok' => false, 'error' => $msg];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ]);

    $http = telegram_http_post($url, $payload);
    if (empty($http['ok'])) {
        $msg = $http['error'] ?? 'Unknown Telegram send error';
        telegram_last_error($msg);
        return ['ok' => false, 'error' => $msg];
    }

    $json = json_decode((string) $http['body'], true);
    if (!empty($json['ok'])) {
        telegram_last_error('');
        return ['ok' => true, 'error' => ''];
    }

    $apiError = $json['description'] ?? ('Telegram API error HTTP ' . ($http['http'] ?? ''));
    telegram_last_error($apiError);
    return ['ok' => false, 'error' => $apiError];
}

function telegram_status_dot(string $status): string
{
    $status = strtoupper(trim($status));
    if ($status === 'UP') {
        return '🟢';
    }
    if ($status === 'DOWN') {
        return '🔴';
    }
    if ($status === 'SLOW') {
        return '🟡';
    }
    return '⚪';
}

function telegram_status_label(string $status): string
{
    $status = strtoupper(trim($status));
    return telegram_status_dot($status) . ' ' . ($status !== '' ? $status : 'UNKNOWN');
}

function telegram_alert_message(
    string $type,
    array $website,
    string $status,
    ?int $responseTime,
    string $checkedAt,
    string $previousStatus = ''
): string {
    $dot = telegram_status_dot($status);
    $title = $dot . ' ALERT: Website DOWN';
    if ($type === 'RECOVERY') {
        $title = $dot . ' RECOVERY: Website back UP';
    } elseif ($type === 'SLOW') {
        $title = $dot . ' WARNING: Slow response detected';
    } elseif ($type === 'STATUS') {
        $title = $dot . ' STATUS: Website is ' . $status;
    }

    $ms = (!$responseTime) ? 'N/A' : $responseTime . ' ms';
    $http = isset($website['http_code']) && $website['http_code'] ? ('HTTP ' . $website['http_code']) : '';
    $changeLine = $previousStatus !== ''
        ? 'Changed: ' . telegram_status_label($previousStatus) . ' → ' . telegram_status_label($status) . "\n"
        : '';

    return $title . "\n"
        . "Website: " . $website['name'] . "\n"
        . "URL: " . $website['url'] . "\n"
        . $changeLine
        . "Status: " . telegram_status_label($status) . ($http ? " ($http)" : '') . "\n"
        . "Response time: " . $ms . "\n"
        . "Time: " . $checkedAt;
}
