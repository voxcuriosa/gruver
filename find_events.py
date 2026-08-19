import os

file_path = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\viewer.js'
search_terms = ['vis_gruve', 'velg_flyfoto', 'bruk_verktoy', 'gtag']

with open(file_path, 'r', encoding='utf-8') as f:
    for line_num, line in enumerate(f, 1):
        for term in search_terms:
            if term in line:
                print(f"Line {line_num}: {line.strip()}")
