// Main Application Controller for Pedagogisk Læringsanimator

import { PRESETS } from "./presets.js";
import { extractTextFromPDF } from "./pdf-parser.js";
import { analyzePedagogicalText } from "./pedagogy-engine.js";
import { SceneCanvas } from "./scene-canvas.js";
import { Scene3D } from "./scene-3d.js";
import { TimelineView } from "./timeline-view.js";
import { NetworkGraph } from "./network-graph.js";
import { SimulationLab } from "./simulation-lab.js";

class LearningAnimatorApp {
    constructor() {
        this.currentData = null;
        this.activeTab = "scene";
        this.activeRenderMode = "3d";

        this.initDOMElements();
        this.initVisualizers();
        this.populatePresets();
        this.bindEvents();
        this.loadPreset("1814_norge");
    }

    initDOMElements() {
        this.inputText = document.getElementById("input-text");
        this.fileInput = document.getElementById("file-input");
        this.fileNameDisplay = document.getElementById("file-name-display");
        this.generateBtn = document.getElementById("generate-btn");
        this.presetSelect = document.getElementById("preset-select");
        this.loadingOverlay = document.getElementById("loading-overlay");
        this.loadingText = document.getElementById("loading-text");
        this.tabButtons = document.querySelectorAll(".tab-btn");
        this.tabContents = document.querySelectorAll(".tab-content");

        this.mode3dBtn = document.getElementById("mode-3d-btn");
        this.mode2dBtn = document.getElementById("mode-2d-btn");
        this.scene3dContainer = document.getElementById("scene-3d-container");
        this.sceneCanvasEl = document.getElementById("scene-canvas");

        this.themeTitle = document.getElementById("theme-title");
        this.themeSubtitle = document.getElementById("theme-subtitle");
        this.themeCategory = document.getElementById("theme-category");
        this.themeStats = document.getElementById("theme-stats");

        this.storyStepLabel = document.getElementById("story-step-label");
        this.storyNarrative = document.getElementById("story-narrative");
        this.storyPrompt = document.getElementById("story-prompt");
        this.playPauseBtn = document.getElementById("play-pause-btn");
        this.prevStepBtn = document.getElementById("prev-step-btn");
        this.nextStepBtn = document.getElementById("next-step-btn");
        this.speedSelect = document.getElementById("speed-select");
        this.fullscreenBtn = document.getElementById("fullscreen-btn");
        this.speakNarrativeBtn = document.getElementById("speak-narrative-btn");
    }

    initVisualizers() {
        if (this.scene3dContainer && window.THREE) {
            this.scene3dVisualizer = new Scene3D(this.scene3dContainer);
            this.scene3dVisualizer.onStepChange = (idx) => this.updateStoryboardUI(idx);
        }
        if (this.sceneCanvasEl) {
            this.scene2dVisualizer = new SceneCanvas(this.sceneCanvasEl);
            this.scene2dVisualizer.onStepChange = (idx) => this.updateStoryboardUI(idx);
        }
        const tlEl = document.getElementById("timeline-container");
        if (tlEl) this.timelineVisualizer = new TimelineView(tlEl);

        const netEl = document.getElementById("network-canvas");
        if (netEl) this.networkVisualizer = new NetworkGraph(netEl);

        const labEl = document.getElementById("lab-container");
        if (labEl) this.labVisualizer = new SimulationLab(labEl);
    }

    populatePresets() {
        if (!this.presetSelect) return;
        this.presetSelect.innerHTML = `<option value="">-- Velg et ferdig fagtema --</option>` +
            PRESETS.map(p => `<option value="${p.id}">${p.title}</option>`).join("");
    }

    bindEvents() {
        this.presetSelect?.addEventListener("change", (e) => e.target.value && this.loadPreset(e.target.value));
        this.fileInput?.addEventListener("change", async (e) => e.target.files[0] && await this.handleFileUpload(e.target.files[0]));
        this.generateBtn?.addEventListener("click", () => this.generateAnimation());

        this.mode3dBtn?.addEventListener("click", () => this.setRenderMode("3d"));
        this.mode2dBtn?.addEventListener("click", () => this.setRenderMode("2d"));

        this.tabButtons.forEach(btn => btn.addEventListener("click", () => this.switchTab(btn.dataset.tab)));

        this.playPauseBtn?.addEventListener("click", () => {
            const isPlaying = this.getActiveSceneVisualizer().togglePlay();
            this.playPauseBtn.innerHTML = isPlaying ? "⏸ Pause" : "▶ Spill av";
            this.playPauseBtn.classList.toggle("playing", isPlaying);
        });

        this.prevStepBtn?.addEventListener("click", () => {
            const vis = this.getActiveSceneVisualizer();
            vis.prevStep();
            this.updateStoryboardUI(vis.currentStep);
        });

        this.nextStepBtn?.addEventListener("click", () => {
            const vis = this.getActiveSceneVisualizer();
            vis.nextStep();
            this.updateStoryboardUI(vis.currentStep);
        });

        this.speedSelect?.addEventListener("change", (e) => {
            const spd = parseFloat(e.target.value);
            if (this.scene3dVisualizer) this.scene3dVisualizer.playbackSpeed = spd;
            if (this.scene2dVisualizer) this.scene2dVisualizer.playbackSpeed = spd;
        });

        this.fullscreenBtn?.addEventListener("click", () => {
            const viewer = document.getElementById("storyboard-viewer-card");
            if (viewer) {
                if (!document.fullscreenElement) viewer.requestFullscreen().catch(() => {});
                else document.exitFullscreen();
            }
        });

        this.speakNarrativeBtn?.addEventListener("click", () => {
            const ch = this.currentData?.storyboard?.[this.getActiveSceneVisualizer().currentStep];
            if (ch) this.speak(ch.stageTitle + ". " + ch.narrative);
        });
    }

