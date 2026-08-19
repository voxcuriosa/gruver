import json

file_path = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\full_data.json'
affected_points = [
    {"name": "Dagstrosse", "lat": 59.2280634587967, "lng": 9.53417211771012}, {"name": "Dagstrosse", "lat": 59.225937, "lng": 9.531451}, 
    {"name": "Dagstrosse", "lat": 59.2236571, "lng": 9.5324726}, {"name": "Dagstrosse", "lat": 59.2226762, "lng": 9.5323807}, 
    {"name": "Dagstrosse", "lat": 59.220454018473, "lng": 9.5363088324666}, {"name": "Dyp dagstrosse", "lat": 59.2200355, "lng": 9.533002}, 
    {"name": "Dyp dagstrosse", "lat": 59.2230545, "lng": 9.5288687}, {"name": "Sammenhengende dagstrosser", "lat": 59.2374888, "lng": 9.5344769}, 
    {"name": "Rødgangsgruva", "lat": 59.2376803, "lng": 9.534142}, {"name": "Sandgruva", "lat": 59.2351378, "lng": 9.5516535}, 
    {"name": "Schakalegruva/Sjakalegruva (Århusgruva)", "lat": 59.2312691, "lng": 9.5470092}, {"name": "Sammenhengende malmåre?", "lat": 59.2315689, "lng": 9.5392713}, 
    {"name": "Dagstrosse", "lat": 59.2170435, "lng": 9.5315609}, {"name": "Dagstrosse", "lat": 59.2184397, "lng": 9.5336347}, 
    {"name": "Dyp dagstrosse", "lat": 59.2196004, "lng": 9.526731}, {"name": "Lang og dyp dagstrosse", "lat": 59.2175164, "lng": 9.5366542}, 
    {"name": "Dagstrosse", "lat": 59.2185126, "lng": 9.5368292}, {"name": "Dagstrosse", "lat": 59.2194673, "lng": 9.5368607}, 
    {"name": "Dagstrosse", "lat": 59.2214973, "lng": 9.5361335}, {"name": "Dagstrosse", "lat": 59.2214351, "lng": 9.53623}, 
    {"name": "Tipphaug", "lat": 59.2210761, "lng": 9.5362766}, {"name": "Dagstrosse", "lat": 59.2240929, "lng": 9.5351756}, 
    {"name": "Dagstrosse", "lat": 59.21795, "lng": 9.53664}, {"name": "Dagstrosse", "lat": 59.219, "lng": 9.53681}, 
    {"name": "Dyp dagstrosse", "lat": 59.22163, "lng": 9.53609}, {"name": "Dagstrosse", "lat": 59.2232, "lng": 9.53598}, 
    {"name": "Dagstrosse", "lat": 59.22455, "lng": 9.53201}, {"name": "Dagstrosse", "lat": 59.22501, "lng": 9.53181}, 
    {"name": "Dagstrosse", "lat": 59.22862, "lng": 9.52456}, {"name": "Dagstrosse", "lat": 59.228, "lng": 9.52457}, 
    {"name": "Dagstrosse", "lat": 59.2278458, "lng": 9.5246251}, {"name": "Høy dagstrosse", "lat": 59.2271, "lng": 9.52468}, 
    {"name": "Dagstrosse", "lat": 59.2267207, "lng": 9.5246748}, {"name": "Dagstrosse", "lat": 59.2233161, "lng": 9.5288258}, 
    {"name": "Kåseligangdraget", "lat": 59.2241684, "lng": 9.5351457}, {"name": "Dagstrosser", "lat": 59.2161521, "lng": 9.5367474}, 
    {"name": "Dagstrosse", "lat": 59.2131578, "lng": 9.5428937}, {"name": "Dagstrosse", "lat": 59.2275344, "lng": 9.5343475}, 
    {"name": "Dagstrosse", "lat": 59.2193625, "lng": 9.526673}, {"name": "Dagstrosse", "lat": 59.2191965, "lng": 9.5267464}, 
    {"name": "Dagstrosse", "lat": 59.21748, "lng": 9.53083}, {"name": "Dagstrosse", "lat": 59.21908, "lng": 9.53}, 
    {"name": "Dagstrosse", "lat": 59.2193237, "lng": 9.5266941}, {"name": "Liten skjæring", "lat": 59.22155, "lng": 9.52932}, 
    {"name": "Dagstrosse", "lat": 59.2282, "lng": 9.5341}, {"name": "Liten dagstrosse", "lat": 59.2284813, "lng": 9.5342214}, 
    {"name": "Sammenhengende åre?", "lat": 59.2275344, "lng": 9.5343475}, {"name": "Dagstrosse", "lat": 59.22615, "lng": 9.5314}, 
    {"name": "Dagstrosse", "lat": 59.222482, "lng": 9.535553}, {"name": "Dagstrossse", "lat": 59.22232, "lng": 9.53567}, 
    {"name": "Dyp dagstrosse", "lat": 59.22208, "lng": 9.53589}
]

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

map_image = "assets/mining_map_vestmarka.png"
count_updated = 0

for feature in data['features']:
    props = feature['properties']
    name = props.get('name')
    lat = props.get('lat')
    lng = props.get('lng')
    
    # Check if this point is in affected list
    is_affected = False
    for p in affected_points:
        if p['name'] == name and abs(p['lat'] - lat) < 0.0001 and abs(p['lng'] - lng) < 0.0001:
            is_affected = True
            break
    
    if is_affected:
        if 'images' not in props: props['images'] = []
        if 'localImages' not in props: props['localImages'] = []
        
        if map_image not in props['images']:
            props['images'].append(map_image)
        if map_image not in props['localImages']:
            props['localImages'].append(map_image)
        
        # Set as primary if no image
        if not props.get('imageUrl'):
            props['imageUrl'] = map_image
            
        count_updated += 1

print(f"Updated {count_updated} points with the map image.")

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=4)
