import json
import time

overrides_file = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\overrides.json'
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

map_image = "assets/mining_map_vestmarka.png"
timestamp = int(time.time())

overrides = []
for p in affected_points:
    overrides.append({
        "timestamp": timestamp,
        "name": p['name'],
        "lat": p['lat'],
        "lng": p['lng'],
        "action": "add_image",
        "value": map_image
    })

with open(overrides_file, 'w', encoding='utf-8') as f:
    json.dump(overrides, f, ensure_ascii=False, indent=4)

print(f"Added {len(overrides)} image overrides to overrides.json.")
