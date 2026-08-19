import cv2
import numpy as np

def score_kiln(gray, x, y, r):
    n = 8
    samples = []
    
    for i in range(n):
        a = (i / n) * 2 * np.pi
        sx = int(x + np.cos(a) * r)
        sy = int(y + np.sin(a) * r)
        samples.append(int(gray[sy, sx]))
        
    c = int(gray[y, x])
    
    # In OpenCV coordinates (y points down):
    # 0 = E, 1 = SE, 2 = S, 3 = SW, 4 = W, 5 = NW, 6 = N, 7 = NE
    # A pit with light from NW has shadow on its NW inner wall (Dark) and light on SE inner wall (Bright)
    
    nw_val = samples[5]
    se_val = samples[1]
    sw_val = samples[3]
    ne_val = samples[7]
    
    contrast = se_val - nw_val
    
    # Require strong contrast across the pit
    if contrast > 25:
        # Require pit to be flat on the perpendicular axis
        perp_diff = abs(sw_val - ne_val)
        if perp_diff < 15:
            # Require the center to be between the extremes
            if nw_val < c < se_val:
                return 1.0
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

print(f"Strict Shadow-Pattern hits: {hits}")
cv2.imwrite("data/funn/tuned_output_4.png", out)
