import shutil
import os
from datetime import datetime

# Files to backup based on BACKUP_INFO.md + recent additions
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
    'export_images.py',
    'compare_coords.py',
    'compare_kml.py',
    'get_missing.py',
    'migrate_images.php',
    'sync.php',
    'sync_images_trigger.php',
    "convert_geojson.php",
    'sync_google_photos.py',
    'cleanup_server_media.py',
    'patch_viewer.py community (intentional) community (intentional) community (intentional) community (intentional) community (intentional)',
    'BACKUP_INFO.md',
    'backup.py',
    'full_data.json',
    'full_data.kml',
    'full_data_server.kml'
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

def run_backup():
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
                dest_name = os.path.basename(filename)
                shutil.copy2(filename, os.path.join(version_dir, dest_name))
                print(f" [OK] {filename} -> {version_dir}/{dest_name}")
                success_count += 1
            except Exception as e:
                print(f" [ERROR] Could not copy {filename}: {e}")
        else:
            print(f" [SKIP] {filename} not found in root directory")

    print(f"--- Backup Completed: {success_count}/{len(FILES_TO_BACKUP)} files backed up to {version_dir} ---")

if __name__ == "__main__":
    run_backup()
