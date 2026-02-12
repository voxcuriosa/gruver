import json
import os
import requests
import re
from PIL import Image
from io import BytesIO

# Configuration
JSON_FILE = 'full_data.json'
EXPORT_DIR = 'bilder'
MAX_WIDTH = 1920
JPEG_QUALITY = 80

def setup_dir():
    if not os.path.exists(EXPORT_DIR):
        os.makedirs(EXPORT_DIR)
        print(f"Created directory: {EXPORT_DIR}")

def clean_filename(name):
    # Same logic as PHP/JS to keep consistent
    name = re.sub(r'[^a-zA-Z0-9æøåÆØÅ]+', '_', name)
    name = re.sub(r'_+', '_', name).strip('_')
    return name

def optimize_image(content, save_path):
    try:
        img = Image.open(BytesIO(content))
        
        # Convert to RGB (in case of PNG/RGBA)
        if img.mode in ('RGBA', 'P'):
            img = img.convert('RGB')
            
        # Resize if too large
        if img.width > MAX_WIDTH:
            ratio = MAX_WIDTH / img.width
            new_height = int(img.height * ratio)
            img = img.resize((MAX_WIDTH, new_height), Image.Resampling.LANCZOS)
            
        # Save as optimized JPEG
        img.save(save_path, 'JPEG', quality=JPEG_QUALITY, optimize=True)
        return True
    except Exception as e:
        print(f"Error optimizing {save_path}: {e}")
        return False

def process_images():
    print(f"Loading {JSON_FILE}...")
    with open(JSON_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)

    print(f"Found {len(data['features'])} features.")
    
    total_images = 0
    download_errors = 0
    
    for feature in data['features']:
        props = feature['properties']
        fid = props['id']
        name = props['name']
        safe_name = clean_filename(name)
        
        # We need the REMOTE URLs (which are in 'images' or 'remoteImageUrl' 
        # but 'images' usually contains both assets and remote links)
        # However, convert_geojson.php populates 'images' with ALL urls found.
        
        # Let's look at 'links' + 'images' to find the candidate google photos
        # Actually, convert_geojson puts everything in 'images'.
        
        # We want to RE-download based on the URLs, but assign NEW local names.
        
        # Filter out assets/ stored in 'images'
        candidate_urls = []
        if 'images' in props:
            for url in props['images']:
                if 'assets/' in url or 'mining_map' in url:
                    continue # Skip assets
                if 'bilder/' in url:
                    continue # Skip existing local paths (we are resolving source)
                # It's a remote URL
                candidate_urls.append(url)
        
        # Clean candidates
        candidate_urls = list(dict.fromkeys(candidate_urls)) # Unique
        
        new_local_images = []
        
        # Also preserve ASSETS in localImages list? 
        # The viewer expects localImages to contain the list to cycle through.
        # If we have assets, they should be in localImages too.
        
        current_assets = []
        if 'images' in props:
            for url in props['images']:
                if 'assets/' in url or 'mining_map' in url:
                    current_assets.append(url)
        
        for i, url in enumerate(candidate_urls):
            # Construction: Name_ID_Index.jpg
            # Index is 1-based
            filename = f"{safe_name}_{fid}_{i+1}.jpg"
            filepath = os.path.join(EXPORT_DIR, filename)
            
            # Download
            if not os.path.exists(filepath):
                print(f"Downloading [{fid}] {filename}...")
                try:
                    # Clean Google URL for High Res
                    dl_url = url
                    if 'googleusercontent.com' in url and 'fife=s' not in url:
                        if '?' in url: dl_url = url.split('?')[0]
                        dl_url += '?fife=s16383'
                        
                    r = requests.get(dl_url, timeout=30)
                    if r.status_code == 200:
                        if optimize_image(r.content, filepath):
                            total_images += 1
                        else:
                            download_errors += 1
                    else:
                        print(f"Failed to download {url}: {r.status_code}")
                        download_errors += 1
                except Exception as e:
                    print(f"Exception downloading {url}: {e}")
                    download_errors += 1
            else:
                # Exists locally
                total_images += 1
            
            # Add to list
            new_local_images.append(f"bilder/{filename}")

        # Merge assets back into localImages
        # Order: Images first, then assets? Or maintain order?
        # Let's put images first.
        final_local_list = new_local_images + current_assets
        
        # Update JSON
        props['localImages'] = final_local_list
        # Update imageUrl (main image)
        if final_local_list:
            props['imageUrl'] = final_local_list[0]
        
    
    # Save updated JSON
    print(f"Saving updated {JSON_FILE}...")
    with open(JSON_FILE, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
        
    print(f"Done! {total_images} images processed. {download_errors} errors.")

if __name__ == "__main__":
    setup_dir()
    process_images()
