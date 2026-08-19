import sqlite3
import os
import base64

roaming = os.path.expandvars(r"%APPDATA%\Antigravity\User\globalStorage")
vscdb_path = os.path.join(roaming, "state.vscdb")

try:
    conn = sqlite3.connect(vscdb_path)
    cursor = conn.cursor()
    cursor.execute("SELECT key, value FROM ItemTable WHERE key LIKE '%sidebarWorkspaces%' OR key LIKE '%scratchWorkspaces%'")
    for key, val in cursor.fetchall():
        print(f"Key: {key}")
        try:
            print(f"Decoded: {base64.b64decode(val)}")
        except:
            print(f"Raw: {val}")
    
    # We want to clear the Antigravity cache so it re-reads from C:\Users\chr1203\.gemini\antigravity\conversations
    print("\nKeys to potentially delete:")
    cursor.execute("SELECT key FROM ItemTable WHERE key LIKE 'antigravityUnifiedStateSync.%' OR key LIKE 'antigravity.%'")
    for row in cursor.fetchall():
        print(row[0])
        
    conn.close()
except Exception as e:
    print(e)
