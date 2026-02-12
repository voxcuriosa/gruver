import re
import json
import xml.etree.ElementTree as ET
import os

# convert_geojson.py
# Python version of convert_geojson.php
# Converts KML to GeoJSON with consistent safe name handling (preserving Norwegian characters)

KML_FILE = 'full_data.kml'
JSON_OUTPUT = 'full_data.json'

def get_safe_name(s):
    """
    Sanitize filename while PRESERVING Norwegian characters.
    Must match the logic:
    1. Allow letters, numbers, æ, ø, å, Æ, Ø, Å
    2. Replace everything else with underscore
    3. Collapse underscores
    4. Trim underscores
    """
    if not s:
        return ""
    
    # Python regex for "not alphanumeric and not norwegian chars"
    # Note: \w in Python 3 unicode matches diverse chars, but let's be explicit to match PHP logic
    # PHP: preg_replace('/[^a-zA-Z0-9æøåÆØÅ]/u', '_', $str);
    
    # We use a whitelist approach
    safe_pattern = re.compile(r'[^a-zA-Z0-9æøåÆØÅ]')
    s = safe_pattern.sub('_', s)
    
    # Collapse multiple underscores
    s = re.sub(r'_+', '_', s)
    
    return s.strip('_')

def clean_description(desc):
    if not desc:
        return ""
    
    # Remove <img> tags
    desc = re.sub(r'<img[^>]+>', '', desc, flags=re.IGNORECASE)
    # Replace <br> with newline
    desc = re.sub(r'<br\s*/?>', '\n', desc, flags=re.IGNORECASE)
    # Clean specific phrases
    desc = re.sub(r'Nettside:?[\s\xa0]*(\n|$)', r'\1', desc, flags=re.IGNORECASE|re.UNICODE)
    desc = re.sub(r'Posisjon:?[\s\xa0]*(\n|$)', r'\1', desc, flags=re.IGNORECASE|re.UNICODE)
    desc = re.sub(r'Informasjon:?[\s\xa0]*(\n|$)', r'\1', desc, flags=re.IGNORECASE|re.UNICODE)
    
    # Remove NGU links from description text
    desc = re.sub(r'Nettside:?[\s\xa0]*(https?://aps\.ngu\.no[^\s<]+).*?(<br\s*/?>|\n|$)', '', desc, flags=re.IGNORECASE|re.UNICODE)
    
    # Remove empty links
    desc = re.sub(r'<a\s+[^>]*href=""[^>]*>.*?</a>', '', desc, flags=re.IGNORECASE)
    
    return desc.strip()

def get_category_data(name, description, style_url):
    cat_key = 'DEFAULT'
    if not style_url:
        style_url = ''
        
    # 1. StyleMap Logic
    style_map = {
        'E65100': 'GRUVE', 'C2185B': 'GRUVE',
        '01579B': 'BYGDEBORG', '9C27B0': 'BYGDEBORG',
        '4E342E': 'GAPAHUK', '795548': 'GAPAHUK',
        'BDBDBD': 'VANN', '0288D1': 'VANN',
        'FFEA00': 'UTSIKT', 'FFD600': 'UTSIKT',
        'AFB42B': 'HUSTUFT',
        '097138': 'GRENSESTEIN',
        '000000': 'GRAVHAUG',
        '1A237E': 'HULE', '3949AB': 'HULE'
    }
    
    for variant, key in style_map.items():
        if variant in style_url:
            cat_key = key
            break
            
    # 2. Keyword Logic
    lower_name = name.lower()
    full_text = (name + " " + description).lower()
    
    diverse_list = [
        'bjørnestilla', 'siljuknatthytta', 'skiens midtpunkt', 'laftet konstruksjon', 
        '"tunnel"', 'tunnel', 'lille ulvskollen', 'brynhildsbu', 'russerhula', 
        'tømmerskot', 'ulvsvannsjordet', 'rødgangsåsen', 'rødgangskollen', 
        'vebjørnsplass', 'vebjørns plass', 'speiderskogen', 'århus sluse', 
        'århus bru', 'gamle gulset bedehus', 'gulsetbrua', 'falkumlia bad', 
        'strømdal gård', 'hoppbakke', 'vadrette', 'grønne sletta', 
        'granebakken', 'meierkollen', 'kutterød sluse', 'helleristninger', 
        'søndre siljuknatten'
    ]
    
    if re.search(r'gruve', lower_name) or re.search(r'skjerp', lower_name) or \
       re.search(r'skjæring|skjering|dagstrosse|dagbrudd|stoll|skråsynk|skrasynk|malm|synk|gruvegang', full_text):
        cat_key = 'GRUVE'
    elif 'bygdeborg' in full_text:
        cat_key = 'BYGDEBORG'
    elif 'hustuft' in full_text or 'ruin' in full_text:
        cat_key = 'HUSTUFT'
    elif 'hule' in full_text or 'grotte' in full_text or 'kuppelommen' in lower_name or 'silencio' in full_text:
        cat_key = 'HULE'
    elif 'sollifjell' in lower_name or re.search(r'utsiktspunkt', full_text) or re.search(r'\butsikt\b', full_text):
        cat_key = 'UTSIKT'
    elif any(d in lower_name for d in diverse_list):
        cat_key = 'DIVERSE'
    elif re.search(r'\bvei\b', name, re.IGNORECASE) or re.search(r'\bveien\b', name, re.IGNORECASE) or 'hulvei' in full_text:
        cat_key = 'VEI'
        
    return cat_key

