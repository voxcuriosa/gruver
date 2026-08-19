import os
import re

ga_snippet = '''<script async src="https://www.googletagmanager.com/gtag/js?id=G-YYPRYXPN70"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-YYPRYXPN70');
</script>'''

def process_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
        # Check if snippet already exists exactly
        if ga_snippet in content:
            print(f"Already exact in: {filepath}")
            return
            
        # Check for existing gtag and remove it
        content = re.sub(r'<!-- Google tag \(gtag\.js\) -->\s*<script async src="https://www\.googletagmanager\.com/gtag/js\?id=G-YYPRYXPN70"></script>\s*<script>\s*window\.dataLayer.*?</script>', '', content, flags=re.DOTALL)
        content = re.sub(r'<script async src="https://www\.googletagmanager\.com/gtag/js\?id=G-YYPRYXPN70"></script>\s*<script>\s*window\.dataLayer.*?</script>', '', content, flags=re.DOTALL)
        
        # Insert right after <head>
        new_content = re.sub(r'(<head(?:>|\s[^>]*>))', r'\1\n    ' + ga_snippet.replace('\n', '\n    '), content, count=1, flags=re.IGNORECASE)
        
        if content != new_content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"Updated: {filepath}")
        else:
            print(f"Could not find <head> in: {filepath}")
            
    except Exception as e:
        print(f"Error processing {filepath}: {e}")

for root, dirs, files in os.walk('.'):
    # skip backup folders
    if 'backup' in root.lower() or '.git' in root.lower():
        continue
    for file in files:
        if file in ['index.php', 'index.html']:
            process_file(os.path.join(root, file))

