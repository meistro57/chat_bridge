<?php

$host = '127.0.0.1';
$startPort = 8000;
$endPort = 8100;

function isPortFree($host, $port)
{
    $connection = @fsockopen($host, $port);
    if (is_resource($connection)) {
        fclose($connection);

        return false;
    }

    return true;
}

for ($port = $startPort; $port <= $endPort; $port++) {
    if (isPortFree($host, $port) && isPortFree($host, $port + 1)) {
        echo $port;
        exit(0);
    }
}
exit(1);
