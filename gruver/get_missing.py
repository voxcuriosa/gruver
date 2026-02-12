
import xml.etree.ElementTree as ET
import re

def clean_name(name):
    if name is None: return ""
    name = re.sub(r'<!\[CDATA\[(.*?)\]\]>', r'\1', name)
    name = re.sub(r'<.*?>', '', name)
    return ' '.join(name.split()).strip()

def extract_placemarks(kml_file):
    try:
        tree = ET.parse(kml_file)
        root = tree.getroot()
        ns = ""
        if '}' in root.tag:
            ns = root.tag.split('}')[0] + '}'
        data = []
        for pm in root.findall(f".//{ns}Placemark"):
            name_node = pm.find(f"{ns}name")
            name = clean_name(name_node.text if name_node is not None else "")
            coords_node = pm.find(f".//{ns}coordinates")
            if coords_node is None or not coords_node.text.strip(): continue
            first_coord = coords_node.text.strip().split()[0]
            parts = first_coord.split(',')
            if len(parts) < 2: continue
            lon, lat = parts[0].strip(), parts[1].strip()
            data.append({'name': name, 'lat': lat, 'lon': lon})
        return data
    except Exception as e:
        print(f"Error parsing {kml_file}: {e}")
        return []

def get_coord_key(site):
    try:
        return (round(float(site['lat']), 6), round(float(site['lon']), 6))
    except:
        return None

app_data = extract_placemarks('full_data.kml')
map_data = extract_placemarks('remote_map.kml')

app_coords = {get_coord_key(d) for d in app_data if get_coord_key(d)}
missing_points = [p for p in map_data if get_coord_key(p) not in app_coords]

print(f"Total points in Google Map: {len(map_data)}")
print(f"Total points in Project: {len(app_data)}")
print(f"Points in Google Map MISSING from Project: {len(missing_points)}")

# Save the list to a file for the user to review
with open('missing_points.txt', 'w', encoding='utf-8') as f:
    f.write("POINTS IN GOOGLE MAP BUT MISSING FROM PROJECT\n")
    f.write("=============================================\n\n")
    for p in sorted(missing_points, key=lambda x: x['name']):
        f.write(f"- {p['name']} ({p['lat']}, {p['lon']})\n")

print("\nSaved full list to missing_points.txt")
