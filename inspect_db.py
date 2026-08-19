import sqlite3
import os
import base64

db_path = r"C:\Users\chr1203\AppData\Roaming\Antigravity\User\globalStorage\state.vscdb"
curr_uuid = "7a15f795-8061-4bcd-a923-b700871a319e"

try:
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    key = 'antigravityUnifiedStateSync.trajectorySummaries'
    cursor.execute("SELECT value FROM ItemTable WHERE key = ?;", (key,))
    row = cursor.fetchone()
    if row:
        val = row[0]
        decoded = base64.b64decode(val)
        if curr_uuid.encode("ascii") in decoded:
            print(f"Current UUID {curr_uuid} FOUND in index.")
        else:
            print(f"Current UUID {curr_uuid} NOT FOUND in index.")
    else:
        print("Key not found.")
    conn.close()
except Exception as e:
    print(f"Error: {e}")
