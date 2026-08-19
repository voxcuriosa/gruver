import json

backup_path = r'c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\backup\v27\full_data.json'
search_link = "AF1QipPtACB3BbwGKRDDumcBHLXQygkEVLcwqaPUhcr4JVkTKo_wZQy5zP9jpCYv0LzJfA"

with open(backup_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

points_with_link = []
all_images_in_linked_points = {}

for feature in data['features']:
    props = feature['properties']
    desc = props.get('cleanDesc', '')
    links = props.get('links', [])
    
    if search_link in desc or any(search_link in l for l in links):
        name = props.get('name', 'N/A')
        images = props.get('images', []) + props.get('localImages', [])
        points_with_link.append(name)
        for img in set(images):
            all_images_in_linked_points[img] = all_images_in_linked_points.get(img, 0) + 1

print(f"Total points with the LINK: {len(points_with_link)}")
print("\nImages found in these points:")
if not all_images_in_linked_points:
    print("No images found in any of these points!")
else:
    for img, count in sorted(all_images_in_linked_points.items(), key=lambda x: x[1], reverse=True):
        print(f"[{count}] {img}")
