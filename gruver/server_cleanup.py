import ftplib

# FTP Settings
FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/gruver"

FILES_TO_DELETE = [
    "historical_maps_investigation.log",
    "file_list.txt",
    "remove_handle_text.py",
    "sjekk_historiske_kart.html"
]

def run_cleanup():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Login successful.")

        ftp.cwd(REMOTE_DIR)
        print(f"Changed directory to {REMOTE_DIR}")

        for filename in FILES_TO_DELETE:
            try:
                ftp.delete(filename)
                print(f" [DELETED] {filename}")
            except ftplib.error_perm as e:
                if str(e).startswith('550'):
                    print(f" [SKIP] {filename} not found on server")
                else:
                    print(f" [ERROR] Could not delete {filename}: {e}")
            except Exception as e:
                print(f" [ERROR] Error deleting {filename}: {e}")

        ftp.quit()
        print("Operation complete.")
    except Exception as e:
        print(f"FTP Error: {e}")

if __name__ == "__main__":
    run_cleanup()
