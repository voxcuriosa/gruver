import json
import os
import sys
from upload_ftp import FTP_HOST, FTP_USER, FTP_PASS
from ftplib import FTP

SERVER_DIR = "public_html/gruver/albumgooglephotos"
REGISTRY_FILE = "sync_registry.json"

def cleanup_server():
    # 1. Load local files (Source of Truth)
    LOCAL_DIR = "albumgooglephotos"
    if not os.path.exists(LOCAL_DIR):
        print(f"Local directory {LOCAL_DIR} not found!")
        return

    active_files = set(os.listdir(LOCAL_DIR))
    
    print(f"Loaded {len(active_files)} active files from local directory: {LOCAL_DIR}")

    # 2. Connect to FTP
    ftp = FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    print(f"Connected to {FTP_HOST}")

    # 3. List server files
    try:
        ftp.cwd(SERVER_DIR)
    except Exception as e:
        print(f"Could not change directory to {SERVER_DIR}: {e}")
        ftp.quit()
        return

    server_files = []
    ftp.retrlines('NLST', server_files.append)
    print(f"Found {len(server_files)} files on server.")

    # 4. Identify orphans
    orphans = []
    for filename in server_files:
        if filename not in active_files and (filename.endswith('.jpg') or filename.endswith('.mp4')):
            orphans.append(filename)
    
    print(f"indetified {len(orphans)} orphaned files to delete.")

    # 5. Delete orphans
    if orphans:
        print("Deleting orphaned files...")
        batch_size = 50
        count = 0
        for filename in orphans:
            try:
                ftp.delete(filename)
                count += 1
                if count % batch_size == 0:
                    print(f"Deleted {count}/{len(orphans)}...")
            except Exception as e:
                print(f"Failed to delete {filename}: {e}")
        print("Cleanup complete.")
    else:
        print("No files to clean up.")

    ftp.quit()

if __name__ == "__main__":
    cleanup_server()
