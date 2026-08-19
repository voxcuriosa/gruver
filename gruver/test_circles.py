import cv2
import numpy as np

def test_detect():
    img = cv2.imread('data/funn/debug_tile.png')
    if img is None:
        print("No debug tile found. Will just update the script for the user to test.")
        return
        
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    
    # Let's test different param2
    for p2 in [15, 20, 25, 30]:
        circles = cv2.HoughCircles(blur, cv2.HOUGH_GRADIENT, dp=1.0, minDist=10,
                                  param1=40, param2=p2, minRadius=5, maxRadius=15)
        count = 0 if circles is None else len(circles[0])
        print(f"param2={p2} -> {count} circles")

if __name__ == '__main__':
    test_detect()
