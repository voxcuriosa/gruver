import json
import os
import ftplib

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/gruver/bilder"

def check_images():
    # 1. Load Local Data
    print("Loading full_data.json...")
    with open('full_data.json', 'r', encoding='utf-8') as f:
        data = json.load(f)

    local_refs = set()
    for feature in data['features']:
        props = feature['properties']
        if 'localImages' in props:
            for img in props['localImages']:
                # img is like "bilder/Dagstrosse_1.jpg"
                # We want just the filename "Dagstrosse_1.jpg"
                if img.startswith('bilder/'):
                    local_refs.add(img.split('/')[-1])
                elif '/' not in img:
                     local_refs.add(img)

    print(f"Found {len(local_refs)} unique image references in JSON.")

    # 2. Check existence in local folder
    local_dir = 'bilder'
    if not os.path.exists(local_dir):
        print(f"Local directory {local_dir} does not exist!")
        return

    local_files = set(os.listdir(local_dir))
    
    missing_locally = []
    present_locally = []

    for img in local_refs:
        if img in local_files:
            present_locally.append(img)
        else:
            missing_locally.append(img)

    print(f"Locally present: {len(present_locally)}")
    print(f"Locally missing (referenced but not found): {len(missing_locally)}")
    if missing_locally:
        print(f"Example missing local: {missing_locally[:5]}")

    # 3. Check Server Properties
    print(f"Connecting to FTP {FTP_HOST}...")
    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.cwd(REMOTE_DIR)
        
        server_files = set(ftp.nlst())
        
        print(f"Server has {len(server_files)} files in {REMOTE_DIR}.")

        # 4. Compare
        missing_on_server = []
        conflicts = [] # Should not be possible if missing, but checking collision

        for img in present_locally:
            if img not in server_files:
                missing_on_server.append(img)
            else:
                # Exists on server
                pass
        
        print(f"---------------------------------------------------")
        print(f"IMAGES REFERENCED IN JSON BUT MISSING ON SERVER: {len(missing_on_server)}")
        print(f"---------------------------------------------------")
        for img in missing_on_server:
            print(f"- {img}")

        # Check specifically for conflict
        target_file = "Dagstrosse_1.jpg"
        if target_file in server_files:
             print(f"\nWARNING: {target_file} ALREADY EXISTS on server!")
             try:
                 size = ftp.size(target_file)
                 print(f"Server file size: {size} bytes")
                 local_size = os.path.getsize(os.path.join(local_dir, target_file))
                 print(f"Local file size: {local_size} bytes")
                 if size != local_size:
                     print("SIZES DIFFER!")
             except Exception as e:
                 print(f"Could not get size: {e}")
        else:
             print(f"\nConfirmation: {target_file} does NOT exist on server (Safe to upload).")

        ftp.quit()

    except Exception as e:
        print(f"FTP Error: {e}")

if __name__ == "__main__":
    check_images()
