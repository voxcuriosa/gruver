import cv2
import numpy as np
import json
import os
import sys
import argparse

def detect_charcoal_kilns(img, min_rad, max_rad):
    """
    Kullmiler: Circular structures, approx 5m diameter.
    In DTM Hillshade/Slope, these often appear as circular rings.
    """
    results = []
    # Convert to grayscale if necessary
    if len(img.shape) == 3:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    else:
        gray = img
        
    # Pre-processing
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    
    # Hough Circle Transform
    # param1 = edge detection threshold
    # param2 = threshold for circle centers (lower = more false positives, but 45 was too strict)
    # Using dp=1.0 and lower minDist to ensure we don't miss closely grouped pits.
    circles = cv2.HoughCircles(blur, cv2.HOUGH_GRADIENT, dp=1.0, minDist=10,
                              param1=40, param2=14, minRadius=min_rad, maxRadius=max_rad)
    
    if circles is not None:
        circles = np.uint16(np.around(circles))
        for i in circles[0, :]:
            # Probability calculation (simplified)
            prob = 0.85 # Placeholder
            results.append({'x': int(i[0]), 'y': int(i[1]), 'r': int(i[2]), 'cat': 'kullmile', 'prob': prob})
            
    return results

def detect_mine_pits(img):
    """
    Dagstrosser: 2m wide, linear depressions.
    Canny Edge + Hough Line Transform.
    """
    results = []
    if len(img.shape) == 3:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    else:
        gray = img

    edges = cv2.Canny(gray, 50, 150, apertureSize=3)
    lines = cv2.HoughLinesP(edges, 1, np.pi/180, threshold=100, minLineLength=20, maxLineGap=10)
    
    if lines is not None:
        for line in lines:
            x1, y1, x2, y2 = line[0]
            # Center of the line
            cx, cy = (x1 + x2) // 2, (y1 + y2) // 2
            prob = 0.82 # Placeholder
            results.append({'x': int(cx), 'y': int(cy), 'cat': 'gruve', 'prob': prob})
            
    return results

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', required=True, help='Path to input LiDAR tile image')
    parser.add_argument('--output', required=True, help='Path to save JSON findings')
    parser.add_argument('--bbox', help='Bounding box [S, W, N, E]')
    parser.add_argument('--min-radius', type=int, default=5, help='Min radius in pixels')
    parser.add_argument('--max-radius', type=int, default=15, help='Max radius in pixels')
    args = parser.parse_args()

    if not os.path.exists(args.input):
        print(f"Error: Input file {args.input} not found")
        sys.exit(1)

    img = cv2.imread(args.input)
    if img is None:
        print(f"Error: Could not read image {args.input}")
        sys.exit(1)

    kilns = detect_charcoal_kilns(img, args.min_radius, args.max_radius)
    pits = detect_mine_pits(img)
    
    findings = kilns + pits
    
    # In a real scenario, we would map (x, y) pixels back to (lat, lng) 
    # using the bbox and world file info.
    
    with open(args.output, 'w') as f:
        json.dump(findings, f, indent=4)
    
    print(f"Detected {len(findings)} objects")

if __name__ == "__main__":
    main()
