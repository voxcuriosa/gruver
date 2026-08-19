# Backup-rutine for Vox Portal (Gruver)

Denne filen beskriver hvordan sikkerhetskopiering skal utføres for dette prosjektet.

## Strategi: Versjonert Backup
Sikkerhetskopiering utføres ved å opprette en ny undermappe for hver kjøring (`v1`, `v2`, `v3`, osv.) i backup-mappen.

*   **Målmappe:** `.../vox_portal/gruver/backup/vN/`
*   **Metode:** `backup.py` detekterer neste ledige versjonsnummer og kopierer kjernefilene dit.

## Inkluderte filer
Følgende aktive filer er inkludert i backupen:
- `index.php`, `viewer.js` (Hovedapplikasjon)
- `georef.php`, `upload.php`, `save_georef.php` (Georefare-verktøy v2)
- `published_maps.json` (Register over publiserte kart)
- `*.php` (Proxy-skripter, aut_v2, APIer, og angre-funksjoner)
- `sync_*.php`, `sync_*.py` (Synkronisering)
- `overrides.json`, `changelog.json` (Vedvarende manuelle endringer)
- `deploy.py`, `upload_ftp.py`, `server_cleanup.py` (Utrulling og vedlikehold)
- `export_images_v2.py` (Bilde-eksport)
- `full_data.*` (Map-data)
- `kanalanlegg_utf8.html`, `test_*.html`, `verifisering.html` (Lokalt innhold og tester)
- `BACKUP_INFO.md`, `backup.py` (Backup-systemet)
- `manifest.json`, `sw.js` (PWA)
- **Viktig:** Bilder/kart i `assets`-mappen på serveren må også kopieres ned lokalt regelmessig for å ha en komplett backup.

## Gjenoppretting
Se i den nyeste `vN`-mappen i `backup/`.

---
*Sist oppdatert: 2026-02-20 av Antigravity*
