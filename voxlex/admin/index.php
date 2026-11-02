<?php
session_start();

// Passordhash (SHA-256) for admin-tilgang ("voxlex2026" eller eksamensvakt/admin-kode)
$VALID_HASHES = [
    hash('sha256', 'voxlex2026'),
    hash('sha256', 'privex2026'),
];

if (isset($_GET['logout'])) {
    unset($_SESSION['vx_admin_ok']);
    header('Location: index.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    $inputHash = hash('sha256', trim((string)$_POST['admin_pass']));
    if (in_array($inputHash, $VALID_HASHES, true)) {
        $_SESSION['vx_admin_ok'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Feil admin-passord.';
    }
}

$isAuthed = !empty($_SESSION['vx_admin_ok']);
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
    .wrap { max-width: 680px; margin: 0 auto; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1.75rem; }
    h1 { margin: 0 0 0.5rem 0; font-size: 1.35rem; }
    p { margin: 0 0 1.25rem 0; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
    label { display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.35rem; }
    input, select { width: 100%; padding: 0.65rem 0.8rem; border-radius: 10px; border: 1px solid var(--border); background: #0f172a; color: var(--text); font-size: 0.92rem; margin-bottom: 1rem; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    button { padding: 0.7rem 1.2rem; border-radius: 10px; border: none; background: var(--brand); color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; }
    .presets { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
    .preset-btn { background: #0f172a; border: 1px solid var(--border); color: var(--text); padding: 0.45rem 0.75rem; font-size: 0.8rem; font-weight: 600; }
    .result-box { margin-top: 1.25rem; padding: 1.25rem; border-radius: 12px; background: #0f172a; border: 1px solid var(--ok); }
    .key-code { font-family: ui-monospace, monospace; font-size: 1.4rem; font-weight: 800; letter-spacing: 0.04em; color: #34d399; margin: 0.4rem 0 0.9rem 0; word-break: break-all; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    a.logout { color: var(--muted); font-size: 0.82rem; text-decoration: underline; }
    .err { color: #fca5a5; font-size: 0.85rem; margin-bottom: 0.75rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <?php if (!$isAuthed): ?>
      <h1>VoxLex Admin – Logg inn</h1>
      <p>Skriv inn admin-passordet for å generere manuelle lisenskoder for VoxLex.</p>
      <?php if ($loginError): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
      <form method="post">
        <label for="admin_pass">Admin-passord</label>
        <input type="password" id="admin_pass" name="admin_pass" required autofocus placeholder="Passord..." />
        <button type="submit">Logg inn</button>
      </form>
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
          <label for="vx-type">Lisenskategori / Referanse (3–8 tegn)</label>
          <select id="vx-type-preset" onchange="document.getElementById('vx-ref').value = this.value; updateKey();">
            <option value="FRI1">FRI1 – Manuell gratis/spesiallisens</option>
            <option value="PRIV">PRIV – Privex-abonnent</option>
            <option value="VIPP">VIPP – Kun VoxLex (20 kr/mnd Vipps)</option>
            <option value="SITE">SITE – Skole- / kommunelisens</option>
            <option value="4501">4501 – NAV Enkeltvedtak</option>
          </select>
        </div>
      </div>

      <div class="grid">
        <div>
          <label for="vx-ref">Egendefinert referanse / ordrenr (f.eks. FRI1, ELEV, 4501)</label>
          <input type="text" id="vx-ref" value="FRI1" oninput="updateKey()" />
        </div>
        <div>
          <label for="vx-date-picker">Eller velg eksakt utløpsdato</label>
          <input type="date" id="vx-date-picker" onchange="onPickDate(this.value)" />
        </div>
      </div>

      <div class="result-box">
        <div style="font-size: 0.82rem; color: var(--muted);" id="vx-exp-label">Gyldighet: Evigvarende</div>
        <div class="key-code" id="vx-output">VX-9999-FRI1-3KAK</div>
        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem;">
          <button type="button" onclick="copyCode()" id="btn-copy-code">Kopier lisenskode</button>
          <button type="button" class="preset-btn" onclick="copyMessage()" id="btn-copy-msg">Kopier ferdig melding til bruker</button>
        </div>
      </div>

      <script>
        const PEPPER = "VoxLex-2026-Offline-NAV-Pepper-v1!#99";
        const ALPHABET = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";
        const MASK64 = 0xFFFFFFFFFFFFFFFFn;

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
        updateKey();
      </script>
    <?php endif; ?>
  </div>
</body>
</html>
