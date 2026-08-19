# TODO - Videreutvikling av Vox Portal

## Sync-logikk (Google My Maps -> Prosjekt)
Gjøre synkroniseringen mer robust mot endringer i Google My Maps.

- [ ] **Løsning 1: Fingeravtrykk-match (Automatisk)**
  Laste inn eksisterende `full_data.json` og kjenne igjen punkter basert på:
  - Nøyaktig navn + omtrentlig posisjon (f.eks. innenfor 10m).
  - Gjenbruke gammel ID selv om rekkefølgen i KML-filen er endret.
  - Sørger for at bilder ikke lastes ned på nytt unødvendig.

- [ ] **Løsning 2: Unike ID-er i beskrivelse (Manuelt)**
  Støtte for å lese en unik ID fra beskrivelsesfeltet i My Maps, f.eks. `{id: 101}`.
  - Gir 100% sikker matching uansett hva som endres.

- [ ] **Løsning 3: Bildefil-validering**
  Endre bilde-check slik at den også ser på filstørrelse eller innhold (hashing) for å oppdage om et bilde i My Maps er byttet ut med et nytt bilde med samme navn.
