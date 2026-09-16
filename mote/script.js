// script.js - MøteSluket logikk og sanntidskalkulator

// Elementer
const setupView = document.getElementById('setup-view');
const liveView = document.getElementById('live-view');
const summaryModal = document.getElementById('summary-modal');

const participantsInput = document.getElementById('participants');
const salaryInput = document.getElementById('salary');
const overheadToggle = document.getElementById('overhead-toggle');
const burnPreviewText = document.getElementById('burn-preview-text');

const btnDecPart = document.getElementById('btn-dec-part');
const btnIncPart = document.getElementById('btn-inc-part');
const salaryPills = document.querySelectorAll('.pill-btn');

const btnStart = document.getElementById('btn-start');
const btnPause = document.getElementById('btn-pause');
const btnStop = document.getElementById('btn-stop');
const btnRestart = document.getElementById('btn-restart');
const btnCopySlack = document.getElementById('btn-copy-slack');

const liveCost = document.getElementById('live-cost');
const liveTime = document.getElementById('live-time');
const metricPerMin = document.getElementById('metric-per-min');
const metricParticipants = document.getElementById('metric-participants');
const liveTeaser = document.getElementById('live-teaser');
const liveTeaserText = document.getElementById('live-teaser-text');

const modalCost = document.getElementById('modal-cost');
const modalSub = document.getElementById('modal-sub');
const equivalentsContainer = document.getElementById('equivalents-container');

const manualEmployeesInput = document.getElementById('manual-employees');
const manualHoursInput = document.getElementById('manual-hours');
const btnManualCalc = document.getElementById('btn-manual-calc');

// Konstanter og tilstand
const ANNUAL_HOURS = 1750; // Norsk standard normalårsverk (ca 230 arb. dager à 7.5t)
const OVERHEAD_MULTIPLIER = 1.20; // +20% rene sosiale personalkostnader (AGA 14.1% + OTP/pensjon & forsikring ~5.9%)

let isRunning = false;
let isPaused = false;
let startTime = 0;
let accumulatedPausedTime = 0;
let pauseStartTime = 0;
let animationFrameId = null;

let costPerSecondTotal = 0;
let costPerMinuteTotal = 0;
let finalCost = 0;
let finalDurationSeconds = 0;

// Beregninger
function updateCalculations() {
    const participants = Math.max(1, parseInt(participantsInput.value, 10) || 1);
    const salary = Math.max(10000, parseFloat(salaryInput.value) || 0);
    const includeOverhead = overheadToggle.checked;

    const effectiveSalary = includeOverhead ? (salary * OVERHEAD_MULTIPLIER) : salary;
    const hourlyRatePerPerson = effectiveSalary / ANNUAL_HOURS;
    const totalHourlyRate = hourlyRatePerPerson * participants;

    costPerSecondTotal = totalHourlyRate / 3600;
    costPerMinuteTotal = totalHourlyRate / 60;

    // Oppdater preview
    const krPrMinFormatted = Math.round(costPerMinuteTotal).toLocaleString('no-NO');
    burnPreviewText.innerHTML = `<strong>${krPrMinFormatted} kr</strong> per minutt (${Math.round(costPerSecondTotal * 10) / 10} kr/sek)`;
}

// Event Listeners for oppsett
participantsInput.addEventListener('input', updateCalculations);
salaryInput.addEventListener('input', () => {
    salaryPills.forEach(p => p.classList.remove('active'));
    updateCalculations();
});
overheadToggle.addEventListener('change', updateCalculations);

btnDecPart.addEventListener('click', () => {
    const val = parseInt(participantsInput.value, 10) || 1;
    if (val > 1) {
        participantsInput.value = val - 1;
        updateCalculations();
    }
});

btnIncPart.addEventListener('click', () => {
    const val = parseInt(participantsInput.value, 10) || 1;
    participantsInput.value = val + 1;
    updateCalculations();
});

salaryPills.forEach(pill => {
    pill.addEventListener('click', () => {
        salaryPills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        salaryInput.value = pill.getAttribute('data-val');
        updateCalculations();
    });
});

// Tidsformatering
function formatTime(totalSeconds) {
    const hrs = Math.floor(totalSeconds / 3600);
    const mins = Math.floor((totalSeconds % 3600) / 60);
    const secs = Math.floor(totalSeconds % 60);

    const pad = (n) => String(n).padStart(2, '0');
    if (hrs > 0) {
        return `${pad(hrs)}:${pad(mins)}:${pad(secs)}`;
    }
    return `${pad(mins)}:${pad(secs)}`;
}

