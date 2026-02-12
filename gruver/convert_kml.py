import re
import json
import os

# Configuration
KML_FILE = 'full_data.kml'
JSON_OUTPUT = 'full_data.json'

def get_safe_name(name):
    # Keep consistent with PHP: Allow letters, numbers, æøå
    # PHP: preg_replace('/[^a-zA-Z0-9æøåÆØÅ]/u', '_', $str)
    name = re.sub(r'[^a-zA-Z0-9æøåÆØÅ]+', '_', name)
    name = re.sub(r'_+', '_', name).strip('_')
    return name

def get_category_data(name, description, style_url):
    if style_url is None: style_url = ''
    cat_key = 'DEFAULT'
    
    # 1. StyleMap Logic
    style_map = {
        'E65100': 'GRUVE',
        'C2185B': 'GRUVE',
        '01579B': 'BYGDEBORG',
        '9C27B0': 'BYGDEBORG',
        '4E342E': 'GAPAHUK',
        '795548': 'GAPAHUK',
        'BDBDBD': 'VANN',
        '0288D1': 'VANN',
        'FFEA00': 'UTSIKT',
        'FFD600': 'UTSIKT',
        'AFB42B': 'HUSTUFT',
        '097138': 'GRENSESTEIN',
        '000000': 'GRAVHAUG',
        '1A237E': 'HULE',
        '3949AB': 'HULE'
    }
    
    for variant, key in style_map.items():
        if variant in style_url:
            cat_key = key
            break
            
    # 2. Keyword Logic
    lower_name = name.lower()
    full_text = (name + " " + description).lower()
    
    diverse_list = ['bjørnestilla', 'siljuknatthytta', 'skiens midtpunkt', 'laftet konstruksjon', '"tunnel"', 'tunnel', 'lille ulvskollen', 'brynhildsbu', 'russerhula', 'tømmerskot', 'ulvsvannsjordet', 'rødgangsåsen', 'rødgangskollen', 'vebjørnsplass', 'vebjørns plass', 'speiderskogen', 'århus sluse', 'århus bru', 'gamle gulset bedehus', 'gulsetbrua', 'falkumlia bad', 'strømdal gård', 'hoppbakke', 'vadrette', 'grønne sletta', 'granebakken', 'meierkollen', 'kutterød sluse', 'helleristninger', 'søndre siljuknatten']
    
    # PHP Regex: /gruve/
    if re.search(r'gruve', lower_name) or \
       re.search(r'skjerp', lower_name) or \
       re.search(r'skjæring|skjering|dagstrosse|dagbrudd|stoll|skråsynk|skrasynk|malm|synk|gruvegang', full_text) or \
       'meltveit' in lower_name or 'gruvefelt' in lower_name:
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
    elif re.search(r'\bvei\b', name, re.I) or re.search(r'\bveien\b', name, re.I) or 'hulvei' in full_text:
        cat_key = 'VEI'
        
    return cat_key

def clean_description(description):
    if not description: return ''
    # Remove <img> tags
    desc = re.sub(r'<img[^>]+>', '', description, flags=re.I)
    # Replace <br> with newline
    desc = re.sub(r'<br\s*/?>', '\n', desc, flags=re.I)
    # Clean specific phrases
    desc = re.sub(r'Nettside:?[\s\xa0]*(\n|$)', r'\1', desc, flags=re.I|re.U)
    desc = re.sub(r'Posisjon:?[\s\xa0]*(\n|$)', r'\1', desc, flags=re.I|re.U)
    desc = re.sub(r'Informasjon:?[\s\xa0]*(\n|$)', r'\1', desc, flags=re.I|re.U)
    
    # Remove "Nettside: [URL]" matches ONLY for NGU links
    # PHP: preg_replace('/Nettside:?[\s\xa0]*(https?:\/\/aps\.ngu\.no[^\s<]+).*?(<br\s*\/?>|\n|$)/iu', '', $desc);
    desc = re.sub(r'Nettside:?[\s\xa0]*(https?://aps\.ngu\.no[^\s<]+).*?(<br\s*/?>|\n|$)', '', desc, flags=re.I|re.U|re.DOTALL)
    
    # Remove empty links
    desc = re.sub(r'<a\s+[^>]*href=""[^>]*>.*?</a>', '', desc, flags=re.I)
    
    return desc.strip()

def extract_images_and_links(description, extended_data, safe_name):
    images = []
    links = []
    
    # 1. Extended Data
    if 'gx_media_links' in extended_data:
        urls = extended_data['gx_media_links'].strip().split()
        for url in urls:
            if url: images.append(url)
            
    # 2. Fallback: Description
    if not images:
        matches = re.findall(r'<img[^>]+src="([^">]+)"', description, flags=re.I)
        for url in matches:
            if url not in images: images.append(url)
            
    # Filter valid images
    # PHP: preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)(\?|#|$)/i', $url)
    valid_images = []
    for url in images:
        if re.search(r'\.(jpg|jpeg|png|gif|webp|bmp)(\?|#|$)', url, re.I) or \
           'googleusercontent.com' in url or \
           'hostedimage' in url or \
           'assets/' in url:
            if url not in valid_images: valid_images.append(url)
    images = valid_images
    
    # Generate local images
    local_images = []
    for idx, img_url in enumerate(images):
        local_images.append(f"bilder/{safe_name}_{idx+1}.jpg")
        
    # Extract links
    # PHP: preg_match_all('/href="([^"]+)"|((?:https?:\/\/|www\.)[^\s<"\']+)/i', $description, $linkMatches);
    # Complex regex in PHP, simplified port
    raw_links = re.findall(r'href="([^"]+)"', description, re.I)
    raw_links += re.findall(r'((?:https?://|www\.)[^\s<"\']+)', description, re.I)
    
    # Deduplicate and clean tuple from findall
    for item in raw_links:
        url = item if isinstance(item, str) else item[0] 
        if url and url not in links:
            links.append(url)
            
    return {
        'images': images,
        'localImages': local_images,
        'imageUrl': local_images[0] if local_images else (images[0] if images else None),
        'remoteImageUrl': images[0] if images else None,
        'links': links
    }