    getActiveSceneVisualizer() {
        return (this.activeRenderMode === "3d" && this.scene3dVisualizer)
            ? this.scene3dVisualizer
            : this.scene2dVisualizer;
    }

    setRenderMode(mode) {
        this.activeRenderMode = mode;
        this.mode3dBtn?.classList.toggle("active", mode === "3d");
        this.mode2dBtn?.classList.toggle("active", mode === "2d");
        this.scene3dContainer?.classList.toggle("hidden", mode !== "3d");
        this.sceneCanvasEl?.classList.toggle("hidden", mode !== "2d");

        const cur = this.getActiveSceneVisualizer().currentStep;
        this.scene3dVisualizer?.goToStep(cur);
        this.scene2dVisualizer?.goToStep(cur);
    }

    loadPreset(presetId) {
        const preset = PRESETS.find(p => p.id === presetId);
        if (preset) {
            this.inputText.value = preset.text;
            if (this.presetSelect) this.presetSelect.value = presetId;
            if (this.fileNameDisplay) this.fileNameDisplay.textContent = `Forhåndsvalgt: ${preset.title}`;
            this.generateAnimation();
        }
    }

    async handleFileUpload(file) {
        this.showLoading(true, "Laster opp og behandler fil...");
        try {
            if (file.type === "application/pdf" || file.name.endsWith(".pdf")) {
                const text = await extractTextFromPDF(file, (pct, msg) => {
                    if (this.loadingText) this.loadingText.textContent = `${msg} (${pct}%)`;
                });
                this.inputText.value = text;
                this.fileNameDisplay.textContent = `📄 PDF: ${file.name} (${Math.round(file.size / 1024)} KB)`;
            } else {
                this.inputText.value = await file.text();
                this.fileNameDisplay.textContent = `📝 Tekstfil: ${file.name}`;
            }
            this.showLoading(false);
            this.generateAnimation();
        } catch (err) {
            this.showLoading(false);
            alert(err.message || "Feil ved filopplasting.");
        }
    }

    generateAnimation() {
        const text = this.inputText.value;
        if (!text || text.trim().length < 30) {
            alert("Vennligst skriv eller lim inn en kildetekst, eller velg et ferdig tema.");
            return;
        }

        this.showLoading(true, "Kjører pedagogisk analyse og bygger 3D/2D animasjoner...");
        setTimeout(() => {
            try {
                this.currentData = analyzePedagogicalText(text);
                this.renderAllViews();
                this.showLoading(false);
                document.getElementById("results-section")?.scrollIntoView({ behavior: "smooth", block: "start" });
            } catch (err) {
                this.showLoading(false);
                alert("Feil under generering: " + err.message);
            }
        }, 200);
    }

    renderAllViews() {
        if (!this.currentData) return;
        if (this.themeTitle) this.themeTitle.textContent = this.currentData.title;
        if (this.themeSubtitle) this.themeSubtitle.textContent = this.currentData.subtitle;
        if (this.themeCategory) this.themeCategory.textContent = this.currentData.category;
        if (this.themeStats) {
            this.themeStats.textContent = `${this.currentData.wordCount} ord • ~${this.currentData.readingTimeMin} min lesetid • ${this.currentData.timeline.length} tidslinjepunkter`;
        }

        this.scene3dVisualizer?.setChapters(this.currentData.storyboard);
        this.scene2dVisualizer?.setChapters(this.currentData.storyboard);
        this.updateStoryboardUI(0);

        this.timelineVisualizer?.render(this.currentData.timeline);
        this.networkVisualizer?.setData(this.currentData.network);
        this.labVisualizer?.render({
            simulations: this.currentData.simulations,
            sourceCritique: this.currentData.sourceCritique,
            quiz: this.currentData.quiz
        });
    }

    updateStoryboardUI(stepIdx) {
        const chapter = this.currentData?.storyboard?.[stepIdx];
        if (!chapter) return;
        if (this.storyStepLabel) this.storyStepLabel.textContent = `Steg ${stepIdx + 1} av ${this.currentData.storyboard.length}: ${chapter.stageTitle}`;
        if (this.storyNarrative) this.storyNarrative.textContent = chapter.narrative;
        if (this.storyPrompt) this.storyPrompt.innerHTML = `<strong>💡 Pedagogisk refleksjon:</strong> ${chapter.pedagogicalPrompt}`;
    }

    switchTab(tabKey) {
        this.activeTab = tabKey;
        this.tabButtons.forEach(b => b.classList.toggle("active", b.dataset.tab === tabKey));
        this.tabContents.forEach(c => c.classList.toggle("active", c.id === `${tabKey}-tab-pane`));
    }

    showLoading(show, msg = "Laster...") {
        this.loadingOverlay?.classList.toggle("hidden", !show);
        if (this.loadingText && msg) this.loadingText.textContent = msg;
    }

    speak(text) {
        if (!("speechSynthesis" in window)) return;
        window.speechSynthesis.cancel();
        const utt = new SpeechSynthesisUtterance(text);
        utt.lang = "no-NO";
        window.speechSynthesis.speak(utt);
    }
}

window.addEventListener("DOMContentLoaded", () => {
    window.app = new LearningAnimatorApp();
});
