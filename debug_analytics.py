import os

file_path = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\viewer.js'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Add a console.log to our tracking code to help the user debug in their console
old_code = """        if (typeof gtag === 'function') {
            gtag('event', 'velg_kartlag', {
                'kartlag_navn': e.name
            });
        }"""

new_code = """        console.log("GA Event Trigger - Layer Add:", e.name);
        if (typeof gtag === 'function') {
            gtag('event', 'velg_kartlag', {
                'kartlag_navn': e.name
            });
        }"""

if old_code in content:
    new_content = content.replace(old_code, new_code)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Added console.log for debugging.")
else:
    print("Could not find tracking code to modify.")
