import cv2
import numpy as np
import urllib.request
import os

def detect_circles_php_style(img_path, min_rad, max_rad):
    img = cv2.imread(img_path)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    # The PHP script applies -15 contrast which maps roughly to 0.85 alpha in OpenCV
    # but we'll just use the raw grayscale for now as it's close enough.
    h, w = gray.shape

    out_img = img.copy()
    found = 0
    
    radii = list(range(min_rad, max_rad + 1))
    step = 3
    
    thresh = 22 # Testing threshold
    
    for r in radii:
        for y in range(r + 5, h - r - 5, step):
            for x in range(r + 5, w - r - 5, step):
                center = int(gray[y, x])
                
                num_samples = 16
                hits = 0
                total_contrast = 0
                quadrants = [0, 0, 0, 0]
                
                for i in range(num_samples):
                    a = (i / num_samples) * 2 * np.pi
                    sx = int(x + np.cos(a) * r)
                    sy = int(y + np.sin(a) * r)
                    
                    v = int(gray[sy, sx])
                    diff = abs(v - center)
                    
                    if diff > thresh:
                        hits += 1
                        total_contrast += diff
                        quad = int((a / (2 * np.pi)) * 4)
                        if quad > 3: quad = 3
                        quadrants[quad] += 1
                        
                active_quads = sum(1 for q in quadrants if q >= 1)
                
                if hits >= 12 and active_quads == 4:
                    cv2.circle(out_img, (x, y), r, (0, 0, 255), 1)
                    found += 1
                    
    print(f"Hits with thresh={thresh}, hits>=12: {found}")
    cv2.imwrite("data/funn/tuned_output.png", out_img)

if not os.path.exists("data/funn/debug_tile.png"):
    urllib.request.urlretrieve('https://voxcuriosa.no/gruver/data/funn/debug_tile.png', 'data/funn/debug_tile.png')
    
detect_circles_php_style("data/funn/debug_tile.png", 5, 10)
