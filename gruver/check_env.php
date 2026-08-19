<?php
header('Content-Type: text/plain');
echo "--- ENVIRONMENT CHECK ---\n\n";

echo "PHP User: " . get_current_user() . "\n";
echo "Safe Mode: " . (ini_get('safe_mode') ? 'ON' : 'OFF') . "\n";
echo "Disabled Functions: " . ini_get('disable_functions') . "\n\n";

echo "--- SHELL CHECK ---\n";
exec("pwd 2>&1", $out_pwd);
echo "pwd: " . implode("\n", $out_pwd) . "\n";
exec("ls -F 2>&1", $out_ls);
echo "ls -F: " . implode("\n", $out_ls) . "\n";

$pythons = ['python', 'python3', '/usr/bin/python3', '/usr/local/bin/python3'];
foreach ($pythons as $py) {
    exec("$py --version 2>&1", $out, $ret);
    echo "$py version: " . ($ret === 0 ? implode("\n", $out) : "FAILED") . "\n";
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

echo "\n--- FIND COMMAND ---\n";
exec("find /usr/bin -name 'python*' 2>&1", $out5);
echo "find /usr/bin: " . implode("\n", array_slice($out5, 0, 10)) . "\n";
exec("find /usr/local/bin -name 'python*' 2>&1", $out6);
echo "find /usr/local/bin: " . implode("\n", array_slice($out6, 0, 10)) . "\n";

echo "\n--- PYTHON PACKAGES ---\n";
exec("python3 -m pip list 2>&1", $out, $ret);
echo implode("\n", $out) . "\n";

echo "\n--- FOLDER PERMISSIONS ---\n";
$folders = ['data', 'data/trening', 'data/funn', 'scripts/ai'];
foreach ($folders as $f) {
    echo "$f: " . (is_writable($f) ? "WRITABLE" : "NOT WRITABLE") . " (" . substr(sprintf('%o', fileperms($f)), -4) . ")\n";
}
?>