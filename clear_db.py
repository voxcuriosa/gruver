import sqlite3
import os

roaming = os.path.expandvars(r"%APPDATA%\Antigravity\User\globalStorage")
vscdb_path = os.path.join(roaming, "state.vscdb")

try:
    conn = sqlite3.connect(vscdb_path)
    cursor = conn.cursor()
    
    # Backup DB before modification just in case
    import shutil
    shutil.copy2(vscdb_path, vscdb_path + ".history_fix_bak")
    
    cursor.execute("DELETE FROM ItemTable WHERE key LIKE 'antigravity.%' OR key LIKE 'antigravityUnifiedStateSync.%'")
    count = cursor.rowcount
    conn.commit()
    print(f"Deleted {count} rows from state.vscdb related to Antigravity.")
    
    conn.close()
except Exception as e:
    print(e)
