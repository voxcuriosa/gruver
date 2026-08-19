<?php
header("Content-Type: text/plain");
echo "Server Public IP: " . file_get_contents('https://api.ipify.org') . "\n";
echo "Server Internal IP: " . $_SERVER['SERVER_ADDR'] . "\n";
?>