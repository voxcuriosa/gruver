import ftplib

FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"

def check_sizes():
    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.cwd("public_html")
        
        for filename in ["index.php", "viewer.js", "index.html"]:
            try:
                size = ftp.size(filename)
                print(f"{filename}: {size} bytes")
            except Exception as e:
                print(f"{filename}: Not found or error: {e}")
                
        ftp.quit()
    except Exception as e:
        print(f"Size check failed: {e}")

if __name__ == "__main__":
    check_sizes()
