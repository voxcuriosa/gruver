---
name: seb-zero-student-tracking
description: >-
  Use this skill whenever working with SEB (Safe Exam Browser), web analytics,
  visit statistics, portal scripts, index.html, or deploy routines in vox_portal and sebgenerator.
  Enforces 100% zero tracking, zero logging, and zero telemetry for student exam links
  (/seb/start/*, /seb/exam/*) while safely allowing teacher visits to be counted on the generator home page.
---

# SEB Zero Student Tracking (Personvern for Elever i Safe Exam Browser)

Dette er en ufravikelig personvern- og sikkerhetsregel for alle portaler, skript og apper tilknyttet Safe Exam Browser (SEB) på VoxCuriosa (`voxcuriosa.no/seb/`).

Elever som åpner en lenke generert for en prøve skal **aldri** spores, logges, geolokeres eller telles i statistikk.

---

## 1. Hovedregel: Strengt skille mellom Lærer og Elev

| Område | URL / Kontekst | Tillatt sporing | Formål |
|---|---|---|---|
| **Lærerportal (Hovedside)** | `https://voxcuriosa.no/` (Kort-klikk: `logAppVisit('seb')`) | ✅ JA | Telle lærere som åpner verktøyet fra portalen |
| **Lærerens Prøvebygger (TeacherHub)** | `https://voxcuriosa.no/seb/` eller `/seb/index.html` | ✅ JA | Telle lærere som besøker prøvebyggeren direkte |
| **Elevens Startside** | `https://voxcuriosa.no/seb/start/:examId` | ❌ **FORBUDT** | 0 % sporing. Ingen nettverkskall, ingen cookies, ingen IP-logging |
| **Elevens Prøverom i SEB** | `https://voxcuriosa.no/seb/exam/:examId` eller `?seb=1` | ❌ **FORBUDT** | 0 % sporing. Fullstendig taushet |
| **Avslutningsside** | `https://voxcuriosa.no/seb/quit` | ❌ **FORBUDT** | 0 % sporing |

---

## 2. Teknisk krav til all sporingskode i SEB

Hvis et sporingsskript noen gang legges inn i `seb/index.html` eller i kildekoden, **MÅ** det ha en umiddelbar blokkering øverst før noen funksjoner, cookies eller nettverkskall kjøres:

```javascript
// 1. Sjekk at vi utelukkende er på lærerens forside
var pathname = window.location.pathname.replace(/\/+$/, '') || '/';
var isTeacherPage = (pathname === '/seb' || pathname === '/seb/index.html');

// 2. Blokker umiddelbart alle elevlenker og SEB-økter
var isStudentLink = window.location.pathname.indexOf('/start/') !== -1 ||
                    window.location.pathname.indexOf('/exam/') !== -1 ||
                    window.location.pathname.indexOf('/quit') !== -1 ||
                    window.location.search.indexOf('seb=1') !== -1;

if (!isTeacherPage || isStudentLink) {
    return; // STOPP UMIDDELBART. Ingen fetch, ingen geojs, ingen localStorage!
}
```

### Hva som er strengt forbudt på elevlenker:
1. **Ingen nettverkskall til analysetjenester:** Aldri kall `fetch('/history/auth_v2.php')` eller andre analysepunkter.
2. **Ingen tredjeparts geolocation:** Aldri gjør oppslag mot `geojs.io`, `api.country.is` eller tilsvarende tjenester når en elevside lastes.
3. **Ingen identifikatorer:** Aldri lagre eller les `vox_visitor_id` fra elevens `localStorage`.
4. **Ingen Google Analytics / GTM / Meta Pixel:** Verken direkte eller betinget.

---

## 3. Sjekkliste før deploy og ved endringer

Før noen endring deployes til `voxcuriosa.no/seb` eller `public_html/seb`:

1. **Test elevlenke manuelt:**
   Kjør en curl eller nettleserinspeksjon mot en elevlenke:
   ```bash
   curl -s https://voxcuriosa.no/seb/start/TESTID | grep "isStudentLink"
   ```
   Bekreft at elevblokkeringen er til stede i HTML-en som serveres.
2. **Kjør personvernvakten i repoet:**
   I `sebgenerator`:
   ```bash
   npm test
   ```
   Bekreft at `scripts/privacy-guard.mjs` godkjenner kodebasen.
3. **Sjekk at lærersiden fortsatt spores:**
   Gå til `https://voxcuriosa.no/history/admin_visit_stats.php` og bekreft at `seb`-kategorien kun øker når lærere klikker på lenken eller laster forsiden, aldri når elever tar prøver.

---

## 4. Ved tvil

Hvis det er tvil om en bruker er lærer eller elev: **Ikke spor.**
Personvern for elever i skolen og eksamensintegritet går foran besøkstall.
