import ftplib

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/Kunnskap"

def delete_dir_recursive(ftp, directory):
    try:
        ftp.cwd(directory)
        items = ftp.nlst()
        for item in items:
            if item in [".", ".."]: continue
            try:
                ftp.delete(item)
                print(f"Deleted file: {item}")
            except:
                delete_dir_recursive(ftp, item)
        ftp.cwd("..")
        ftp.rmd(directory)
        print(f"Deleted directory: {directory}")
    except Exception as e:
        print(f"Failed to delete {directory}: {e}")

if __name__ == "__main__":
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Login successful.")
        
        delete_dir_recursive(ftp, REMOTE_DIR)
        
        ftp.quit()
        print("Cleanup complete.")
    except Exception as e:
        print(f"Error: {e}")