def extract_images_and_links(description, extended_data, safe_name):
    images = []
    links = []
    
    # 1. Extract from ExtendedData
    if 'gx_media_links' in extended_data:
        urls = extended_data['gx_media_links'].split()
        for url in urls:
            if url:
                images.append(url)
                
    # 2. Extract from Description (HTML)
    if not images:
        img_matches = re.findall(r'<img[^>]+src="([^">]+)"', description, re.IGNORECASE)
        for url in img_matches:
            if url not in images:
                images.append(url)
                
    # Filter valid images
    valid_images = []
    for url in images:
        if re.search(r'\.(jpg|jpeg|png|gif|webp|bmp)(\?|#|$)', url, re.IGNORECASE) or \
           'googleusercontent.com' in url or \
           'hostedimage' in url or \
           'assets/' in url:
            if url not in valid_images:
                valid_images.append(url)
    
    images = valid_images
    
    # Generate local images paths
    local_images = []
    for idx, img_url in enumerate(images):
        # PHP: "bilder/" . $safeName . "_" . ($idx + 1) . ".jpg"
        local_images.append(f"bilder/{safe_name}_{idx + 1}.jpg")
        
    # Extract links
    href_matches = re.findall(r'href="([^"]+)"', description, re.IGNORECASE)
    raw_url_matches = re.findall(r'((?:https?://|www\.)[^\s<"\']+)', description, re.IGNORECASE)
    
    all_links = href_matches + [m[0] if isinstance(m, tuple) else m for m in raw_url_matches]
    
    for url in all_links:
        if url and url not in links:
            links.append(url)
            
    return {
        'images': images,
        'localImages': local_images,
        'imageUrl': local_images[0] if local_images else (images[0] if images else None),
        'remoteImageUrl': images[0] if images else None,
        'links': links
    }

