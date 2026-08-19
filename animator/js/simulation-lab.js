// Simulation Lab & Pedagogical Active Recall Quiz Component

export class SimulationLab {
    constructor(containerElement) {
        this.container = containerElement;
        this.simulations = [];
        this.sourceCritique = [];
        this.quiz = [];
        this.userAnswers = {};
    }

    render(data) {
        this.simulations = data.simulations || [];
        this.sourceCritique = data.sourceCritique || [];
        this.quiz = data.quiz || [];
        this.userAnswers = {};

        let html = `
            <div class="lab-layout">
                <!-- 1. Hva hvis? Scenarier -->
                <div class="lab-section simulation-section">
                    <div class="section-badge">🎛️ Simuleringslaboratorium</div>
                    <h3>«Hva hvis?» – Påvirk samfunnsvariablene</h3>
                    <p class="section-intro">Juster glidebryterne for å utforske hvordan endringer i ytringsfrihet, ressursfordeling og maktbruk påvirker samfunnsstabiliteten.</p>
                    
                    <div class="sim-controls">
        `;

        this.simulations.forEach(sim => {
            html += `
                <div class="sim-card" data-sim-id="${sim.id}">
                    <div class="sim-header">
                        <span class="sim-title">${escapeHtml(sim.title)}</span>
                        <span class="sim-val" id="val-${sim.id}">${sim.default}${sim.unit}</span>
                    </div>
                    <p class="sim-desc">${escapeHtml(sim.description)}</p>
                    <input type="range" class="sim-slider" id="slider-${sim.id}" min="${sim.min}" max="${sim.max}" value="${sim.default}">
                    <div class="sim-feedback" id="feedback-${sim.id}">
                        ${escapeHtml(sim.default > 50 ? sim.highImpact : sim.lowImpact)}
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>

                <!-- 2. Kildekritikk & Perspektiver -->
                <div class="lab-section critique-section">
                    <div class="section-badge">🧐 Kildekritisk Dypdykk</div>
                    <h3>Kildekritikk & Perspektiver</h3>
                    <p class="section-intro">Bruk historiefaglig metode til å vurdere hvem som har stemmen i denne kildeteksten.</p>
                    <div class="critique-grid">
        `;

        this.sourceCritique.forEach(c => {
            html += `
                <div class="critique-card">
                    <h4>${escapeHtml(c.aspect)}</h4>
                    <p class="critique-q"><strong>Spørsmål:</strong> ${escapeHtml(c.question)}</p>
                    <div class="pedagogy-tip">💡 <em>Pedagogisk hint:</em> ${escapeHtml(c.pedagogyNote)}</div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>

                <!-- 3. Læringssjekk (Quiz) -->
                <div class="lab-section quiz-section">
                    <div class="section-badge">🎯 Aktiv Læringssjekk</div>
                    <h3>Test din pedagogiske forståelse</h3>
                    <p class="section-intro">Svar på spørsmålene nedenfor for å forankre nøkkelkunnskapen.</p>
                    <div class="quiz-list">
        `;

        this.quiz.forEach((q, qIdx) => {
            html += `
                <div class="quiz-item" data-qindex="${qIdx}">
                    <div class="quiz-question">Spørsmål ${qIdx + 1}: ${escapeHtml(q.question)}</div>
                    <div class="quiz-options">
            `;
            q.options.forEach((opt, oIdx) => {
                html += `
                    <button class="quiz-opt-btn" data-q="${qIdx}" data-opt="${oIdx}">${escapeHtml(opt)}</button>
                `;
            });
            html += `
                    </div>
                    <div class="quiz-explanation hidden" id="quiz-exp-${qIdx}">
                        ${escapeHtml(q.explanation)}
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;

        this.container.innerHTML = html;
        this.bindEvents();
    }

    bindEvents() {
        // Bind slider change events
        this.simulations.forEach(sim => {
            const slider = this.container.querySelector(`#slider-${sim.id}`);
            const valLabel = this.container.querySelector(`#val-${sim.id}`);
            const feedback = this.container.querySelector(`#feedback-${sim.id}`);

            if (slider) {
                slider.addEventListener("input", (e) => {
                    const val = parseInt(e.target.value, 10);
                    if (valLabel) valLabel.textContent = `${val}${sim.unit}`;
                    if (feedback) {
                        feedback.textContent = val >= 50 ? sim.highImpact : sim.lowImpact;
                        feedback.style.color = val >= 50 ? "#00f2ff" : "#f59e0b";
                    }
                });
            }
        });

        // Bind Quiz options
        const optButtons = this.container.querySelectorAll(".quiz-opt-btn");
        optButtons.forEach(btn => {
            btn.addEventListener("click", () => {
                const qIdx = parseInt(btn.dataset.q, 10);
                const optIdx = parseInt(btn.dataset.opt, 10);
                const qData = this.quiz[qIdx];
                if (!qData) return;

                const parent = btn.closest(".quiz-item");
                const allOpts = parent.querySelectorAll(".quiz-opt-btn");
                const exp = parent.querySelector(`#quiz-exp-${qIdx}`);

                // Mark choices
                allOpts.forEach((b, idx) => {
                    b.disabled = true;
                    if (idx === qData.correct) {
                        b.classList.add("correct");
                    } else if (idx === optIdx && optIdx !== qData.correct) {
                        b.classList.add("wrong");
                    }
                });

                if (exp) {
                    exp.classList.remove("hidden");
                }
            });
        });
    }
}

function escapeHtml(str) {
    if (!str) return "";
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}
