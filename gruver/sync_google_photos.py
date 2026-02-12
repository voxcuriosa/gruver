import os
import requests
import re
import hashlib
import json
import time
import sys

# Configuration
DEFAULT_ALBUM_URL = "https://photos.app.goo.gl/QkDQDJ5XMjqQcnvo7"
TARGET_DIR = "albumgooglephotos"
REGISTRY_FILE = "sync_registry.json"

def get_image_hash(data):
    """Generate SHA256 hash for image data."""
    return hashlib.sha256(data).hexdigest()

def sync_album(album_url=None):
    if not album_url:
        album_url = DEFAULT_ALBUM_URL

    if not os.path.exists(TARGET_DIR):
        os.makedirs(TARGET_DIR)
        print(f"Created directory {TARGET_DIR}")

    # Load registry
    if os.path.exists(REGISTRY_FILE):
        with open(REGISTRY_FILE, 'r') as f:
            registry = json.load(f)
    else:
        registry = {}

    print(f"Fetching album at {album_url}...")
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(album_url, timeout=30, headers=headers)
        response.raise_for_status()
        content = response.text
        
        # Sjekk om vi faktisk fikk innhold
        if len(content) < 1000:
            print("Warning: Received very short response. Content might be blocked or require JS.")
        
        with open("album_raw.html", "w", encoding="utf-8") as f:
            f.write(content)
        print("Saved album_raw.html for debugging.")

    except Exception as e:
        print(f"Failed to fetch album: {e}")
        return

    # Extract image URLs
    url_patterns = [
        r'[\'"](https://lh3\.googleusercontent\.com/[a-zA-Z0-9\-_/]{60,})[\'"]'
    ]
    
    candidate_urls = []
    for pattern in url_patterns:
        matches = re.findall(pattern, content)
        for u in matches:
            # Clean up URLs
            u = u.split('"')[0].split('\\')[0].split(',')[0]
            if len(u) < 60: continue
            candidate_urls.append(u)
    
    # Filter out duplicates while preserving order
    unique_urls = []
    seen = set()
    for u in candidate_urls:
        if u not in seen:
            unique_urls.append(u)
            seen.add(u)
    
    candidate_urls = unique_urls
    print(f"Found {len(candidate_urls)} candidate media URLs.")

    new_count = 0
    updated_count = 0
    valid_hashes = set()
    for i, url in enumerate(candidate_urls):
        try:
            print(f"Processing media {i+1}/{len(candidate_urls)}...")
            
            ext = '.jpg'
            
            # Fetch content for hashing
            # For images, we use a slightly larger size to get a stable hash that isn't just a tiny thumbnail
            # but small enough to be fast.
            fetch_url = url + "=w500"
            img_response = requests.get(fetch_url, timeout=15)
            img_response.raise_for_status()
            img_data = img_response.content
            
            img_hash = get_image_hash(img_data)
            
            # Exclusion list (e.g. default letter icons)
            EXCLUDED_HASHES = {
                "6e27a4b683ce9294d10a9e5025a9f5f8c510b10ec68a31633104c9ce802b368b", # 't' icon
                "2664fdb40cf54a439366c3cf446b2b6d0934bf93deb070aa85128598da2a2bdb", # 'L' icon
                "93589b6e12de49eced04ef7df0d15be06e471c2afb2225f5cae0402d07d7a02c", # 't' icon variation (photo_1770663075_230)
                # User excluded images (photo_1770663446_297, etc.)
                "e06642123dba43ee1281cc85bb9840772cf4c6f8c64ec4aea89d3512acdf067c",
                "cbd68f976682cd2cad7a26f0f42a764be37deeb217b34f0fb3d855e2c7c4dc87",
                "53fd757f6081f96ce7935221edcca099341331f8685ed8d2ea97585acf923e92",
                "38d35f70e2c2d3af710f3f8161b52c920b0940f5c9e2799f1d74617168dcca0c",
                "2ebb3ad24564b11a572c11e28364b4ef81cc3a7b6f29b90bf5cfc327d71d5e87",
                "b94293d1ff1c108903e7341ade5f02b027e91b50bb0f388cd2c439d52c076ffb",
                "7dab55cbec6ff89290ba089102d328ad9f5bdff0aef33830ffdca447fe3f91f2",
                "f04dfe17a1c691b67fe95765321efc49f3b273f05777eb4c81d07b286ed953df",
                "86bcf298be1e16dc78ce618277243d9be9b833f475445366c13b25777f48fa67",
                "f7fe905d7d386a8dda8987ab5276df5895279782851a62d6bf5b995629fdd40d",
                "cc140891d427b9c0a1b6dda9352f25270e22d1496599a07bbbc0aad11d3a8a04",
                "4bbfc21a1e161a9c2d019859dec3ebe91c587730888251814c2add9568299fb8",
                "6cfa41e3c22d97bfd04aadb6a5b15c040609870a233aafd42b35171af288d389",
                # New hashes found by index matching (hash drift correction)
                "37b8ab85d09c23f7ccce0179e77eca21a0a8759ebba26505b07699b21053b14b",
                "712467f0c25c961868fdf170882476cbb5ef6b84be0e4cb60a802345f6499f34",
                "cda7eab1483d6cbacf8cc39f19c76bbd4861c1b81ce6c548d9f959131466d8fb",
                "2e61b76a336180706741e55be5484daeb832be290399dad0641960b6a48d859c",
                "f3ce13e6a26854a542a06467e923e0903ba39ad0b55aa9eb8999bcf2ff31a01e",
                "8cf83a1cf6f94929af1431e28da154ee4d8b34a799870c20717444cf97eba9d6",
                "addced2cfd30a766fcd9fa97f3e12088166635b037f9e5cf5cf85d32d9a34f31",
                "ef9caaafb2c62a4f824acf299028d6b014504ac846908a07c2352f4b5e269af6",
                "1f50e60cc9eacb2bb775e980153d7ae0d249f4e8827c17b8a8024e8a98c6016c",
                "69c8cc8be57e7ba78778395d2f4668d56d10500ac2c891a576a204e982950296",
                "2f9d7a288171004d95fac35114ad409224f9aef7ab398ade4f1eeebf3355e2ab",
                # User excluded images (photo_1770674695_215 and photo_1770674641_181)
                "6fb2bff338370687b22b69a53d34930327f276629546c09a41afa7f35fafd697",
                "47ed57494a6154ae370aeabddcfb316d8e2e325cc2b88bd4695b827623a3c68f",
            }
            
            if img_hash in EXCLUDED_HASHES:
                print(f"Skipping excluded image (hash match): {img_hash}")
                continue

            valid_hashes.add(img_hash)
            
            filename = f"photo_{int(time.time())}_{i}{ext}"
            thumbnail_filename = None
            
            if img_hash in registry:
                item = registry[img_hash]
                filename = item['filename']
                if thumbnail_filename and not os.path.exists(os.path.join(TARGET_DIR, thumbnail_filename)):
                    print(f"Thumbnail missing for {filename}, will regenerate.")
                    thumbnail_filename = None
                
            file_path = os.path.join(TARGET_DIR, filename)
            if not os.path.exists(file_path):
                with open(file_path, 'wb') as f:
                    f.write(img_data)
            
            # Generate thumbnail if missing
            if not thumbnail_filename:
                thumb_filename = filename.replace('.jpg', '_thumb.jpg')
                thumb_path = os.path.join(TARGET_DIR, thumb_filename)
                if not os.path.exists(thumb_path):
                    thumb_response = requests.get(url + "=w500-h500-c", timeout=15)
                    with open(thumb_path, 'wb') as f:
                        f.write(thumb_response.content)
                thumbnail_filename = thumb_filename
            
            registry[img_hash] = {
                "filename": filename,
                "thumbnail": thumbnail_filename,
                "url": url,
                "type": "image",
                "timestamp": time.time()
            }
            new_count += 1
            print(f"Registry entry for {filename}")

        except Exception as e:
            print(f"Error processing item {i+1}: {e}")

        # Periodically save registry
        if (i + 1) % 10 == 0:
            with open(REGISTRY_FILE, 'w') as f:
                json.dump(registry, f, indent=4)

    # Prune registry: Remove entries that were not seen in this run
    print("Pruning stale registry entries...")
    keys_to_delete = [k for k in registry.keys() if k not in valid_hashes]
    for k in keys_to_delete:
        print(f"Removing stale entry: {registry[k]['filename']}")
        del registry[k]

    # Final save
    with open(REGISTRY_FILE, 'w') as f:
        json.dump(registry, f, indent=4)

    print(f"Sync complete. {new_count} items processed ({updated_count} updated).")
    
    # Cleanup orphaned files
    cleanup_media(registry)

def cleanup_media(registry):
    """Remove files in TARGET_DIR that are not in the registry."""
    print("Starting cleanup of orphaned media...")
    active_files = set()
    for item in registry.values():
        active_files.add(item['filename'])
        if item.get('thumbnail'):
            active_files.add(item['thumbnail'])
    
    deleted_count = 0
    for filename in os.listdir(TARGET_DIR):
        if filename not in active_files:
            file_path = os.path.join(TARGET_DIR, filename)
            try:
                os.remove(file_path)
                deleted_count += 1
            except Exception as e:
                print(f"Failed to delete {filename}: {e}")
    
    print(f"Cleanup finished. Removed {deleted_count} orphaned files.")

if __name__ == "__main__":
    url = sys.argv[1] if len(sys.argv) > 1 else None
    sync_album(url)
