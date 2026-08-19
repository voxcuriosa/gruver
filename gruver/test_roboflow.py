import requests
import base64
import os
import urllib.request

# Download image if not exists
if not os.path.exists("data/funn/debug_tile.png"):
    urllib.request.urlretrieve('https://voxcuriosa.no/gruver/data/funn/debug_tile.png', 'data/funn/debug_tile.png')

api_key = "JCc7gNw1HfQKaGhiLJRd"
model_id = "voxcuriosa/6"
url = f"https://detect.roboflow.com/{model_id}?api_key={api_key}"

with open("data/funn/debug_tile.png", "rb") as image_file:
    encoded_string = base64.b64encode(image_file.read()).decode("ascii")

response = requests.post(url, data=encoded_string, headers={
    "Content-Type": "application/x-www-form-urlencoded"
})

print(response.status_code)
print(response.json())
