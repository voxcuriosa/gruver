<?php
define('VX_ADMIN_ENTRY', true);
require_once __DIR__ . '/auth_guard.php';
?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>VoxLex Admin – Manuell Lisensgenerator</title>
  <style>
    :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --brand: #2563eb; --ok: #10b981; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); padding: 2rem 1rem; }
    .wrap { max-width: 740px; margin: 0 auto; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1.75rem; }
    h1 { margin: 0 0 0.5rem 0; font-size: 1.35rem; }
    h2 { margin: 1.75rem 0 0.75rem 0; font-size: 1.05rem; color: #e2e8f0; border-top: 1px solid var(--border); padding-top: 1.35rem; }
    p { margin: 0 0 1.25rem 0; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
    label { display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.35rem; }
    input, select { width: 100%; padding: 0.65rem 0.8rem; border-radius: 10px; border: 1px solid var(--border); background: #0f172a; color: var(--text); font-size: 0.92rem; margin-bottom: 1rem; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    button { padding: 0.7rem 1.2rem; border-radius: 10px; border: none; background: var(--brand); color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    .presets { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
    .preset-btn { background: #0f172a; border: 1px solid var(--border); color: var(--text); padding: 0.45rem 0.75rem; font-size: 0.8rem; font-weight: 600; }
    .ref-row { display: flex; gap: 0.5rem; align-items: stretch; margin-bottom: 1rem; }
    .ref-row input { margin-bottom: 0; flex: 1; }
    .result-box { margin-top: 0.75rem; padding: 1.25rem; border-radius: 12px; background: #0f172a; border: 1px solid var(--ok); }
    .key-code { font-family: ui-monospace, monospace; font-size: 1.4rem; font-weight: 800; letter-spacing: 0.04em; color: #34d399; margin: 0.4rem 0 0.9rem 0; word-break: break-all; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    a.logout { color: var(--muted); font-size: 0.82rem; text-decoration: underline; }
    .err { color: #fca5a5; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 0.9rem; }
    .sec-note { margin-top: 1rem; font-size: 0.76rem; color: var(--muted); }
    .guide-grid { display: grid; gap: 0.85rem; margin-top: 0.75rem; }
    .guide-card { background: #0f172a; border: 1px solid var(--border); border-radius: 10px; padding: 0.95rem 1.1rem; }
    .guide-card strong { display: block; color: #f8fafc; font-size: 0.88rem; margin-bottom: 0.3rem; }
    .guide-card p { margin: 0; font-size: 0.83rem; color: var(--muted); line-height: 1.5; }
    code { font-family: ui-monospace, monospace; background: #1e293b; color: #93c5fd; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.82rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <?php if (!$isAuthed): ?>
      <h1>VoxLex Admin – Logg inn</h1>
      <p>Skriv inn admin-passordet for å generere manuelle lisenskoder for VoxLex.</p>
      <?php if ($loginError): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['vx_csrf'] ?? '') ?>" />
        <label for="admin_pass">Admin-passord</label>
        <input type="password" id="admin_pass" name="admin_pass" required autofocus placeholder="Passord..." <?= !empty($isLockedOut) ? 'disabled' : '' ?> />
        <button type="submit" <?= !empty($isLockedOut) ? 'disabled' : '' ?>>Logg inn</button>
      </form>
      <div class="sec-note">Beskyttet med PBKDF2-HMAC-SHA256 (120 000 iterasjoner) og automatisk 30 minutters IP-lås etter 5 feilforsøk.</div>
    <?php else: ?>
      <div class="top-bar">
        <h1>VoxLex Admin – Nøkkelgenerator</h1>
        <a class="logout" href="?logout=1">Logg ut</a>
      </div>
      <p>Generer kryptografisk signerte lisenskoder som fungerer 100 % offline i VoxLex på Mac, Windows og PWA.</p>

      <label>Hurtigvalg for varighet</label>
      <div class="presets">
        <button type="button" class="preset-btn" onclick="setPreset('perpetual')">Evigvarende (9999)</button>
        <button type="button" class="preset-btn" onclick="setPreset('days', 14)">+14 dager</button>
        <button type="button" class="preset-btn" onclick="setPreset('days', 30)">+30 dager (1 mnd)</button>
        <button type="button" class="preset-btn" onclick="setPreset('days', 180)">+6 måneder</button>
        <button type="button" class="preset-btn" onclick="setPreset('days', 365)">+1 år</button>
        <button type="button" class="preset-btn" onclick="setPreset('schoolyear')">Ut skoleåret (15. aug)</button>
      </div>

      <div class="grid">
        <div>
          <label for="vx-period">Periodekode (9999, YYYY eller YYMMDD)</label>
          <input type="text" id="vx-period" value="9999" oninput="updateKey()" />
        </div>
        <div>
          <label for="vx-type-preset">Lisenskategori (legger automatisk til unik ID)</label>
          <select id="vx-type-preset" onchange="applyCategoryRandom(this.value)">
            <option value="FRI">FRI – Manuell gratis/spesiallisens (enkeltbruker)</option>
            <option value="PRIV">PRIV – Privex-abonnent (enkeltbruker)</option>
            <option value="VIPP">VIPP – Kun VoxLex 20 kr/mnd Vipps (enkeltbruker)</option>
            <option value="NAV">NAV – NAV Enkeltvedtak / Hjelpemiddelsentral</option>
            <option value="SITE">SITE – Skole- / kommunelisens (ubegrenset antall PC-er)</option>
          </select>
        </div>
      </div>

      <div class="grid">
        <div>
          <label for="vx-ref">Unik referanse / navn / ordrenr (f.eks. FRI48K eller JOSEPHINE)</label>
          <div class="ref-row">
            <input type="text" id="vx-ref" value="FRI1" oninput="updateKey()" />
            <button type="button" class="preset-btn" onclick="randomizeCurrentRef()" title="Generer ny tilfeldig ID for valgt kategori">Ny ID</button>
          </div>
        </div>
        <div>
          <label for="vx-date-picker">Eller velg eksakt utløpsdato</label>
          <input type="date" id="vx-date-picker" onchange="onPickDate(this.value)" />
        </div>
      </div>

      <div class="result-box">
        <div style="font-size: 0.82rem; color: var(--muted);" id="vx-exp-label">Gyldighet: Evigvarende</div>
        <div class="key-code" id="vx-output">VX-9999-FRI1-7A6E</div>
        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem;">
          <button type="button" onclick="copyCode()" id="btn-copy-code">Kopier lisenskode</button>
          <button type="button" class="preset-btn" onclick="copyMessage()" id="btn-copy-msg">Kopier ferdig melding til bruker</button>
        </div>
      </div>

      <h2>Huskeliste: Slik fungerer lisenskodene</h2>
      <div class="guide-grid">
        <div class="guide-card">
          <strong>1. Hvorfor må hver person få sin egen unike referanse (f.eks. FRI48K eller JOSEPHINE)?</strong>
          <p>Lisenskoden regnes ut matematisk fra <code>periode + referanse</code>. Hvis du gir nøyaktig samme kode (f.eks. <code>VX-9999-FRI1-7A6E</code>) til flere ulike personer, tror serveren at det er én og samme lisens som deles. Derfor legger generatoren nå automatisk på 3 tilfeldige tegn (f.eks. <code>FRI48K</code>) hver gang du åpner siden eller trykker «Ny ID». Du kan også skrive inn brukerens fornavn (f.eks. <code>JOSEPHINE</code>) eller ordrenummer direkte.</p>
        </div>
        <div class="guide-card">
          <strong>2. Hvor mange enheter kan én person bruke – og hva skjer ved PC-bytte om 4 år?</strong>
          <p>Hver personlig lisenskode tillater <strong>3 samtidige enheter</strong> (f.eks. skole-PC, hjemme-PC og Mac). Når brukeren legger inn koden på en 4. enhet (f.eks. ny PC om 4 år), <strong>skyves den eldste maskinen automatisk ut av listen</strong> (FIFO-kø), slik at brukeren aldri trenger å kontakte deg for å deaktivere gamle maskiner. For å hindre at en kode deles med en hel klasse samtidig, er det en grense på <strong>5 nyaktiveringer per kalenderår</strong> – denne telleren <strong>nullstilles automatisk hver 1. januar</strong>.</p>
        </div>
        <div class="guide-card">
          <strong>3. Skole- og kommunelisens (SITE / KOMM) har ingen maskinkvote</strong>
          <p>Når referansen starter med <code>SITE</code> eller <code>KOMM</code> (f.eks. <code>SITEOSLO</code> eller <code>SITE82M</code>), deaktiveres grensen på 3 maskiner/5 bytter automatisk. Samme kode kan da brukes på et ubegrenset antall elevmaskiner på skolen eller i kommunen.</p>
        </div>
        <div class="guide-card">
          <strong>4. Slik tolker appen periodekoden (9999, YYYY og YYMMDD)</strong>
          <p><code>9999</code> = Evigvarende lisens (utløper aldri).<br />
          <code>YYYY</code> (4 siffer, f.eks. <code>2027</code>) = Gyldig ut skoleåret, til og med <strong>15. august</strong> det året.<br />
          <code>YYMMDD</code> (6 siffer, f.eks. <code>261231</code>) = Gyldig til og med eksakt dato (<code>2026-12-31 kl. 23:59</code>).<br />
          Under eksamen i <strong>Safe Exam Browser (SEB)</strong> er nettverk fysisk avslått, og VoxLex fungerer alltid 100 % uavhengig av lisens.</p>
        </div>
      </div>

      <script>
        const PEPPER = "VoxLex-2026-Offline-NAV-Pepper-v1!#99";
        const ALPHABET = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";
        const MASK64 = 0xFFFFFFFFFFFFFFFFn;

        function randomSuffix(len = 3) {
          let s = "";
          for (let i = 0; i < len; i++) {
            s += ALPHABET[Math.floor(Math.random() * ALPHABET.length)];
          }
          return s;
        }

        function applyCategoryRandom(prefix) {
          document.getElementById('vx-ref').value = prefix + randomSuffix(3);
          updateKey();
        }

        function randomizeCurrentRef() {
          const prefix = document.getElementById('vx-type-preset').value || 'FRI';
          applyCategoryRandom(prefix);
        }

        function computeSig(period, ref) {
          const input = PEPPER + ":" + period.trim().toUpperCase() + ":" + ref.trim().toUpperCase();
          const bytes = new TextEncoder().encode(input);
          let h = 0xCBF29CE484222325n;
          for (const b of bytes) {
            h ^= BigInt(b);
            h = (h * 0x100000001B3n) & MASK64;
            h ^= h >> 31n;
          }
          for (let i = 0; i < 4; i++) {
            h = (h * 0xFF51AFD7ED558CCDn) & MASK64;
            h ^= h >> 33n;
          }
          let out = "";
          for (let i = 0; i < 4; i++) {
            const idx = Number(h % 32n);
            out += ALPHABET[idx];
            h >>= 5n;
          }
          return out;
        }

        function setPreset(mode, days) {
          const now = new Date();
          if (mode === 'perpetual') {
            document.getElementById('vx-period').value = '9999';
          } else if (mode === 'days') {
            now.setDate(now.getDate() + days);
            const yy = String(now.getFullYear()).slice(-2);
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            document.getElementById('vx-period').value = yy + mm + dd;
          } else if (mode === 'schoolyear') {
            const y = now.getMonth() >= 7 ? now.getFullYear() + 1 : now.getFullYear();
            document.getElementById('vx-period').value = String(y);
          }
          updateKey();
        }

        function onPickDate(val) {
          if (!val) return;
          const parts = val.split('-');
          if (parts.length === 3) {
            document.getElementById('vx-period').value = parts[0].slice(-2) + parts[1] + parts[2];
            updateKey();
          }
        }

        function updateKey() {
          const p = document.getElementById('vx-period').value.trim().replace(/\D/g, '');
          const r = document.getElementById('vx-ref').value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
          const outEl = document.getElementById('vx-output');
          const expEl = document.getElementById('vx-exp-label');
          if ((p.length !== 4 && p.length !== 6) || r.length < 3) {
            outEl.textContent = 'Skriv 4/6 siffer i periode og minst 3 tegn i referanse';
            return;
          }
          const sig = computeSig(p, r);
          const code = `VX-${p}-${r}-${sig}`;
          outEl.textContent = code;
          expEl.textContent = p === '9999' ? 'Gyldighet: Evigvarende' : (p.length === 6 ? `Gyldig til og med: 20${p.slice(0,2)}-${p.slice(2,4)}-${p.slice(4,6)}` : `Gyldig til: ${p}-08-15`);
        }

        function copyCode() {
          navigator.clipboard.writeText(document.getElementById('vx-output').textContent);
          const b = document.getElementById('btn-copy-code');
          b.textContent = 'Kopiert!';
          setTimeout(() => b.textContent = 'Kopier lisenskode', 2000);
        }

        function copyMessage() {
          const code = document.getElementById('vx-output').textContent;
          const exp = document.getElementById('vx-exp-label').textContent;
          const msg = `Her er din lisenskode til VoxLex:\n\n${code}\n(${exp})\n\nSlik aktiverer du:\n1. Åpne VoxLex (Innstillinger -> Om VoxLex -> Legg inn lisenskode, eller direkte i aktiveringsvinduet).\n2. Lim inn koden ${code} og klikk «Aktiver lisens».`;
          navigator.clipboard.writeText(msg);
          const b = document.getElementById('btn-copy-msg');
          b.textContent = 'Melding kopiert!';
          setTimeout(() => b.textContent = 'Kopier ferdig melding til bruker', 2000);
        }
        randomizeCurrentRef();
      </script>
    <?php endif; ?>
  </div>
</body>
</html>