// Live oppdatering
function tick() {
    if (!isRunning) return;

    if (!isPaused) {
        const now = performance.now();
        const elapsedMs = now - startTime - accumulatedPausedTime;
        const elapsedSec = elapsedMs / 1000;

        const currentCost = elapsedSec * costPerSecondTotal;
        finalCost = currentCost;
        finalDurationSeconds = elapsedSec;

        // Vis kostnad med 2 desimaler
        const costFormatted = currentCost.toLocaleString('no-NO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        liveCost.innerHTML = `${costFormatted}<span class="currency">kr</span>`;

        // Vis tid
        liveTime.textContent = formatTime(elapsedSec);

        // Teaser oppdatering ved milepæler
        updateLiveTeaser(currentCost);
    }

    animationFrameId = requestAnimationFrame(tick);
}

let lastTeaserUpdate = 0;
let currentTeaserHtml = '';

function updateLiveTeaser(cost) {
    const now = performance.now();
    // Oppdater kun hvert 10. sekund så teksten står stille og er lett å lese
    if (now - lastTeaserUpdate < 10000 && currentTeaserHtml !== '') {
        return;
    }
    lastTeaserUpdate = now;

    const eq = getLiveEquivalent(cost);
    const newHtml = `<div class="teaser-icon">${eq.icon}</div><div class="teaser-text">${eq.text}</div>`;

    if (newHtml !== currentTeaserHtml) {
        currentTeaserHtml = newHtml;
        liveTeaser.innerHTML = newHtml;
    }
}

// Start møtet
btnStart.addEventListener('click', () => {
    updateCalculations();

    setupView.classList.add('hidden');
    liveView.classList.remove('hidden');

    metricPerMin.textContent = `${Math.round(costPerMinuteTotal).toLocaleString('no-NO')} kr`;
    metricParticipants.textContent = `${participantsInput.value} personer`;

    isRunning = true;
    isPaused = false;
    accumulatedPausedTime = 0;
    startTime = performance.now();

    tick();
});

// Pause / Fortsett
btnPause.addEventListener('click', () => {
    if (!isRunning) return;

    if (!isPaused) {
        isPaused = true;
        pauseStartTime = performance.now();
        btnPause.textContent = 'Fortsett';
        btnPause.style.background = 'rgba(0, 242, 255, 0.15)';
        btnPause.style.borderColor = 'var(--primary)';
    } else {
        isPaused = false;
        accumulatedPausedTime += (performance.now() - pauseStartTime);
        btnPause.textContent = 'Pause';
        btnPause.style.background = '';
        btnPause.style.borderColor = '';
    }
});

// Åpne modal med resultat og morsomme sammenligninger
function showSummaryModal(cost, durationSeconds, participantsCount) {
    finalCost = cost;
    finalDurationSeconds = durationSeconds;

    const costKr = Math.round(cost);
    modalCost.textContent = `${costKr.toLocaleString('no-NO')} kr`;

    const timeFormatted = formatTime(durationSeconds);
    modalSub.textContent = `Møtet varte i ${timeFormatted} med ${participantsCount} deltakere`;

    // Hent 5 morsomme ting
    const funThings = getFunEquivalents(cost);
    equivalentsContainer.innerHTML = '';

    funThings.forEach(item => {
        const card = document.createElement('div');
        card.className = 'equivalent-card';
        card.innerHTML = `
            <span class="eq-icon">${item.icon}</span>
            <span><strong>${item.countText}</strong> ${item.name}</span>
        `;
        equivalentsContainer.appendChild(card);
    });

    summaryModal.classList.add('active');
}

// Stopp møtet (live taksameter)
btnStop.addEventListener('click', () => {
    isRunning = false;
    if (animationFrameId) cancelAnimationFrame(animationFrameId);

    const parts = parseInt(participantsInput.value, 10) || 1;
    showSummaryModal(finalCost, finalDurationSeconds, parts);
});

// Manuell hurtigberegning
btnManualCalc.addEventListener('click', () => {
    const salary = Math.max(10000, parseFloat(salaryInput.value) || 0);
    const includeOverhead = overheadToggle.checked;
    const effectiveSalary = includeOverhead ? (salary * OVERHEAD_MULTIPLIER) : salary;
    const hourlyRatePerPerson = effectiveSalary / ANNUAL_HOURS;

    const employees = Math.max(1, parseInt(manualEmployeesInput.value, 10) || 1);
    const hours = Math.max(0.01, parseFloat(manualHoursInput.value) || 0);

    const totalCost = hourlyRatePerPerson * employees * hours;
    const durationSeconds = Math.round(hours * 3600);

    showSummaryModal(totalCost, durationSeconds, employees);
});

// Start nytt møte / Lukk modal
btnRestart.addEventListener('click', () => {
    summaryModal.classList.remove('active');
    liveView.classList.add('hidden');
    setupView.classList.remove('hidden');
    btnPause.textContent = 'Pause';
    btnPause.style.background = '';
    btnPause.style.borderColor = '';
    liveCost.innerHTML = `0,00<span class="currency">kr</span>`;
    liveTime.textContent = '00:00';
});

// Kopier oppsummering til Slack / Teams
btnCopySlack.addEventListener('click', () => {
    const costKr = Math.round(finalCost).toLocaleString('no-NO');
    const timeFormatted = formatTime(finalDurationSeconds);
    const parts = participantsInput.value;
    const funThings = getFunEquivalents(finalCost);

    let text = `💸 *Møteoppsummering fra MøteSluket*\n`;
    text += `⏱️ *Varighet:* ${timeFormatted}\n`;
    text += `👥 *Deltakere:* ${parts}\n`;
    text += `💰 *Total kostnad:* ${costKr} kr\n\n`;
    text += `💡 *Hva bedriften kunne fått i stedet:*\n`;
    funThings.forEach(item => {
        text += `• ${item.icon} ${item.countText} ${item.name}\n`;
    });
    text += `\nRegn ut ditt neste møte på: https://voxcuriosa.no/mote/`;

    navigator.clipboard.writeText(text).then(() => {
        const origText = btnCopySlack.innerHTML;
        btnCopySlack.innerHTML = `<span>✅ Kopiert til utklippstavlen!</span>`;
        setTimeout(() => {
            btnCopySlack.innerHTML = origText;
        }, 2500);
    }).catch(err => {
        alert('Kunne ikke kopiere automatisk. Her er teksten:\n\n' + text);
    });
});

// Init
updateCalculations();
