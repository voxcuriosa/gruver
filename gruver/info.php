<?php
header('Content-Type: text/plain');
echo "GD Check: " . (extension_loaded('gd') ? 'FOUND' : 'NOT FOUND') . "\n";
echo "Imagick Check: " . (extension_loaded('imagick') ? 'FOUND' : 'NOT FOUND') . "\n";
print_r(get_loaded_extensions());
?>