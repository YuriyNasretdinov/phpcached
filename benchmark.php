<?php
require __DIR__ . "/PhpCached.php";
if (!extension_loaded('posix')) dl('posix.' . PHP_SHLIB_SUFFIX);

$server_addr = '127.0.0.1';

$num = isset($argv[1]) ? intval($argv[1]) : 10000;

$start = microtime(true);
for ($i = 0; $i < $num; $i++) {
    $Cached = new PhpCached();
    $Cached->put($server_addr, "key$i", md5($i));
    if ($i % 1000 == 999) echo "Done $i\n";
}
$time = microtime(true) - $start;
echo "Insertions took " . round($time * 1000) . "ms, " . round($num / $time) . " rps\n";
