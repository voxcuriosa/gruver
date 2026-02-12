import ftplib
import os

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/gruver"

files_to_upload = [
    "index.php", 
    "viewer.js", 
    "full_data.json",
    "sw.js",
    "manifest.json",
    "nib_proxy.php", 
    "kultur_proxy.php", 
    "proxy_xml.php", 
    "elevation_proxy.php",
    "image_proxy.php",
    "tile_proxy.php",
    "bygdeborger.pdf"
]

def deploy():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        
        print(f"Changing directory to {REMOTE_DIR}...")
        ftp.cwd(REMOTE_DIR)
        
        for filename in files_to_upload:
            if os.path.exists(filename):
                print(f"Uploading {filename}...")
                with open(filename, "rb") as f:
                    ftp.storbinary(f"STOR {filename}", f)
                print(f"Successfully uploaded {filename}")
            else:
                print(f"Warning: {filename} not found locally.")
                
        ftp.quit()
        print("Deployment completed successfully.")
    except Exception as e:
        print(f"Deployment failed: {e}")

if __name__ == "__main__":
    deploy()
