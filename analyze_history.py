import sqlite3, json

db_path = r'C:\Users\chr1203\AppData\Roaming\Antigravity\User\globalStorage\state.vscdb'
try:
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    c.execute("SELECT value FROM ItemTable WHERE key = 'antigravityUnifiedStateSync.trajectorySummaries'")
    row = c.fetchone()
    if row:
        val = row[0]
        print("Raw value for trajectorySummaries:", repr(val)[:200] + '...' if len(repr(val)) > 200 else repr(val))
        try:
            data = json.loads(val)
            print(f'\nFound {len(data)} trajectory summaries.')
            dates = []
            for k, v in data.items():
                if type(v) == dict and 'lastModified' in v:
                    dates.append(v['lastModified'])
            if dates:
                dates.sort(reverse=True)
                print('Most recent 5:', dates[:5])
                print('Oldest 5:', dates[-5:])
        except json.JSONDecodeError as e:
            print("Failed to parse trajectory summaries JSON:", e)
    else:
        print('\nNo trajectorySummaries found in DB.')
    
    # Check google.antigravity
    c.execute("SELECT value FROM ItemTable WHERE key = 'google.antigravity'")
    row = c.fetchone()
    if row:
        try:
            data = json.loads(row[0])
            print('\nKeys in google.antigravity:')
            print(list(data.keys()))
            if 'antigravity.workspaceCascadeMap' in data:
                print(f"workspaceCascadeMap has {len(data.get('antigravity.workspaceCascadeMap', {}))} entries.")
        except json.JSONDecodeError as e:
            print("Failed to parse google.antigravity JSON:", e)
            print("Raw:", repr(row[0])[:200])
except Exception as e:
    print('Error:', e)
