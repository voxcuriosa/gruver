import json
import sys

def search_json(filename, search_term):
    try:
        with open(filename, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        print(f"Loaded {len(data['features'])} features.")
        
        found = False
        for feature in data['features']:
            props = feature['properties']
            # Search in all string values of properties
            for key, value in props.items():
                if isinstance(value, str) and search_term.lower() in value.lower():
                    print(f"--- FANT PUNKT (Match i '{key}') ---")
                    print(json.dumps(feature, indent=4, ensure_ascii=False))
                    found = True
                    break
        
        if not found:
            print("Ingen treff funnet.")
            
    except Exception as e:
        print(f"Feil: {e}")

if __name__ == "__main__":
    search_json('full_data.json', 'Dagstrosse_1.jpg')
