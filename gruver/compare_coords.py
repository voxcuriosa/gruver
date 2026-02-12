
import xml.etree.ElementTree as ET
import re
import os

def clean_name(name):
    if name is None: return ""
    name = re.sub(r'<!\[CDATA\[(.*?)\]\]>', r'\1', name)
    name = re.sub(r'<.*?>', '', name)
    return ' '.join(name.split()).strip()

def extract_placemarks(kml_file):
    try:
        # KML often uses namespaces
        tree = ET.parse(kml_file)
        root = tree.getroot()
        
        # Determine namespace
        ns = ""
        if '}' in root.tag:
            ns = root.tag.split('}')[0] + '}'
        
        data = []
        for pm in root.findall(f".//{ns}Placemark"):
            name_node = pm.find(f"{ns}name")
            name = clean_name(name_node.text if name_node is not None else "")
            
            coords_node = pm.find(f".//{ns}coordinates")
            if coords_node is None:
                continue
                
            raw_coords = coords_node.text.strip()
            if not raw_coords: continue
            
            first_coord = raw_coords.split()[0]
            parts = first_coord.split(',')
            if len(parts) < 2: continue
            
            lon, lat = parts[0].strip(), parts[1].strip()
            
            # Extract images (gx_media_links or description)
            images = set()
            gx_media = pm.find(f".//{ns}Data[@name='gx_media_links']/{ns}value")
            if gx_media is not None and gx_media.text:
                for url in gx_media.text.strip().split():
                    images.add(url.strip())
            
            desc_node = pm.find(f"{ns}description")
            if desc_node is not None and desc_node.text:
                img_urls = re.findall(r'src="(https?://[^">]+)"', desc_node.text)
                for url in img_urls:
                    images.add(url.strip())
            
            data.append({
                'name': name,
                'lat': lat,
                'lon': lon,
                'images': images
            })
        return data
    except Exception as e:
        print(f"Error parsing {kml_file}: {e}")
        return []

full_data = extract_placemarks('full_data.kml')
remote_data = extract_placemarks('remote_map.kml')

def get_coord_key(site):
    try:
        lat = round(float(site['lat']), 6)
        lon = round(float(site['lon']), 6)
        return (lat, lon)
    except:
        return None

full_coords = {}
for i, d in enumerate(full_data):
    key = get_coord_key(d)
    if key:
        full_coords[key] = d

remote_coords = {}
for i, d in enumerate(remote_data):
    key = get_coord_key(d)
    if key:
        remote_coords[key] = d

matches = 0
renames = []
new_points = []
for key, r_site in remote_coords.items():
    if key in full_coords:
        f_site = full_coords[key]
        matches += 1
        if r_site['name'] != f_site['name']:
            renames.append((f_site['name'], r_site['name'], key))
    else:
        new_points.append(r_site)

deleted_in_remote = []
for key, f_site in full_coords.items():
    if key not in remote_coords:
        deleted_in_remote.append(f_site)

print(f"Total in app: {len(full_data)}")
print(f"Total in Google Map: {len(remote_data)}")
print(f"Coordinate matches: {matches}")
print(f"New points in Google Map: {len(new_points)}")
print(f"Points in app NOT in Google Map: {len(deleted_in_remote)}")
print(f"Renames detected: {len(renames)}")

if renames:
    print("\nSample Renames (App -> Map):")
    for old, new, coord in renames[:10]:
        print(f"  '{old}' -> '{new}' at {coord}")

if new_points:
    print("\nSample NEW points:")
    for p in new_points[:10]:
        print(f"  '{p['name']}' at {p['lat']}, {p['lon']}")

if deleted_in_remote:
    print("\nSample points in app NOT in Google:")
    for p in deleted_in_remote[:10]:
        print(f"  '{p['name']}' at {p['lat']}, {p['lon']}")
