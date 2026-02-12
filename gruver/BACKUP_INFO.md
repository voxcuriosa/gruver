# Backup-rutine for Vox Portal (Gruver)

Denne filen beskriver hvordan sikkerhetskopiering skal utføres for dette prosjektet.

## Strategi: Versjonert Backup
Sikkerhetskopiering utføres ved å opprette en ny undermappe for hver kjøring (`v1`, `v2`, `v3`, osv.) i backup-mappen. Dette gjør det enkelt å gå tilbake til spesifikke versjoner uten å måtte stole utelukkende på OneDrive sin historikk.

*   **Målmappe:** `C:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\backup\vN\`
*   **Metode:** `backup.py` detekterer neste ledige versjonsnummer og kopierer kjernefilene dit.

## Backup-prosedyre (Steg-for-steg)
1.  **Sjekk etter nye filer:** Se gjennom rotmappen for nye `.php`, `.js` eller `.py` filer som bør inkluderes.
2.  **Oppdater `backup.py`:** Legg til eventuelle nye filer i `FILES_TO_BACKUP`-listen.
3.  **Utfør kopiering:** Kjør `python backup.py`.

## Inkluderte filer
Følgende kjernefiler er inkludert i backupen:
- `index.php` (Grensesnitt og CSS)
- `viewer.js` (Kartlogikk og koordinat-parser)
- `nib_proxy.php`, `kultur_proxy.php`, `proxy_xml.php`, `image_proxy.php`, `tile_proxy.php`, `elevation_proxy.php`, `proxy.php` (Proxy-skripter)
- `sync.php`, `sync_images_trigger.php`, `convert_geojson.php` (Synkroniseringsverktøy PHP & GeoJSON)
- `sync_google_photos.py`, `cleanup_server_media.py` (Google Photos Sync & Cleanup)
- `deploy.py`, `upload_ftp.py` (Utrullingsskript)
- `export_images.py`, `compare_coords.py`, `compare_kml.py`, `get_missing.py`, `migrate_images.php` (Verktøy og synkronisering)
- `patch_viewer.py ...` (Patch-verktøy)
- `BACKUP_INFO.md`, `backup.py` (Backup-systemet)

## Gjenoppretting
Nå du trenger å gjenopprette en versjon, se i den nyeste `vN`-mappen i `backup/`.

---
*Sist oppdatert: 2026-02-09 av Antigravity*
