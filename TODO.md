# Vox Portal - Gjøremål (TO-DO)

## Sikkerhet (Høy prioritet)
- [ ] **Sikre legitimasjon i `config.php`**: Flytt OpenAI API-nøkkel og PIN-kode ut av `public_html` eller bruk en `.env`-fil for å beskytte mot innsyn hvis serveren blir kompromittert.
- [ ] **Rydd i FTP-skripter**: Vurder å bruke miljøvariabler lokalt i stedet for å ha FTP-passordet i klartekst i `deploy.py` og `upload_ftp.py`.

## Vedlikehold
- [ ] **Backup-sjekk**: Se over `backup/`-mappene periodisk og rydd i gamle versjoner hvis det blir for mange.
- [ ] **Forbedre KML-synkronisering**: Implementer logikk for å gjenkjenne eksisterende punkter (via ID eller navn) i `compare_coords.py` og `compare_kml.py`, slik at flyttede punkter eller oppdatert info replikeres i stedet for å lage duplikater.

---
*Opprettet av Antigravity 2026-02-09*
