import json

try:
    with open('swagger.json', 'r', encoding='utf-8') as f:
        data = json.load(f)
        
    print("Base URL:", data.get('host', 'Unknown'))
    print("BasePath:", data.get('basePath', ''))
    
    paths = data.get('paths', {})
    if '/token/tilecache' in paths:
        print("\nDetails for /token/tilecache:")
        for method, details in paths['/token/tilecache'].items():
            print(f"  Method: {method.upper()}")
            print(f"  Parameters: {json.dumps(details.get('parameters', []), indent=2)}")
            print(f"  RequestBody: {json.dumps(details.get('requestBody', {}), indent=2)}")
            print(f"  Responses: {json.dumps(details.get('responses', {}), indent=2)}")
            
except Exception as e:
    print(f"Error: {e}")
