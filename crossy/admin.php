<?php
session_start();
require_once __DIR__ . '/config.php';

$error = ''; $success = '';

if (isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (($user === ADMIN_USER && $pass === ADMIN_PASS) || $pass === ADMIN_PIN || (defined('ADMIN_PIN') && $pass === ADMIN_PIN)) {
        $_SESSION['crossy_admin'] = true;
    } else {
        $error = 'Feil brukernavn eller admin-PIN.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['crossy_admin']);
    header('Location: admin.php');
    exit;
}

$isAdmin = isset($_SESSION['crossy_admin']) && $_SESSION['crossy_admin'] === true;

function loadQuestionsData() {
    if (file_exists(QUESTIONS_FILE)) {
        return json_decode(file_get_contents(QUESTIONS_FILE), true) ?: ['topics' => []];
    }
    return ['topics' => []];
}

function saveQuestionsData($data) {
    file_put_contents(QUESTIONS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = loadQuestionsData();

    if (isset($_POST['delete_topic'])) {
        $delId = $_POST['delete_topic_id'] ?? '';
        $data['topics'] = array_values(array_filter($data['topics'], fn($t) => $t['id'] !== $delId));
        saveQuestionsData($data);
        $success = "Tema slettet.";
    }

    if (isset($_POST['reset_highscores'])) {
        file_put_contents(HIGHSCORES_FILE, json_encode(['scores' => []], JSON_PRETTY_PRINT));
        $success = "🏆 Highscores er nå nullstilt!";
    }

    if (isset($_POST['delete_q'])) {
        $tId = $_POST['del_q_topic'] ?? '';
        $qIdx = intval($_POST['del_q_idx'] ?? -1);
        foreach ($data['topics'] as &$t) {
            if ($t['id'] === $tId && isset($t['questions'][$qIdx])) {
                array_splice($t['questions'], $qIdx, 1);
                break;
            }
        }
        saveQuestionsData($data);
        $success = "Spørsmål slettet.";
    }

    if (isset($_POST['import_batch'])) {
        $newTitle = trim($_POST['new_topic_title'] ?? '');
        $tId = $_POST['import_topic'] ?? '';
        $parsed = json_decode(trim($_POST['batch_json'] ?? ''), true);

        if ($newTitle) {
            $tId = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '_', $newTitle)));
            // Check if already exists
            $exists = false;
            foreach ($data['topics'] as $t) { if ($t['id'] === $tId) { $exists = true; break; } }
            if (!$exists) {
                $data['topics'][] = ['id' => $tId, 'title' => $newTitle, 'description' => 'Opprettet fra pensum-PDF', 'questions' => []];
            }
        }

        if (is_array($parsed)) {
            foreach ($data['topics'] as &$t) {
                if ($t['id'] === $tId) {
                    foreach ($parsed as $q) {
                        if (isset($q['question'], $q['options'], $q['answer']) && count($q['options']) === 4) {
                            $q['id'] = count($t['questions']) + 1;
                            $t['questions'][] = $q;
                        }
                    }
                    break;
                }
            }
            saveQuestionsData($data);
            $success = "Spørsmål lagret til temaet!";
        } else {
            $error = "Ugyldig JSON-format på spørsmålene.";
        }
    }
}
$questionsData = loadQuestionsData();
?>
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <title>Admin - Tidskrøll Pensum & Spørsmål</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>if (window.pdfjsLib) window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
    <style>
        body { background: #0f1117; color: #fff; padding: 20px; font-family: 'Outfit', sans-serif; }
        .admin-box { max-width: 860px; margin: 0 auto; background: #1a1c24; border: 1px solid #333; border-radius: 12px; padding: 20px; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #2a2d3a; padding-bottom: 12px; margin-bottom: 16px; }
        .btn-admin { background: #00f2ff; color: #0a0b0d; font-weight: bold; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-secondary { background: #374151; color: #e5e7eb; }
        .input-group { margin-bottom: 12px; }
        .input-group label { display: block; margin-bottom: 4px; color: #94a3b8; font-size: 0.8rem; font-weight: bold; }
        .input-group input, .input-group textarea, .input-group select { width: 100%; padding: 8px 10px; background: #0c0d12; border: 1px solid #333; color: #fff; border-radius: 6px; }
        .topic-card { background: #12141c; border: 1px solid #2a2e3d; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
        .q-item { background: #1c1f2b; padding: 8px 10px; border-radius: 6px; margin-top: 6px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
        .pdf-box { background: #1e293b; border: 2px dashed #00f2ff; padding: 18px; border-radius: 8px; margin-bottom: 18px; text-align: center; }
    </style>
</head>
<body>
<div class="admin-box">
    <?php if (!$isAdmin): ?>
        <h2>🔐 Tidskrøll Lærer/Admin</h2>
        <p style="color: #94a3b8; margin-bottom: 15px;">Logg inn med administrator-passord eller admin-PIN (1313):</p>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="input-group"><label>Brukernavn (valgfritt hvis PIN)</label><input type="text" name="username" placeholder="Voxcuriosa"></div>
            <div class="input-group"><label>Passord / Admin-PIN (1313)</label><input type="password" name="password" placeholder="Admin-PIN 1313" required autofocus></div>
            <button type="submit" name="login" class="btn-admin">Logg inn</button>
            <a href="index.html" class="btn-admin btn-secondary">Tilbake til spillet</a>
        </form>
    <?php else: ?>
        <div class="admin-header">
            <div><h2 style="margin:0; color:#00f2ff;">⚡ Administrer Pensum-PDF & Spørsmål</h2></div>
            <div style="display:flex; gap: 8px; align-items: center;">
                <form method="POST" onsubmit="return confirm('Er du sikker på at du vil slette og nullstille hele topplisten (highscores)?');" style="margin:0;">
                    <button type="submit" name="reset_highscores" class="btn-admin btn-danger">🗑️ Nullstill Highscores</button>
                </form>
                <a href="index.html" class="btn-admin btn-secondary">🎮 Til spill</a>
                <a href="?logout=1" class="btn-admin btn-secondary">Logg ut</a>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Primary PDF Upload Section -->
        <div class="pdf-box">
            <h3 style="margin-top:0; color: #00f2ff;">📄 1. Last opp Pensum-PDF</h3>
            <p style="font-size:0.85rem; color:#cbd5e1; margin-bottom: 12px;">Velg en PDF med pensum for å automatisk hente ut tekst og generere flervalgsspørsmål (MCQ):</p>
            <input type="file" id="pdf-file-input" accept=".pdf" style="margin-bottom: 8px;">
            <div id="pdf-status" style="font-size: 0.85rem; color: #38bdf8; font-weight: bold; margin-top: 5px;"></div>
        </div>

        <!-- Import / Save Form -->
        <div class="topic-card" id="import-box" style="margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #00f2ff;">💾 2. Bekreft og lagre spørsmål</h3>
            <form method="POST" style="margin-top: 10px;">
                <div class="input-group">
                    <label>Nytt temanavn (eller velg eksisterende nedenfor):</label>
                    <input type="text" name="new_topic_title" id="pdf-topic-title" placeholder="F.eks. Kapittel 5: Den kalde krigen">
                </div>
                <div class="input-group">
                    <label>Eller legg til i eksisterende tema:</label>
                    <select name="import_topic" id="import-topic-select">
                        <option value="">-- Velg eksisterende tema --</option>
                        <?php foreach ($questionsData['topics'] as $t): ?>
                            <option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Genererte spørsmål (JSON-format):</label>
                    <textarea name="batch_json" id="batch-json-input" rows="6" placeholder="Spørsmålene fra PDF-en vises her automatisk..." required></textarea>
                </div>
                <button type="submit" name="import_batch" class="btn-admin" style="font-size: 1rem; padding: 10px 18px;">Lagre tema og spørsmål 🚀</button>
            </form>
        </div>

        <h3 style="margin: 20px 0 10px 0;">📚 Aktive Pensumtemaer (Fra PDF)</h3>
        <?php if (empty($questionsData['topics'])): ?>
            <p style="color: #94a3b8; font-size: 0.9rem;">Ingen pensumtemaer er lastet opp ennå. Last opp din første PDF ovenfor!</p>
        <?php endif; ?>

        <?php foreach ($questionsData['topics'] as $t): ?>
            <div class="topic-card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin: 0; color: #38bdf8;">📄 <?= htmlspecialchars($t['title']) ?></h4>
                        <small style="color: #94a3b8;"><?= count($t['questions']) ?> spørsmål &bull; <?= htmlspecialchars($t['description']) ?></small>
                    </div>
                    <form method="POST" onsubmit="return confirm('Vil du slette dette temaet?');" style="margin:0;">
                        <input type="hidden" name="delete_topic_id" value="<?= htmlspecialchars($t['id']) ?>">
                        <button type="submit" name="delete_topic" class="btn-admin btn-danger" style="padding: 4px 8px; font-size: 0.75rem;">Slett tema</button>
                    </form>
                </div>
                <?php foreach ($t['questions'] as $qIdx => $q): ?>
                    <div class="q-item">
                        <div><strong>#<?= $qIdx + 1 ?>:</strong> <?= htmlspecialchars($q['question']) ?> <span style="color: #34d399;">(Riktig: <?= htmlspecialchars($q['options'][$q['answer']] ?? '') ?>)</span></div>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="del_q_topic" value="<?= htmlspecialchars($t['id']) ?>">
                            <input type="hidden" name="del_q_idx" value="<?= $qIdx ?>">
                            <button type="submit" name="delete_q" class="btn-admin btn-danger" style="padding: 2px 6px; font-size: 0.7rem;">✕</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <script type="module">
            import { extractTextFromPDF, generateQuestionsFromText } from './js/pdf_importer.js';

            const fileInput = document.getElementById('pdf-file-input');
            const statusEl = document.getElementById('pdf-status');
            const titleInput = document.getElementById('pdf-topic-title');
            const jsonTextarea = document.getElementById('batch-json-input');

            if (fileInput) {
                fileInput.addEventListener('change', async (e) => {
                    const file = e.target.files[0];
                    if (!file) return;
                    // Auto-set title from filename (remove .pdf and clean underscores)
                    const cleanName = file.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ');
                    titleInput.value = cleanName;

                    statusEl.textContent = '⏳ Leser PDF og henter ut tekst...';
                    try {
                        const text = await extractTextFromPDF(file);
                        statusEl.textContent = `✅ PDF lest (${text.length} tegn). Genererer spørsmål...`;
                        const questions = generateQuestionsFromText(text);
                        jsonTextarea.value = JSON.stringify(questions, null, 2);
                        statusEl.textContent = `🎉 Fantastisk! ${questions.length} spørsmål generert fra "${file.name}". Klikk "Lagre tema og spørsmål" nedenfor for å aktivere i spillet!`;
                    } catch (err) {
                        statusEl.textContent = '❌ Kunne ikke lese PDF: ' + err.message;
                    }
                });
            }
        </script>
    <?php endif; ?>
</div>
</body>
</html>
