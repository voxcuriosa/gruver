import cv2
import numpy as np

def detect_kilns(img_path):
    img = cv2.imread(img_path)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # In hillshade, a pit looks like a bright crescent on one side (facing NW) 
    # and a dark crescent on the other (facing SE), or vice versa depending on sun angle.
    # It does NOT look like a uniform ring of high contrast.
    # It has a specific light-dark pattern. Let's try to capture that.
    
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    
    # Let's use HoughCircles but tune it locally to see if it even works on this image
    circles = cv2.HoughCircles(blur, cv2.HOUGH_GRADIENT, dp=1.0, minDist=10,
                              param1=40, param2=14, minRadius=5, maxRadius=10)
                              
    out_img = img.copy()
    if circles is not None:
        circles = np.uint16(np.around(circles))
        for i in circles[0, :]:
            cv2.circle(out_img, (i[0], i[1]), i[2], (0, 255, 0), 2)
            cv2.circle(out_img, (i[0], i[1]), 2, (0, 0, 255), 3)
            
    print(f"HoughCircles found: {0 if circles is None else len(circles[0])}")
    
    # Now let's try a custom PHP-ready algorithm that looks for the light-dark dipole
    h, w = gray.shape
    step = 3
    found_php = 0
    radii = [5, 6, 7, 8, 9, 10]
    
    for r in radii:
        for y in range(r + 5, h - r - 5, step):
            for x in range(r + 5, w - r - 5, step):
                # Sample 8 points around the circle
                samples = []
                for i in range(8):
                    a = (i / 8) * 2 * np.pi
                    sx = int(x + np.cos(a) * r)
                    sy = int(y + np.sin(a) * r)
                    samples.append(int(gray[sy, sx]))
                
                center = int(gray[y, x])
                
                # A charcoal kiln in hillshade usually has:
                # 1. A fairly flat/neutral center (~128 in hillshade)
                # 2. A rim that is brighter on one side (e.g. NW) and darker on the opposite side (SE)
                
                max_val = max(samples)
                min_val = min(samples)
                
                # We need significant contrast across the pit
                if max_val - min_val > 40:
                    # Find where the max and min are
                    max_idx = samples.index(max_val)
                    min_idx = samples.index(min_val)
                    
                    # They should be roughly opposite each other (index diff of ~3-5 out of 8)
                    idx_diff = min(abs(max_idx - min_idx), 8 - abs(max_idx - min_idx))
                    
                    if idx_diff >= 3:
                        # Ensure the center is somewhere between the extremes (it's a pit, not a ridge)
                        # Actually in a pit, the center is flat. The rim goes UP then DOWN.
                        # Wait, LiDAR DTM Skyggerelieff: a pit is a hole.
                        # Light from NW (top left).
                        # Top-left inner edge is DARK (shadow).
                        # Bottom-right inner edge is BRIGHT (lit).
                        
                        # So NW sample < Center < SE sample
                        # Let's just check if it has a strong dipole pattern and center is moderate
                        if 80 < center < 180:
                            cv2.circle(out_img, (x, y), r, (255, 0, 0), 1)
                            found_php += 1
                            
    print(f"Dipole algorithm found: {found_php}")
    cv2.imwrite("data/funn/tuned_output_2.png", out_img)

detect_kilns("data/funn/debug_tile.png")
