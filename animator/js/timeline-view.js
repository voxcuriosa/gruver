// Interactive Pedagogical Timeline Component

export class TimelineView {
    constructor(containerElement) {
        this.container = containerElement;
        this.events = [];
        this.activeEventIndex = 0;
        this.onSelectEvent = null;
    }

    render(events) {
        this.events = events || [];
        this.activeEventIndex = 0;

        if (this.events.length === 0) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <p>Ingen tidslinjehendelser funnet i kilden.</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="timeline-wrapper">
                <div class="timeline-track"></div>
                <div class="timeline-nodes">
        `;

        this.events.forEach((ev, idx) => {
            const isActive = idx === 0 ? "active" : "";
            html += `
                <div class="timeline-item ${isActive}" data-index="${idx}">
                    <div class="timeline-node" title="${ev.timeLabel}: ${ev.title}">
                        <span class="node-dot"></span>
                        <span class="node-badge">${ev.timeLabel}</span>
                    </div>
                    <div class="timeline-card">
                        <div class="card-header">
                            <span class="event-year">${ev.timeLabel}</span>
                            <span class="event-importance ${ev.importance}">${ev.importance === 'høy' ? '★ Nøkkelhendelse' : 'Delmål'}</span>
                        </div>
                        <h4 class="event-title">${escapeHtml(ev.title)}</h4>
                        <p class="event-desc">${escapeHtml(ev.description)}</p>
                        <div class="card-actions">
                            <button class="speak-btn" data-text="${escapeHtml(ev.timeLabel + '. ' + ev.title + '. ' + ev.description)}">🔊 Les opp</button>
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
            <div class="timeline-controls">
                <button class="nav-btn prev-timeline" title="Forrige hendelse">◀ Forrige</button>
                <span class="timeline-counter">1 / ${this.events.length}</span>
                <button class="nav-btn next-timeline" title="Neste hendelse">Neste ▶</button>
            </div>
        `;

        this.container.innerHTML = html;
        this.bindEvents();
    }

    bindEvents() {
        const items = this.container.querySelectorAll(".timeline-item");
        const counter = this.container.querySelector(".timeline-counter");
        const prevBtn = this.container.querySelector(".prev-timeline");
        const nextBtn = this.container.querySelector(".next-timeline");

        const setActive = (index) => {
            if (index < 0 || index >= this.events.length) return;
            this.activeEventIndex = index;
            items.forEach((it, i) => {
                it.classList.toggle("active", i === index);
            });
            if (counter) {
                counter.textContent = `${index + 1} / ${this.events.length}`;
            }
            if (this.onSelectEvent) {
                this.onSelectEvent(this.events[index], index);
            }
        };

        items.forEach((item, index) => {
            const node = item.querySelector(".timeline-node");
            if (node) {
                node.addEventListener("click", () => setActive(index));
            }
        });

        if (prevBtn) {
            prevBtn.addEventListener("click", () => setActive(this.activeEventIndex - 1));
        }

        if (nextBtn) {
            nextBtn.addEventListener("click", () => setActive(this.activeEventIndex + 1));
        }

        // Voice playback button
        const speakBtns = this.container.querySelectorAll(".speak-btn");
        speakBtns.forEach(btn => {
            btn.addEventListener("click", (e) => {
                e.stopPropagation();
                const text = btn.dataset.text;
                speakText(text);
            });
        });
    }

    selectEvent(index) {
        const items = this.container.querySelectorAll(".timeline-item");
        const counter = this.container.querySelector(".timeline-counter");
        if (items[index]) {
            this.activeEventIndex = index;
            items.forEach((it, i) => it.classList.toggle("active", i === index));
            if (counter) counter.textContent = `${index + 1} / ${this.events.length}`;
            items[index].scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
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

function speakText(text) {
    if (!("speechSynthesis" in window)) return;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = "no-NO";
    utterance.rate = 1.0;
    window.speechSynthesis.speak(utterance);
}
