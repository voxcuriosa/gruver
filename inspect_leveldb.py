import os

search_dir = r"C:\Users\chr1203\AppData\Roaming\Antigravity\Local Storage\leveldb"
uuid = "1c4526bb-b52a-4399-923a-a804bf22958c"

found = False
for root, dirs, files in os.walk(search_dir):
    for file in files:
        file_path = os.path.join(root, file)
        try:
            with open(file_path, "rb") as f:
                content = f.read()
                if uuid.encode("ascii") in content:
                    print(f"Found UUID in {file_path}")
                    found = True
        except Exception as e:
            pass

if not found:
    print("UUID not found in leveldb.")
