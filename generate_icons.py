from PIL import Image
import os

# Source image
source = r"C:/Users/chr1203/.gemini/antigravity/brain/9b7592aa-70eb-439a-af42-cf2555bb9480/voxcuriosa_icon_1768947410611.png"
output_dir = r"c:/Users/chr1203/OneDrive - Telemark fylkeskommune/Jobb/Diverse/Antigravity/vox_portal"

# Open source image
img = Image.open(source)

# Generate different sizes
sizes = [
    (512, 512, "icon-512.png"),
    (192, 192, "icon-192.png"),
    (32, 32, "favicon.ico"),
    (180, 180, "apple-touch-icon.png")
]

for width, height, filename in sizes:
    resized = img.resize((width, height), Image.Resampling.LANCZOS)
    output_path = os.path.join(output_dir, filename)
    resized.save(output_path)
    print(f"Created: {filename}")

print("All icons generated successfully!")
