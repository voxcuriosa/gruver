import sqlite3
import os

roaming = os.path.expandvars(r"%APPDATA%\Antigravity\User\globalStorage")
vscdb_path = os.path.join(roaming, "state.vscdb")

try:
    conn = sqlite3.connect(vscdb_path)
    cursor = conn.cursor()
    
    print(f"Connected to {vscdb_path}")

    cursor.execute("SELECT key FROM ItemTable WHERE key LIKE '%antigravity%' OR key LIKE '%gemini%'")
    keys = cursor.fetchall()
    print("Found keys related to antigravity/gemini:")
    for k in keys:
        print(f" - {k[0]}")
        
    cursor.execute("SELECT key, value FROM ItemTable WHERE key LIKE '%workspace%' OR key LIKE '%history%' OR key LIKE '%trajectory%'")
    data = cursor.fetchall()
    print("\nInteresting values:")
    for row in data:
        key, val = row
        print(f"Key: {key}\nVal: {str(val)[:200]}...\n")
        
    conn.close()
except Exception as e:
    print(f"Error: {e}")
