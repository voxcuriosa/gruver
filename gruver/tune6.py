import cv2
import numpy as np

def score_kiln(gray, x, y, r):
    n = 16
    samples = []
    
    for i in range(n):
        a = (i / n) * 2 * np.pi
        sx = int(x + np.cos(a) * r)
        sy = int(y + np.sin(a) * r)
        samples.append(int(gray[sy, sx]))
        
    c = int(gray[y, x])
    
    # Northwest index = 10 (approx 225 deg if 0=Right)
    # Southeast index = 2 (approx 45 deg)
    # Northeast index = 14
    # Southwest index = 6
    
    nw_val = samples[10]
    se_val = samples[2]
    sw_val = samples[6]
    ne_val = samples[14]
    
    contrast = se_val - nw_val
    
    # 1. Very strong NW/SE contrast (Light/Shadow gradient)
    if contrast > 40: 
        # 2. SE must be brighter than center, NW must be darker
        if nw_val < c < se_val:
            # 3. Flatter perpendicular axis
            perp_diff = abs(sw_val - ne_val)
            if perp_diff < 20:
                # 4. Ring structure: Ensure it's not just a straight cliff
                # The sides (East, West, N, S) should be somewhere in between NW and SE
                w_val = samples[8]
                e_val = samples[0]
                n_val = samples[12]
                s_val = samples[4]
                
                # West should be darker than East (generally) since light is from NW
                if e_val - w_val > 10:
                    return 1.0 + (contrast/255.0)
    return 0
    
img = cv2.imread("data/funn/debug_tile.png")
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
h, w = gray.shape

hits = 0
out = img.copy()

radii = [5, 6, 7]
# Only check every 4 pixels to cluster naturally
for y in range(10, h - 10, 4):
    for x in range(10, w - 10, 4):
        best_score = 0
        best_r = 0
        for r in radii:
            s = score_kiln(gray, x, y, r)
            if s > best_score:
                best_score = s
                best_r = r
                
        if best_score > 0:
            cv2.circle(out, (x, y), best_r, (0, 0, 255), 2)
            cv2.circle(out, (x, y), 1, (0, 255, 0), 2)
            hits += 1

print(f"Ultra-Strict Hits: {hits}")
cv2.imwrite("data/funn/tuned_output_6.png", out)
