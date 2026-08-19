import cv2
import numpy as np
import json

def score_kiln(gray, x, y, r):
    # DTM Hillshade rules (Light from Top-Left / NW usually)
    # A pit (kullmile) goes down:
    # Top-Left inner edge is shadow (Dark)
    # Bottom-Right inner edge is lit (Bright)
    # Center is flat (Mid)
    
    # Let's sample the circle in 8 directions (0=E, 1=SE, 2=S, 3=SW, 4=W, 5=NW, 6=N, 7=NE)
    n = 8
    samples = []
    
    for i in range(n):
        a = (i / n) * 2 * np.pi
        sx = int(x + np.cos(a) * r)
        sy = int(y + np.sin(a) * r)
        samples.append(int(gray[sy, sx]))
        
    c = int(gray[y, x])
    
    # 5 is NW (Top-Left) = 5/8 * 2pi = 225 deg (if 0 is Right, then pi is Left, pi/2 is Down... wait.
    # OpenCV y goes DOWN.
    # 0 = Right (E)
    # 1 = Bottom-Right (SE)
    # ...
    # 4 = Left (W)
    # 5 = Top-Left (NW)
    
    nw_val = samples[5]
    se_val = samples[1]
    w_val  = samples[4]
    e_val  = samples[0]
    n_val  = samples[6]
    s_val  = samples[2]
    
    # Pit signature: NW edge must be dark, SE edge must be bright
    # Let's check contrast
    contrast = se_val - nw_val 
    
    if contrast > 15: # Brightness diff
        # Check against center too
        if nw_val < c < se_val:
            # How circular is it? The left/right sides should be relatively flat (not a cliff)
            n_s_diff = abs(n_val - s_val)
            e_w_diff = abs(e_val - w_val)
            
            # If it's a perfect pit, NE/SW and E/W diffs are much smaller than NW/SE
            if n_s_diff < 15 and e_w_diff < 15:
                return 1.0 + (contrast/255.0)
                
    return 0

img = cv2.imread("data/funn/debug_tile.png")
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
h, w = gray.shape

hits = 0
out = img.copy()

for r in [5, 6, 7]:
    for y in range(r + 5, h - r - 5, 3):
        for x in range(r + 5, w - r - 5, 3):
            score = score_kiln(gray, x, y, r)
            if score > 0:
                cv2.circle(out, (x, y), r, (0, 0, 255), 1)
                hits += 1

print(f"Shadow-Pattern hits: {hits}")
cv2.imwrite("data/funn/tuned_output_3.png", out)