def convert():
    print(f"Reading {KML_FILE}...")
    try:
        with open(KML_FILE, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"Error reading KML: {e}")
        return

    placemarks = re.findall(r'<Placemark>(.*?)</Placemark>', content, re.DOTALL)
    print(f"Found {len(placemarks)} placemarks.")
    
    geojson = {
        'type': 'FeatureCollection',
        'features': []
    }
    
    # We iterate index to assign ID (same as PHP $idx)
    for idx, pm_content in enumerate(placemarks):
        # Name
        name_match = re.search(r'<name>(.*?)</name>', pm_content, re.DOTALL)
        name = name_match.group(1).strip() if name_match else "Ukjent"
        name = re.sub(r'<!\[CDATA\[(.*?)\]\]>', r'\1', name)
        
        # Description
        desc_match = re.search(r'<description>(.*?)</description>', pm_content, re.DOTALL)
        description = desc_match.group(1) if desc_match else ""
        
        # StyleURL
        style_match = re.search(r'<styleUrl>(.*?)</styleUrl>', pm_content)
        style_url = style_match.group(1).strip() if style_match else ""
        
        # Extended Data
        extended_data = {}
        # Simple regex for Data name="gx_media_links"
        gx_match = re.search(r'<Data name="gx_media_links">\s*<value>(.*?)</value>', pm_content, re.DOTALL)
        if gx_match:
            extended_data['gx_media_links'] = gx_match.group(1)
            
        # Geometry
        geometry = None
        is_line = False
        
        # Point
        pt_match = re.search(r'<Point>.*?<coordinates>(.*?)</coordinates>.*?</Point>', pm_content, re.DOTALL)
        ln_match = re.search(r'<LineString>.*?<coordinates>(.*?)</coordinates>.*?</LineString>', pm_content, re.DOTALL)
        
        if pt_match:
            coords = pt_match.group(1).strip().split(',')
            if len(coords) >= 2:
                geometry = {
                    'type': 'Point',
                    'coordinates': [float(coords[0]), float(coords[1])]
                }
        elif ln_match:
            is_line = True
            coords_raw = ln_match.group(1).strip()
            coord_lines = coords_raw.split()
            coords_array = []
            for line in coord_lines:
                p = line.split(',')
                if len(p) >= 2:
                    coords_array.append([float(p[0]), float(p[1])])
            if coords_array:
                geometry = {
                    'type': 'LineString',
                    'coordinates': coords_array
                }

        if geometry:
            cat_key = get_category_data(name, description, style_url)
            safe_name = get_safe_name(name)
            media_data = extract_images_and_links(description, extended_data, safe_name)
            clean_desc = clean_description(description)
            
            images = media_data['images']
            links = media_data['links']
            image_url = media_data['imageUrl']
            
            # Hardcoded logic port
            for l in links:
                if 'SkPQzkgqQ3E6zR7n6' in l:
                    if 'assets/mining_map_n20.png' not in images: images.append('assets/mining_map_n20.png')
                    if not image_url: image_url = 'assets/mining_map_n20.png'
                if 'ZKktL6ESvvG6rdcN9' in l:
                    if 'assets/mining_map_vestmarka.png' not in images: images.append('assets/mining_map_vestmarka.png')
                    if not image_url: image_url = 'assets/mining_map_vestmarka.png'
                    
            if 'Bruberg østre gruve' in name:
                links.append("https://youtu.be/rz7ON7pVRTc")
            if 'Magneten gruve' in name:
                 links.append("https://youtube.com/shorts/rTPEYpHHIrk")
                 
            # Dedupe
            links = list(dict.fromkeys(links))
            images = list(dict.fromkeys(images))
            
            lat = 0
            lng = 0
            if geometry['type'] == 'Point':
                lat = geometry['coordinates'][1]
                lng = geometry['coordinates'][0]
            elif geometry['type'] == 'LineString' and len(geometry['coordinates']) > 0:
                lat = geometry['coordinates'][0][1]
                lng = geometry['coordinates'][0][0]

            feature = {
                'type': 'Feature',
                'geometry': geometry,
                'properties': {
                    'id': idx, # THE IMPORTANT ID
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
            
    print(f"Generated {len(geojson['features'])} features.")
    
    with open(JSON_OUTPUT, 'w', encoding='utf-8') as f:
        json.dump(geojson, f, indent=4, ensure_ascii=False)
    print(f"Saved to {JSON_OUTPUT}")

if __name__ == "__main__":
    convert()