def main():
    if not os.path.exists(KML_FILE):
        print(f"Error: {KML_FILE} not found.")
        return

    # Parse XML with namespace handling
    try:
        tree = ET.parse(KML_FILE)
        root = tree.getroot()
        # KML usually has a namespace
        ns = {'kml': 'http://www.opengis.net/kml/2.2'}
        
        # Find all Placemarks
        # If standard namespace fails, try without (some KMLs are messy)
        placemarks = root.findall('.//kml:Placemark', ns)
        if not placemarks:
            placemarks = root.findall('.//Placemark')
            
        print(f"Found {len(placemarks)} placemarks.")
        
        geojson = {
            'type': 'FeatureCollection',
            'features': []
        }
        
        for idx, pm in enumerate(placemarks):
            # Extract basic data
            name_node = pm.find('kml:name', ns) if ns else pm.find('name')
            name = name_node.text if name_node is not None and name_node.text else ""
            
            desc_node = pm.find('kml:description', ns) if ns else pm.find('description')
            description = desc_node.text if desc_node is not None and desc_node.text else ""
            
            style_node = pm.find('kml:styleUrl', ns) if ns else pm.find('styleUrl')
            style_url = style_node.text if style_node is not None and style_node.text else ""
            
            # Extended Data
            extended_data = {}
            ed_node = pm.find('kml:ExtendedData', ns) if ns else pm.find('ExtendedData')
            if ed_node is not None:
                for data in ed_node.findall('kml:Data', ns) if ns else ed_node.findall('Data'):
                    val_node = data.find('kml:value', ns) if ns else data.find('value')
                    if val_node is not None:
                        encoding = data.get('name')
                        extended_data[encoding] = val_node.text
            
            # Geometry
            geometry = None
            is_line = False
            
            point = pm.find('kml:Point', ns) if ns else pm.find('Point')
            line_string = pm.find('kml:LineString', ns) if ns else pm.find('LineString')
            
            if point is not None:
                coords_text = point.find('kml:coordinates', ns).text if ns else point.find('coordinates').text
                if coords_text:
                    parts = coords_text.strip().split(',')
                    if len(parts) >= 2:
                        geometry = {
                            'type': 'Point',
                            'coordinates': [float(parts[0]), float(parts[1])]
                        }
            
            elif line_string is not None:
                is_line = True
                coords_text = line_string.find('kml:coordinates', ns).text if ns else line_string.find('coordinates').text
                if coords_text:
                    coord_tuples = []
                    for pair in coords_text.split():
                        p = pair.split(',')
                        if len(p) >= 2:
                            coord_tuples.append([float(p[0]), float(p[1])])
                    
                    if coord_tuples:
                        geometry = {
                            'type': 'LineString',
                            'coordinates': coord_tuples
                        }

            if geometry:
                # Process Data
                cat_key = get_category_data(name, description, style_url)
                safe_name = get_safe_name(name)
                media_data = extract_images_and_links(description, extended_data, safe_name)
                clean_desc = clean_description(description)
                
                image_url = media_data['imageUrl']
                images = media_data['images']
                links = media_data['links']
                
                # Hardcoded logic
                for l in links:
                    if "SkPQzkgqQ3E6zR7n6" in l:
                        images.append('assets/mining_map_n20.png')
                        if not image_url: image_url = 'assets/mining_map_n20.png'
                    if "ZKktL6ESvvG6rdcN9" in l:
                        images.append('assets/mining_map_vestmarka.png')
                        if not image_url: image_url = 'assets/mining_map_vestmarka.png'
                
                if "Bruberg østre gruve" in name:
                    links.append("https://youtu.be/rz7ON7pVRTc")
                if "Magneten gruve" in name:
                    links.append("https://youtube.com/shorts/rTPEYpHHIrk")
                    
                # Unique
                links = list(dict.fromkeys(links))
                images = list(dict.fromkeys(images))
                
                # Lat/Lng helper
                lat, lng = 0, 0
                if geometry['type'] == 'Point':
                    lng, lat = geometry['coordinates']
                elif geometry['type'] == 'LineString' and geometry['coordinates']:
                    lng, lat = geometry['coordinates'][0]
                
                feature = {
                    'type': 'Feature',
                    'geometry': geometry,
                    'properties': {
                        'id': idx,
                        'name': name,
                        'cleanDesc': clean_desc,
                        'styleUrl': style_url,
                        'catKey': cat_key,
                        'images': images,
                        'localImages': media_data['localImages'],
                        'imageUrl': image_url,
                        'remoteImageUrl': media_data['remoteImageUrl'],
                        'links': links,
                        'isLine': is_line,
                        'lat': lat,
                        'lng': lng
                    }
                }
                geojson['features'].append(feature)

        # Write output
        with open(JSON_OUTPUT, 'w', encoding='utf-8') as f:
            json.dump(geojson, f, indent=4, ensure_ascii=False)
            
        print(f"Successfully converted {len(geojson['features'])} features to {JSON_OUTPUT}")

    except Exception as e:
        print(f"Error parsing KML: {e}")

if __name__ == "__main__":
    main()
