import ftplib

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"

def cleanup():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        
        print("Listing public_html contents:")
        ftp.cwd("public_html")
        items = ftp.nlst()
        for item in items:
            print(f" - {item}")
            
        # Specific cleanup for the likely "index.html" upload from my previous mistake
        if "index.html" in items:
             # Check if it was uploaded recently or if it's the intended portal index
             # Actually, the portal index is usually in the root. 
             # But if I uploaded it from the 'gruver' folder and it didn't exist there, 
             # then nothing was uploaded.
             pass
             
        ftp.quit()
    except Exception as e:
        print(f"Cleanup check failed: {e}")

if __name__ == "__main__":
    cleanup()
