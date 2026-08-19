import ftplib
import sys

# FTP Settings
FTP_HOST = "voxcuriosa.no"
FTP_USER = "cpjvfkip"
FTP_PASS = "F2gw2FSXJcJLtk!"
REMOTE_DIR = "public_html/voxkunnskap/dist"

def remove_dir_recursive(ftp, path):
    """Recursively deletes a directory and its contents on FTP."""
    try:
        # Get contents
        items = ftp.nlst(path)
        for item in items:
            # Check if it's a file or directory by trying to change into it
            try:
                ftp.cwd(item)
                ftp.cwd("..")
                remove_dir_recursive(ftp, item)
            except:
                # If it's a file, delete it
                try:
                    print(f"Deleting file {item}...")
                    ftp.delete(item)
                except Exception as e:
                    print(f"Failed to delete file {item}: {e}")
        
        # Finally delete the empty directory
        print(f"Removing directory {path}...")
        ftp.rmd(path)
    except Exception as e:
        print(f"Error removing {path}: {e}")

if __name__ == "__main__":
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Login successful.")

        remove_dir_recursive(ftp, REMOTE_DIR)

        print("Cleanup complete.")
        ftp.quit()
    except Exception as e:
        print(f"FTP Error: {e}")
