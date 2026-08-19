import json

# Path to backup that still has the link
backup_path = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\backup\v27\full_data.json'
search_link = "AF1QipPtACB3BbwGKRDDumcBHLXQygkEVLcwqaPUhcr4JVkTKo_wZQy5zP9jpCYv0LzJfA"
search_image = "mining_map_vestmarka.png"

try:
    with open(backup_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
except Exception as e:
    print(f"Error loading backup: {e}")
    exit(1)

points_with_link = []
points_with_image = []

for feature in data['features']:
    props = feature['properties']
    name = props.get('name', 'N/A')
    desc = props.get('cleanDesc', '')
    links = props.get('links', [])
    images = props.get('images', [])
    local_images = props.get('localImages', [])
    
    has_link = (search_link in desc) or any(search_link in l for l in links)
    has_image = any(search_image in img for img in images) or any(search_image in img for img in local_images)
    
    if has_link:
        points_with_link.append({
            "name": name,
            "has_image": has_image,
            "images": images + local_images
        })
    if has_image:
        points_with_image.append(name)

print(f"Total points with the LINK: {len(points_with_link)}")
print(f"Total points with the IMAGE: {len(points_with_image)}")

correlation_count = sum(1 for p in points_with_link if p['has_image'])
print(f"Points with BOTH: {correlation_count}")

print("\nPoints with LINK but NO IMAGE:")
for p in points_with_link:
    if not p['has_image']:
        print(f"- {p['name']} (Images: {p['images']})")

print("\nPoints with BOTH (sample):")
for p in points_with_link[:10]:
    if p['has_image']:
        print(f"- {p['name']}")
