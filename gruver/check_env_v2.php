<?php
header('Content-Type: text/plain');
echo "--- ENVIRONMENT CHECK V2 ---\n\n";

echo "PHP User: " . get_current_user() . "\n";
echo "Safe Mode: " . (ini_get('safe_mode') ? 'ON' : 'OFF') . "\n";
echo "Disabled Functions: " . ini_get('disable_functions') . "\n\n";

echo "--- PHP EXTENSIONS ---\n";
echo "GD: " . (extension_loaded('gd') ? 'INSTALLED' : 'MISSING') . "\n";
echo "Imagick: " . (extension_loaded('imagick') ? 'INSTALLED' : 'MISSING') . "\n\n";

echo "--- SHELL CHECK ---\n";
exec("pwd 2>&1", $out_pwd);
echo "pwd: " . implode("\n", $out_pwd) . "\n";
exec("ls -F 2>&1", $out_ls);
echo "ls -F: " . implode("\n", $out_ls) . "\n";

echo "\n--- PYTHON VERSION CHECK ---\n";
$pythons = ['python', 'python3', '/usr/bin/python3', '/usr/local/bin/python3', '/usr/bin/python', '/usr/local/bin/python'];
foreach ($pythons as $py) {
    exec("$py --version 2>&1", $out, $ret);
    echo "$py version: " . ($ret === 0 ? implode("\n", $out) : "FAILED (Ret: $ret)") . "\n";
    unset($out);
}

echo "\n--- SEARCHING FOR PYTHON ---\n";
exec("whereis python 2>&1", $out1);
echo "whereis python: " . implode("\n", $out1) . "\n";
exec("whereis python3 2>&1", $out2);
echo "whereis python3: " . implode("\n", $out2) . "\n";
exec("which python 2>&1", $out3);
echo "which python: " . implode("\n", $out3) . "\n";
exec("which python3 2>&1", $out4);
echo "which python3: " . implode("\n", $out4) . "\n";

echo "\n--- FOLDER PERMISSIONS ---\n";
$folders = ['data', 'data/trening', 'data/funn', 'scripts/ai'];
foreach ($folders as $f) {
    echo "$f: " . (is_writable($f) ? "WRITABLE" : "NOT WRITABLE") . " (" . substr(sprintf('%o', fileperms($f)), -4) . ")\n";
}
?>