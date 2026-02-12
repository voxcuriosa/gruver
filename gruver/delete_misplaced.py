import ftplib

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"

def delete_misplaced():
    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.cwd("public_html")
        
        filename = "viewer.js"
        print(f"Deleting {filename} from public_html root...")
        ftp.delete(filename)
        print("Delete successful.")
        
        ftp.quit()
    except Exception as e:
        print(f"Delete failed: {e}")

if __name__ == "__main__":
    delete_misplaced()
