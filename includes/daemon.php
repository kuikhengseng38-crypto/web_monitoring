<?php

function monitor_storage_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function monitor_pid_path(): string
{
    return monitor_storage_dir() . DIRECTORY_SEPARATOR . 'watch.pid';
}

function monitor_lock_path(): string
{
    return monitor_storage_dir() . DIRECTORY_SEPARATOR . 'watch.lock';
}

function monitor_log_path(): string
{
    return monitor_storage_dir() . DIRECTORY_SEPARATOR . 'watch.log';
}

function monitor_daemon_pid(): int
{
    $path = monitor_pid_path();
    if (!is_file($path)) {
        return 0;
    }
    return (int) trim((string) @file_get_contents($path));
}

function monitor_pid_is_running(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $out = [];
        @exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH', $out);
        $line = strtolower(trim(implode(' ', $out)));
        if ($line === '' || str_contains($line, 'no tasks') || str_contains($line, 'info:')) {
            return false;
        }
        return str_contains($line, ',' . $pid . ',')
            || str_contains($line, '"' . $pid . '"')
            || preg_match('/\b' . $pid . '\b/', $line) === 1;
    }

    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    return is_dir('/proc/' . $pid);
}

function monitor_daemon_is_alive(): bool
{
    return monitor_pid_is_running(monitor_daemon_pid());
}

function php_cli_binary(): string
{
    $bin = PHP_BINARY;
    if (PHP_OS_FAMILY !== 'Windows') {
        return $bin;
    }

    $dir = dirname($bin);
    foreach (['php-win.exe', 'php.exe'] as $name) {
        $candidate = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return $bin;
}

function vbs_quote(string $value): string
{
    return '"' . str_replace('"', '""', $value) . '"';
}

function monitor_daemon_start(): bool
{
    if (monitor_daemon_is_alive()) {
        return true;
    }

    $root = dirname(__DIR__);
    $bin = php_cli_binary();
    $script = $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'watch.php';
    $log = monitor_log_path();

    if (!is_file($script)) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        return monitor_daemon_start_windows($root, $bin, $script);
    }

    $cmd = sprintf(
        'nohup %s %s >> %s 2>&1 &',
        escapeshellarg($bin),
        escapeshellarg($script),
        escapeshellarg($log)
    );
    @exec($cmd);
    return true;
}

function monitor_daemon_start_windows(string $root, string $bin, string $script): bool
{
    $command = '"' . $bin . '" "' . $script . '"';

    if (class_exists('COM', false)) {
        try {
            $wsh = new COM('WScript.Shell');
            $wsh->CurrentDirectory = $root;
            $wsh->Run($command, 0, false);
            return true;
        } catch (Throwable $e) {
            // Fall through to wscript.
        }
    }

    $vbs = monitor_storage_dir() . DIRECTORY_SEPARATOR . 'start_watch.vbs';
    $body = "Set sh = CreateObject(\"WScript.Shell\")\r\n"
        . 'sh.CurrentDirectory = ' . vbs_quote($root) . "\r\n"
        . 'sh.Run ' . vbs_quote($command) . ", 0, False\r\n";
    @file_put_contents($vbs, $body);

    $wscript = (getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\wscript.exe';
    if (!is_file($wscript)) {
        $wscript = 'wscript.exe';
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open(
        [$wscript, '//B', '//nologo', $vbs],
        $descriptors,
        $pipes,
        $root,
        null,
        ['bypass_shell' => true]
    );

    if (is_resource($proc)) {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($proc);
        return true;
    }

    @pclose(@popen('"' . $wscript . '" //B //nologo "' . $vbs . '"', 'r'));
    return true;
}

function monitor_daemon_ensure(): void
{
    if (defined('MONITOR_DAEMON_PROCESS')) {
        return;
    }
    if (monitor_daemon_is_alive()) {
        return;
    }

    $gate = monitor_storage_dir() . DIRECTORY_SEPARATOR . 'start.lock';
    $fh = @fopen($gate, 'c');
    if ($fh && !flock($fh, LOCK_EX | LOCK_NB)) {
        fclose($fh);
        return;
    }

    try {
        if (!monitor_daemon_is_alive()) {
            monitor_daemon_start();
        }
    } finally {
        if ($fh) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
