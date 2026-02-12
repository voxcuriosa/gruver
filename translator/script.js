document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const authScreen = document.getElementById('auth-screen');
    const appScreen = document.getElementById('app-screen');
    const pinInput = document.getElementById('pin-input');
    const unlockBtn = document.getElementById('unlock-btn');

    const fileInput = document.getElementById('file-input');
    const fileNameDisplay = document.getElementById('file-name');
    const translateBtn = document.getElementById('translate-btn');
    const langCheckboxes = document.querySelectorAll('input[name="lang"]');

    const cueCountSpan = document.getElementById('cue-count');
    const estTimeSpan = document.getElementById('est-time');
    const estCostSpan = document.getElementById('est-cost');

    const progressContainer = document.getElementById('progress-container');
    const progressFill = document.getElementById('progress-fill');
    const statusText = document.getElementById('status-text');

    const resultArea = document.getElementById('result-area');
    const resultTabs = document.getElementById('result-tabs');
    const subtitleOutput = document.getElementById('subtitle-output');
    const copyBtn = document.getElementById('copy-btn');
    const downloadBtn = document.getElementById('download-btn');

    let currentCues = [];
    let savedPIN = '';
    let results = {}; // Stores { 'Lang': 'VTT Content' }

    // Auth Logic
    unlockBtn.addEventListener('click', async () => {
        const pin = pinInput.value;

        // Validate PIN with backend
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    pin: pin,
                    text: 'test',
                    target_lang: 'Norwegian (Bokmål)'
                })
            });

            if (response.ok) {
                savedPIN = pin;
                authScreen.classList.add('hidden');
                appScreen.classList.remove('hidden');
            } else {
                alert('Feil PIN-kode');
                pinInput.value = '';
            }
        } catch (error) {
            alert('Kunne ikke validere PIN. Sjekk tilkoblingen.');
            console.error(error);
        }
    });

    pinInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') unlockBtn.click();
    });

    // Language & File Logic
    function updateStats() {
        if (!currentCues.length) return;

        const selectedLangs = Array.from(langCheckboxes).filter(cb => cb.checked);
        const numLangs = selectedLangs.length;

        // Button Text
        if (numLangs === 0) {
            translateBtn.disabled = true;
            translateBtn.textContent = "Velg minst ett språk";
        } else {
            translateBtn.disabled = false;
            translateBtn.textContent = `Oversett (${numLangs} språk)`;
        }

        // Est time: ~0.5s per cue per language
        const estSeconds = Math.ceil((currentCues.length / 20 * 2) * numLangs);
        estTimeSpan.textContent = `Est. tid: ~${estSeconds}s`;

        // Est Cost Calculation (GPT-4o-mini)
        const content = currentCues.map(c => c.text).join(''); // Approx
        const charCount = content.length;
        const EstTokens = charCount / 4;
        const baseCostUSD = ((EstTokens / 1000000) * 0.15) + ((EstTokens / 1000000) * 0.60);
        const totalCostNOK = baseCostUSD * 11 * numLangs; // Multiply by languages

        if (totalCostNOK < 0.02) {
            estCostSpan.textContent = `Est. pris: < 0.02 kr`;
        } else {
            estCostSpan.textContent = `Est. pris: ~${totalCostNOK.toFixed(2)} kr`;
        }
    }

    langCheckboxes.forEach(cb => cb.addEventListener('change', updateStats));

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) {
            resetStats();
            return;
        }

        fileNameDisplay.textContent = `Valgt fil: ${file.name}`;

        const reader = new FileReader();
        reader.onload = (e) => {
            const content = e.target.result;
            processContent(content);
        };
        reader.readAsText(file);
    });

    function resetStats() {
        currentCues = [];
        translateBtn.disabled = true;
        cueCountSpan.textContent = "Venter på fil...";
        estTimeSpan.textContent = "";
        estCostSpan.textContent = "";
        fileNameDisplay.textContent = "";
    }

    function processContent(content) {
        if (!content || !content.trim()) {
            resetStats();
            return;
        }

        if (content.includes('-->')) {
            currentCues = content.trim().startsWith('WEBVTT') ? parseVTT(content) : parseSRT(content);

            if (currentCues.length > 0) {
                cueCountSpan.textContent = `${currentCues.length} linjer funnet`;
                updateStats();
            } else {
                cueCountSpan.textContent = "Ingen gyldige linjer funnet";
                translateBtn.disabled = true;
            }

        } else {
            cueCountSpan.textContent = "Ukjent format (mangler tidsstempler)";
            translateBtn.disabled = true;
        }
    }

    translateBtn.addEventListener('click', async () => {
        if (currentCues.length === 0) return;

        const selectedLangs = Array.from(langCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (selectedLangs.length === 0) {
            alert("Velg minst ett språk.");
            return;
        }

        translateBtn.disabled = true;
        progressContainer.classList.remove('hidden');
        resultArea.classList.add('hidden');
        progressFill.style.width = '0%';
        results = {};
        resultTabs.innerHTML = '';

        try {
            for (let i = 0; i < selectedLangs.length; i++) {
                const lang = selectedLangs[i];
                statusText.textContent = `Oversetter til ${lang} (${i + 1}/${selectedLangs.length})...`;

                const translatedCues = await translateBatches(currentCues, lang, selectedLangs.length, i);
                results[lang] = generateVTT(translatedCues);
            }

            // Setup Tabs
            selectedLangs.forEach((lang, idx) => {
                const btn = document.createElement('button');
                btn.className = 'tab-btn' + (idx === 0 ? ' active' : '');
                // Simplify label for button (remove parentheticals for UI)
                const shortLabel = lang.split(' ')[0];
                btn.textContent = shortLabel;
                btn.addEventListener('click', () => switchTab(lang, btn));
                resultTabs.appendChild(btn);
            });

            // Show first result
            switchTab(selectedLangs[0], resultTabs.firstChild);

            resultArea.classList.remove('hidden');
            statusText.textContent = "Ferdig!";
            progressFill.style.width = '100%';

        } catch (error) {
            console.error(error);
            alert('Feil: ' + error.message);
            statusText.textContent = "Feil oppstod.";
        } finally {
            translateBtn.disabled = false;
        }
    });

    function switchTab(lang, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        subtitleOutput.value = results[lang];
        downloadBtn.onclick = () => downloadFile(lang);
    }

    function downloadFile(lang) {
        const content = results[lang];
        const blob = new Blob([content], { type: 'text/vtt' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        // Clean filename
        const safeLang = lang.split(' ')[0].toLowerCase();
        a.download = `subtitle_${safeLang}.vtt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Parsers & Generators...
    // (Reusable functions)
    function parseTimestamp(timestampStr) {
        timestampStr = timestampStr.trim().replace(',', '.');
        const parts = timestampStr.split(':');
        let seconds = 0;
        if (parts.length === 3) {
            seconds += parseInt(parts[0]) * 3600;
            seconds += parseInt(parts[1]) * 60;
            seconds += parseFloat(parts[2]);
        } else if (parts.length === 2) {
            seconds += parseInt(parts[0]) * 60;
            seconds += parseFloat(parts[1]);
        }
        return seconds;
    }

    function formatTimestampVTT(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        const h = hours.toString().padStart(2, '0');
        const m = minutes.toString().padStart(2, '0');
        const s = secs.toFixed(3).padStart(6, '0');
        return `${h}:${m}:${s}`;
    }

    function parseSRT(content) {
        const cues = [];
        const blocks = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim().split('\n\n');
        for (const block of blocks) {
            const lines = block.split('\n');
            if (lines.length >= 2) {
                let timeLineIdx = -1;
                for (let i = 0; i < lines.length; i++) {
                    if (lines[i].includes('-->')) { timeLineIdx = i; break; }
                }
                if (timeLineIdx !== -1 && lines.length > timeLineIdx) {
                    const times = lines[timeLineIdx].split(' --> ');
                    if (times.length === 2) {
                        const start = parseTimestamp(times[0]);
                        const end = parseTimestamp(times[1]);
                        const text = lines.slice(timeLineIdx + 1).join('\n');
                        cues.push({ start, end, text });
                    }
                }
            }
        }
        return cues;
    }

    function parseVTT(content) {
        const cues = [];
        const lines = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        let i = 0;
        while (i < lines.length) {
            const line = lines[i].trim();
            if (line === "WEBVTT" || line === "") { i++; continue; }
            if (line.includes('-->')) {
                const times = line.split(' --> ');
                if (times.length === 2) {
                    const start = parseTimestamp(times[0]);
                    const end = parseTimestamp(times[1]);
                    let textLines = [];
                    i++;
                    while (i < lines.length && lines[i].trim() !== "") {
                        textLines.push(lines[i].trim());
                        i++;
                    }
                    cues.push({ start, end, text: textLines.join('\n') });
                } else { i++; }
            } else { i++; }
        }
        return cues;
    }

    function generateVTT(cues) {
        let output = "WEBVTT\n\n";
        cues.forEach(cue => {
            output += `${formatTimestampVTT(cue.start)} --> ${formatTimestampVTT(cue.end)}\n`;
            output += `${cue.text}\n\n`;
        });
        return output;
    }

    async function translateBatches(cues, targetLang, totalLangs, currentLangIdx) {
        const BATCH_SIZE = 20;
        const totalBatches = Math.ceil(cues.length / BATCH_SIZE);
        let translatedCues = [];

        for (let i = 0; i < cues.length; i += BATCH_SIZE) {
            const batch = cues.slice(i, i + BATCH_SIZE);
            const batchIdx = Math.floor(i / BATCH_SIZE);

            // Calc overall progress
            // Total segments = totalBatches * totalLangs
            // Completed = (currentLangIdx * totalBatches) + batchIdx
            const globalProgress = ((currentLangIdx * totalBatches) + batchIdx) / (totalBatches * totalLangs);
            progressFill.style.width = `${globalProgress * 100}%`;

            const textChunk = batch.map(c => c.text).join('\n<SEP>\n');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pin: savedPIN,
                        text: textChunk,
                        target_lang: targetLang
                    })
                });

                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                if (data.error) throw new Error(data.error);

                const translatedText = data.translated_text;
                const segments = translatedText.split('<SEP>');

                batch.forEach((cue, idx) => {
                    const newCue = { ...cue };
                    if (idx < segments.length) newCue.text = segments[idx].trim();
                    translatedCues.push(newCue);
                });

            } catch (err) {
                console.warn("Batch failed", err);
                batch.forEach(c => translatedCues.push(c));
            }
        }
        return translatedCues;
    }

    // Init copy logic
    copyBtn.addEventListener('click', () => {
        subtitleOutput.select();
        document.execCommand('copy');
        const origText = copyBtn.textContent;
        copyBtn.textContent = "Kopiert!";
        setTimeout(() => copyBtn.textContent = origText, 2000);
    });
});
