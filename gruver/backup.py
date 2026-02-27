import shutil
import os
import ftplib
import time
from datetime import datetime

# FTP Settings (Copied from upload_ftp.py)
FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_ASSETS_DIR = "public_html/gruver/assets"
REMOTE_GRUVER_DIR = "public_html/gruver"

# Files to backup based on active project state
FILES_TO_BACKUP = [
    'index.php',
    'viewer.js',
    'nib_proxy.php',
    'kultur_proxy.php',
    'proxy_xml.php',
    'image_proxy.php',
    'tile_proxy.php',
    'elevation_proxy.php',
    'proxy.php',
    'deploy.py',
    'upload_ftp.py',
    'export_images_v2.py',
    'sync_smart.php',
    'sync_google_photos.py',
    'manifest.json',
    'sw.js',
    'BACKUP_INFO.md',
    'backup.py',
    'georef.php',
    'georef_v2_stable.php',
    'upload.php',
    'save_georef.php',
    'published_maps.json',
    'full_data.json',
    'full_data.kml',
    'kanalanlegg_utf8.html',
    'auth_v2.php',
    'server_cleanup.py',
    'check_limits.php',
    'check_ip.php',
    'admin_check.php',
    'admin_tools.js',
    'analyze_area.php',
    'lidarfunn.php',
    'update_coord.php',
    'undo_coord.php',
    'get_changelog.php',
    'save_override.php',
    'undo_override.php',
    'overrides.json',
    'changelog.json',
    'local_points.json',
    'pending_points.json',
    'add_point.php',
    'approve_point.php',
    'reject_point.php',
    'edit_local_point.php',
    'delete_local_point.php',
    'extract_bounds.py',
    'nib_bounds.json',
    'assets' # Directory
]

BACKUP_DIR = 'backup'

def get_next_version_dir(base_dir):
    """Finds the next available v1, v2, etc. directory."""
    i = 1
    while True:
        v_dir = os.path.join(base_dir, f'v{i}')
        if not os.path.exists(v_dir):
            return v_dir
        i += 1

def sync_assets_from_ftp():
    """Downloads missing or newer files from FTP assets folder to local assets folder."""
    print("\n--- Checking FTP for new assets ---")
    
    if not os.path.exists('assets'):
        os.makedirs('assets')
        print("Created local 'assets' directory.")

    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print(f"Connected to {FTP_HOST}")
        
        try:
            ftp.cwd(REMOTE_ASSETS_DIR)
        except ftplib.error_perm:
            print(f"Remote directory {REMOTE_ASSETS_DIR} does not exist. Skipping sync.")
            ftp.quit()
            return

        remote_files = ftp.nlst()
        
        for filename in remote_files:
            if filename in ['.', '..']: continue
            
            local_path = os.path.join('assets', filename)
            
            # Check if we should download
            should_download = False
            if not os.path.exists(local_path):
                should_download = True
                print(f" [NEW] {filename}")
            else:
                # Simple size check (modification time is unreliable via simple FTP listing)
                # For a robust sync we would use MDTM, but let's stick to size/existence for now 
                # to avoid re-downloading everything constantly.
                try:
                    remote_size = ftp.size(filename)
                    local_size = os.path.getsize(local_path)
                    if remote_size != local_size:
                        should_download = True
                        print(f" [UPDATE] {filename} (Size: {local_size} -> {remote_size})")
                except:
                    pass # Some servers don't support SIZE

            if should_download:
                try:
                    with open(local_path, 'wb') as f:
                        ftp.retrbinary(f"RETR {filename}", f.write)
                    print(f"    Downloaded {filename}")
                except Exception as e:
                    print(f"    Failed to download {filename}: {e}")
        
        # 3. Sync data JSONs from root (overrides and changelog)
        ftp.cwd(f"/{REMOTE_GRUVER_DIR}")
        for filename in ['overrides.json', 'changelog.json', 'local_points.json', 'pending_points.json']:
            try:
                if filename in ftp.nlst():
                    should_dl = False
                    if not os.path.exists(filename):
                        should_dl = True
                    else:
                        if ftp.size(filename) != os.path.getsize(filename):
                            should_dl = True
                    
                    if should_dl:
                        print(f" [SYNC] {filename} from server...")
                        with open(filename, 'wb') as f:
                            ftp.retrbinary(f"RETR {filename}", f.write)
            except:
                pass

        ftp.quit()
        print("Sync complete.\n")

    except Exception as e:
        print(f"FTP Sync Error: {e}")
        print("Continuing with backup of local files...\n")

def run_backup():
    # 1. Sync from FTP first
    sync_assets_from_ftp()

    # 2. Perform Local Backup
    print(f"--- Starting Versioned Backup: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} ---")
    
    if not os.path.exists(BACKUP_DIR):
        os.makedirs(BACKUP_DIR)
        print(f"Created backup directory: {BACKUP_DIR}")

    version_dir = get_next_version_dir(BACKUP_DIR)
    os.makedirs(version_dir)
    print(f"Target directory: {version_dir}")

    success_count = 0
    for filename in FILES_TO_BACKUP:
        if os.path.exists(filename):
            try:
                dest_path = os.path.join(version_dir, filename)
                
                if os.path.isdir(filename):
                    # Directory Copy
                    shutil.copytree(filename, dest_path)
                    print(f" [DIR] {filename} -> {dest_path}")
                else:
                    # File Copy
                    shutil.copy2(filename, dest_path)
                    print(f" [OK] {filename} -> {dest_path}")
                
                success_count += 1
            except Exception as e:
                print(f" [ERROR] Could not copy {filename}: {e}")
        else:
            print(f" [SKIP] {filename} not found in root directory")

    print(f"--- Backup Completed: {success_count}/{len(FILES_TO_BACKUP)} items backed up to {version_dir} ---")

if __name__ == "__main__":
    run_backup()
