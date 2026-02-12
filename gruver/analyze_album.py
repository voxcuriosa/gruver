import requests
import re
import os

ALBUM_URL = "https://photos.app.goo.gl/QkDQDJ5XMjqQcnvo7"

def fetch_and_analyze():
    print(f"Fetching {ALBUM_URL}...")
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    }
    r = requests.get(ALBUM_URL, headers=headers)
    content = r.text
    
    with open('album_source.txt', 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f"Saved source to album_source.txt ({len(content)} chars)")
    
    # Common Patterns
    # lh3.googleusercontent.com is for images
    # video-downloads.googleusercontent.com is for videos
    
    images = re.findall(r'https://lh3\.googleusercontent\.com/[a-zA-Z0-9\-_]{60,}', content)
    videos = re.findall(r'https://video-downloads\.googleusercontent\.com/[a-zA-Z0-9\-_]{60,}', content)
    pw_urls = re.findall(r'https://lh3\.googleusercontent\.com/pw/[a-zA-Z0-9\-_]{60,}', content)
    
    print(f"Found {len(images)} lh3 URLs")
    print(f"Found {len(videos)} video-downloads URLs")
    print(f"Found {len(pw_urls)} 'pw' URLs")

if __name__ == "__main__":
    fetch_and_analyze()
