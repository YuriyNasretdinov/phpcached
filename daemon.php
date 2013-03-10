<?php
require __DIR__ . "/PhpCached.php";
if (!extension_loaded('pcntl')) dl('pcntl.' . PHP_SHLIB_SUFFIX);
if (!extension_loaded('posix')) dl('posix.' . PHP_SHLIB_SUFFIX);

$parent_pid = posix_getpid();

while (true) {
    $pid = pcntl_fork();
    if ($pid < 0) {
        fwrite(STDERR, "$parent_pid: Error: Cannot fork\n");
        exit(1);
    }

    if ($pid == 0) {
        try {
            $Cached = new PhpCached();
            $Cached->runDaemon();
        } catch (PhpCachedException $e) {
            fwrite(STDERR, posix_getpid() . ": " . $e->getMessage() . "\n");
            exit(1);
        }
        exit(0);
    }

    fwrite(STDERR, "$parent_pid: Spawned child with pid $pid\n");

    if (pcntl_wait($status) < 0) {
        fwrite(STDERR, "$parent_pid: Error: pcntl_wait failed\n");
        exit(1);
    }

    $exit_status = pcntl_wexitstatus($status);

    fwrite(STDERR, "$parent_pid: Child died with exit status $exit_status\n");
}
