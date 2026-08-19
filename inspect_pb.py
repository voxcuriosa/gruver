import re

file_path = r"C:\Users\chr1203\.gemini\antigravity\user_settings.pb"

try:
    with open(file_path, "rb") as f:
        content = f.read()
        # Find all sequences of printable characters
        strings = re.findall(rb"[\x20-\x7E]{4,}", content)
        for s in strings:
            print(s.decode("ascii", errors="ignore"))
except Exception as e:
    print(f"Error: {e}")
