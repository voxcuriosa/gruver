import ftplib
import os
import subprocess
import sys

# FTP Settings
FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
# Password is read from the macOS Keychain (never stored in git).
# Store/update it with:
#   security add-generic-password -a cpjvfkip -s voxcuriosa-ftp -w 'NEW_PASSWORD' -U
def _read_ftp_password():
    env_pw = os.environ.get("VOXCURIOSA_FTP_PASS")
    if env_pw:
        return env_pw
    result = subprocess.run(
        ["security", "find-generic-password", "-a", FTP_USER, "-s", "voxcuriosa-ftp", "-w"],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        sys.exit("FTP password not found in Keychain (service: voxcuriosa-ftp).")
    return result.stdout.strip()

FTP_PASS = _read_ftp_password()
REMOTE_DIR_BASE = "public_html" 

def upload_path(local_path, remote_path, ftp):
    """Uploads a file or directory recursively."""
    if os.path.isfile(local_path):
        try:
            with open(local_path, "rb") as f:
                print(f"Uploading {local_path} to {remote_path}...")
                ftp.storbinary(f"STOR {remote_path}", f)
        except Exception as e:
            print(f"Failed to upload {local_path}: {e}")
            
    elif os.path.isdir(local_path):
        try:
            ftp.mkd(remote_path)
            print(f"Created directory {remote_path}")
        except:
            pass # Directory likely exists
        
        for item in os.listdir(local_path):
            if item in ["data.json", "backup"]:
                print(f"Skipping {item} (Excluded from FTP)")
                continue
            local_item = os.path.join(local_path, item)
            remote_item = f"{remote_path}/{item}"
            upload_path(local_item, remote_item, ftp)

if __name__ == "__main__":
    local_target = "index.html"
    remote_subdir = "" 

    if len(sys.argv) > 1:
        local_target = sys.argv[1]
    
    if len(sys.argv) > 2:
        remote_subdir = sys.argv[2]

    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Login successful.")

        base_remote = f"{REMOTE_DIR_BASE}/{remote_subdir}".strip("/")
        
        # If uploading a directory, we need to append the directory name to remote path unless it's root
        if os.path.isdir(local_target):
             final_remote = f"{base_remote}/{os.path.basename(local_target)}".strip("/")
             # Check if we want to merge contents or put dir inside. The simple way is:
             # python upload_ftp.py notebooks -> public_html/notebooks
             upload_path(local_target, final_remote, ftp)
        else:
             final_remote = f"{base_remote}/{os.path.basename(local_target)}".strip("/")
             upload_path(local_target, final_remote, ftp)

        print("Operation complete.")
        ftp.quit()
    except Exception as e:
        print(f"FTP Error: {e}")
