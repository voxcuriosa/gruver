import cv2
import numpy as np

def detect_kilns(img_path):
    img = cv2.imread(img_path)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w = gray.shape

    out_img = img.copy()
    found = 0
    
    radii = [5, 6, 7]
    step = 3
    
    thresh = 15 # Contrast required for an edge pixel
    
    for r in radii:
        for y in range(r + 5, h - r - 5, step):
            for x in range(r + 5, w - r - 5, step):
                center = int(gray[y, x])
                
                num_samples = 16
                hits = 0
                quadrants = [0, 0, 0, 0]
                samples = []
                
                # Sample the 16 points around the circle
                for i in range(num_samples):
                    a = (i / num_samples) * 2 * np.pi
                    sx = int(x + np.cos(a) * r)
                    sy = int(y + np.sin(a) * r)
                    
                    v = int(gray[sy, sx])
                    samples.append(v)
                    diff = abs(v - center)
                    
                    if diff > thresh:
                        hits += 1
                        quad = int((a / (2 * np.pi)) * 4)
                        if quad > 3: quad = 3
                        quadrants[quad] += 1
                        
                active_quads = sum(1 for q in quadrants if q >= 1)
                
                # Rule 1: Must be mostly a full circle outline of contrast (at least 10/16 strong edges, in 3+ quadrants)
                if hits >= 10 and active_quads >= 3:
                    
                    # Rule 2: Must be a PIT (dark NW, bright SE)
                    # 16 samples. 0=Right(E). 
                    # NW is approx index 10 (225 deg)
                    # SE is approx index 2 (45 deg)
                    # NE is approx index 14 (315 deg)
                    # SW is approx index 6 (135 deg)
                    # In OpenCV Y points down, so:
                    # 0=Right, 4=Down, 8=Left, 12=Up.
                    # SE = 2, SW = 6, NW = 10, NE = 14
                    
                    nw_val = samples[10]
                    se_val = samples[2]
                    ne_val = samples[14]
                    sw_val = samples[6]
                    
                    # Ensure NW is dark, SE is bright (Shadow from top-left)
                    if se_val - nw_val > 20: 
                        # Ensure perpendicular axis is flatter than main axis
                        if abs(ne_val - sw_val) < abs(se_val - nw_val):
                            # Ensure center is between the two
                            if nw_val - 10 <= center <= se_val + 10:
                                cv2.circle(out_img, (x, y), r, (0, 0, 255), 1)
                                found += 1
                    
    print(f"Combined Strict Hits: {found}")
    cv2.imwrite("data/funn/tuned_output_5.png", out_img)

detect_kilns("data/funn/debug_tile.png")
