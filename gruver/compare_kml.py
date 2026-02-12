
import re
import os

def clean_name(name):
    # Remove CDATA
    name = re.sub(r'<!\[CDATA\[(.*?)\]\]>', r'\1', name)
    # Remove HTML tags
    name = re.sub(r'<.*?>', '', name)
    # Trim and normalize whitespace
    return ' '.join(name.split()).strip()

def extract_placemarks(kml_file):
    try:
        with open(kml_file, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"Error reading {kml_file}: {e}")
        return []

    placemarks = re.findall(r'<Placemark>(.*?)</Placemark>', content, re.DOTALL)
    data = {}
    for pm in placemarks:
        name_match = re.search(r'<name>(.*?)</name>', pm)
        raw_name = name_match.group(1) if name_match else "Ukjent"
        name = clean_name(raw_name)
        
        # Extract images
        img_urls = re.findall(r'src="(https?://[^">]+)"', pm)
        img_urls.extend(re.findall(r'<Data name="gx_media_links">\s*<value>(.*?)</value>', pm, re.DOTALL))
        
        # Extract coords for more robust matching
        coords_match = re.search(r'<coordinates>(.*?)</coordinates>', pm)
        coords = coords_match.group(1).strip().split(',')[0:2] if coords_match else ["0", "0"]
        
        # Use name + coords as key to avoid duplicates or misidentifications
        # but for simple name check, let's keep name as key
        if name not in data:
            data[name] = {'raw': raw_name, 'images': set(), 'coords': coords}
        
        for url in img_urls:
            data[name]['images'].add(url.strip())
            
    return data

full_data = extract_placemarks('full_data.kml')
remote_data = extract_placemarks('remote_map.kml')

full_names = set(full_data.keys())
remote_names = set(remote_data.keys())

missing_in_full = remote_names - full_names
extra_in_full = full_names - remote_names
common_names = remote_names & full_names

print(f"Total Unique Names in full_data.kml: {len(full_names)}")
print(f"Total Unique Names in remote_map.kml: {len(remote_names)}")

print(f"\nMissing in full_data.kml (New in Google Map) ({len(missing_in_full)}):")
for name in sorted(list(missing_in_full))[:20]:
    print(f" - {name}")
if len(missing_in_full) > 20:
    print(f" ... and {len(missing_in_full)-20} more")

print(f"\nExtra in full_data.kml (Exists locally BUT NOT in Google Map) ({len(extra_in_full)}):")
for name in sorted(list(extra_in_full))[:20]:
    print(f" - {name}")
if len(extra_in_full) > 20:
    print(f" ... and {len(extra_in_full)-20} more")

# Check for image updates in common points
updates = 0
for name in common_names:
    if remote_data[name]['images'] - full_data[name]['images']:
        updates += 1
print(f"\nPoints found in both but with NEW images in Google Map: {updates}")
