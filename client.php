<?php
require __DIR__ . "/PhpCached.php";
if (!extension_loaded('posix')) dl('posix.' . PHP_SHLIB_SUFFIX);

$server_addr = '127.0.0.1';
$Cached = new PhpCached();

if ($argv[1] == 'get') {
    echo $Cached->get($server_addr, $argv[2]) . "\n";
} else if ($argv[1] == 'put') {
    var_dump($Cached->put($server_addr, $argv[2], $argv[3]));
} else if ($argv[1] == 'del') {
    var_dump($Cached->del($server_addr, $argv[2]));
} else {
    echo "Usage: client.php get <key>\n";
    echo "Usage: client.php put <key> <data>\n";
    echo "Usage: client.php del <key>\n";
}
