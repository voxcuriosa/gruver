import json
import os
import ftplib

# FTP Settings (from deploy.py)
FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/gruver/bilder"

def cleanup():
    json_path = 'full_data.json'
    bilder_dir = 'bilder'
    
    if not os.path.exists(json_path):
        print("Error: full_data.json not found")
        return

    # 1. Get referenced files
    referenced_files = set()
    with open(json_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
        for feature in data.get('features', []):
            for img_path in feature.get('properties', {}).get('localImages', []):
                referenced_files.add(os.path.basename(img_path))
    
    # 2. Identify local orphans
    local_files = [f for f in os.listdir(bilder_dir) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
    orphans = [f for f in local_files if f not in referenced_files]
    
    if not orphans:
        print("No local orphans found.")
        return

    print(f"Found {len(orphans)} orphans to delete.")
    
    # 3. Delete locally
    for orphan in orphans:
        try:
            os.remove(os.path.join(bilder_dir, orphan))
            # print(f"Deleted local: {orphan}")
        except Exception as e:
            print(f"Failed to delete local {orphan}: {e}")
            
    print("Local cleanup complete.")

    # 4. Clean up remotely (FTP)
    try:
        print(f"Connecting to {FTP_HOST} for remote cleanup...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.cwd(REMOTE_DIR)
        
        # Get remote file list
        remote_files = ftp.nlst()
        remote_orphans = [f for f in remote_files if f in orphans] # Only delete what we found as orphans locally
        
        print(f"Found {len(remote_orphans)} corresponding orphans on server.")
        
        for ro in remote_orphans:
            try:
                ftp.delete(ro)
                # print(f"Deleted remote: {ro}")
            except Exception as e:
                print(f"Failed to delete remote {ro}: {e}")
        
        ftp.quit()
        print("Remote cleanup complete.")
        
    except Exception as e:
        print(f"FTP Remote cleanup failed: {e}")

if __name__ == "__main__":
    cleanup()
