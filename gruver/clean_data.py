import json
import re

def clean_data():
    with open('full_data.json', 'r', encoding='utf-8') as f:
        data = json.load(f)

    changed_count = 0
    for feature in data['features']:
        props = feature.get('properties', {})
        name = props.get('name', '')
        links = props.get('links', [])
        desc = props.get('cleanDesc', '')
        
        has_skiensatlas = False
        new_links = []
        for link in links:
            if 'skiensatlas.org' in link:
                has_skiensatlas = True
                # Clean up ]]> and other junk
                clean_link = link.replace(']]>', '').strip()
                # Special case for Åshammeren
                if "shammeren" in name.lower():
                    clean_link = "http://www.skiensatlas.org/soner/bronsealderlandet/aashammeren-jernhammer"
                new_links.append(clean_link)
            else:
                new_links.append(link)
        
        if has_skiensatlas:
            props['links'] = new_links
            
            # Remove "Nettside:" line from cleanDesc
            # Match "Nettside: [link]" and optional connectors like " og "
            # Handles CDATA wrapper
            new_desc = desc
            
            # Regex to find Nettside: followed by URLs, potentially with 'og' or spaces
            # We want to remove the whole line or segment
            pattern = r'Nettside:\s*http[^\s<]+(?:\s+og\s+http[^\s<]+)*'
            new_desc = re.sub(pattern, '', new_desc)
            
            # Also clean up any lingering ]]> inside the CDATA if it was part of the text
            new_desc = new_desc.replace(']]>', '')
            
            if new_desc != desc:
                props['cleanDesc'] = new_desc
                changed_count += 1

    with open('full_data.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
    
    print(f"Cleaned up {changed_count} sites.")

if __name__ == "__main__":
    clean_data()
