import json

def analyze_categories():
    try:
        with open('full_data.json', 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Check if it's a FeatureCollection
        if isinstance(data, dict) and 'features' in data:
            sites = data['features']
        elif isinstance(data, list):
            sites = data
        else:
            print("Unknown JSON structure")
            return

        categories = set()
        grav_entries = []

        for site in sites:
            props = site.get('properties', {})
            
            if 'catKey' in props:
                categories.add(props['catKey'])
            
            # Check for "grav" in name or description
            name = props.get('name', '').lower()
            
            if 'grav' in name and 'gruve' not in name: 
                grav_entries.append(props.get('name', 'Unknown'))
        
        print("Unique Categories (catKey):")
        for cat in sorted(categories):
            print(f"- {cat}")
            
        print(f"\nEntries with 'grav' in name (found {len(grav_entries)}):")
        for name in grav_entries[:20]:
            print(f"- {name}")
            
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    analyze_categories()
