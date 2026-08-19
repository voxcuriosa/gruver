import xml.etree.ElementTree as ET
import json
import re

def extract_bounds(xml_file, output_file):
    print(f"Parsing {xml_file}...")
    try:
        tree = ET.parse(xml_file)
        root = tree.getroot()
        
        # Namespaces are usually present in WMS capabilities
        ns = {'wms': 'http://www.opengis.net/wms'}
        
        # Try finding layers with and without namespace
        layers = root.findall('.//wms:Layer', ns)
        if not layers:
             layers = root.findall('.//Layer')
             
        bounds_map = {}
        
        for layer in layers:
            name_elem = layer.find('wms:Name', ns)
            if name_elem is None:
                name_elem = layer.find('Name')
                
            if name_elem is not None:
                name = name_elem.text
                
                # Find EX_GeographicBoundingBox
                bbox = layer.find('wms:EX_GeographicBoundingBox', ns)
                if bbox is None:
                     bbox = layer.find('EX_GeographicBoundingBox')
                     
                if bbox is not None:
                    try:
                        west = float(bbox.find('wms:westBoundLongitude', ns).text if bbox.find('wms:westBoundLongitude', ns) is not None else bbox.find('westBoundLongitude').text)
                        east = float(bbox.find('wms:eastBoundLongitude', ns).text if bbox.find('wms:eastBoundLongitude', ns) is not None else bbox.find('eastBoundLongitude').text)
                        south = float(bbox.find('wms:southBoundLatitude', ns).text if bbox.find('wms:southBoundLatitude', ns) is not None else bbox.find('southBoundLatitude').text)
                        north = float(bbox.find('wms:northBoundLatitude', ns).text if bbox.find('wms:northBoundLatitude', ns) is not None else bbox.find('northBoundLatitude').text)
                        
                        # Leaflet format: [[south, west], [north, east]]
                        bounds_map[name] = [[south, west], [north, east]]
                        # print(f"Found bounds for: {name}")
                    except AttributeError:
                        continue

        print(f"Extracted bounds for {len(bounds_map)} layers.")
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(bounds_map, f, ensure_ascii=False)
            
        print(f"Saved to {output_file}")
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    extract_bounds("wms_nib_prosjekter.xml", "nib_bounds.json")
