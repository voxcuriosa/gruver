import ftplib
import os

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/gruver"

files_to_upload = [
    "index.php", 
    "viewer.js", 
    "log_interaction.php",
    "georef.php",
    "admin_check.php",
    "upload.php",
    "save_georef.php",
    "proxy_xml.php",
    "proxy.php",
    "image_proxy.php",
    "assets/bratsberg1919.png",
    "check_limits.php",
    "check_size.php",
    "lidarfunn.php",
    "save_training.php",
    "analyze_area.php",
    "clear_funn.php",
    "check_env.php",
    "check_env_v2.php",
    "info.php",
    "scripts/ai/detector.py",
    "admin_tools.js",
    "update_coord.php",
    "undo_coord.php",
    "get_changelog.php",
    "auth_v2.php",
    "check_badges.php",
    ".htaccess",
    "sw.js",
    "sync_smart.php",
    'diag_json.php'
]

def deploy():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        
        print(f"Changing directory to {REMOTE_DIR}...")
        ftp.cwd(REMOTE_DIR)
        
        # Ensure requested directory structure exists
        for folder in ["data/trening/kullmiler", "data/trening/gruver", "data/funn", "scripts/ai"]:
            parts = folder.split('/')
            curr = ""
            for part in parts:
                curr = f"{curr}/{part}" if curr else part
                try:
                    ftp.mkd(curr)
                except:
                    pass

        for filepath in files_to_upload:
            if os.path.exists(filepath):
                # Håndter mapper
                remote_path = filepath.replace("\\", "/")
                remote_dir = os.path.dirname(remote_path)
                
                if remote_dir:
                    parts = remote_dir.split('/')
                    curr = ""
                    for part in parts:
                        curr = f"{curr}/{part}" if curr else part
                        try:
                            ftp.mkd(curr)
                        except:
                            pass
                
                print(f"Uploading {filepath}...")
                with open(filepath, "rb") as f:
                    ftp.storbinary(f"STOR {remote_path}", f)
                print(f"Successfully uploaded {filepath}")
            else:
                print(f"Warning: {filepath} not found locally.")
                
        ftp.quit()
        print("Deployment completed successfully.")
    except Exception as e:
        print(f"Deployment failed: {e}")

if __name__ == "__main__":
    deploy()
