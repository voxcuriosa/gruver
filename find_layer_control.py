import os

file_path = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\viewer.js'

with open(file_path, 'r', encoding='utf-8') as f:
    for line_num, line in enumerate(f, 1):
        if 'layerControl =' in line or 'layerControl=' in line:
            print(f"Line {line_num}: {line.strip()}")
        if 'L.control.groupedLayers' in line:
            print(f"Line {line_num}: {line.strip()}")
