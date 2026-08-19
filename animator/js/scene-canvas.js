// Canvas 2D Interactive Scene Visualizer for Storyboard Animations

export class SceneCanvas {
    constructor(canvasElement) {
        this.canvas = canvasElement;
        this.ctx = this.canvas.getContext("2d");
        this.chapters = [];
        this.currentStep = 0;
        this.progress = 0;
        this.isPlaying = false;
        this.playbackSpeed = 1.0;
        this.particles = [];
        this.floatingNodes = [];
        this.animationFrameId = null;
        this.lastTime = 0;
        this.onStepChange = null;

        this.initResize();
        this.initParticles();
        this.startLoop();
    }

    initResize() {
        const resize = () => {
            const rect = this.canvas.parentElement.getBoundingClientRect();
            this.width = rect.width;
            this.height = Math.min(rect.height || 420, 500);
            this.canvas.width = this.width * window.devicePixelRatio;
            this.canvas.height = this.height * window.devicePixelRatio;
            this.canvas.style.width = `${this.width}px`;
            this.canvas.style.height = `${this.height}px`;
            this.ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        };
        window.addEventListener("resize", resize);
        setTimeout(resize, 50);
    }

    initParticles() {
        this.particles = [];
        for (let i = 0; i < 40; i++) {
            this.particles.push({
                x: Math.random() * 800,
                y: Math.random() * 450,
                vx: (Math.random() - 0.5) * 0.8,
                vy: (Math.random() - 0.5) * 0.8,
                size: Math.random() * 3 + 1,
                alpha: Math.random() * 0.5 + 0.2,
                color: i % 2 === 0 ? "#00f2ff" : "#7000ff"
            });
        }
    }

    setChapters(chapters) {
        this.chapters = chapters;
        this.currentStep = 0;
        this.progress = 0;
        this.buildStageElements();
    }

    buildStageElements() {
        this.floatingNodes = [];
        if (!this.chapters || this.chapters.length === 0) return;

        for (let i = 0; i < 5; i++) {
            const angle = (i / 5) * Math.PI * 2;
            const dist = 110 + (i % 2) * 40;
            this.floatingNodes.push({
                baseAngle: angle,
                dist,
                speed: 0.008 * (i % 2 === 0 ? 1 : -1),
                radius: 12 + (i === 0 ? 8 : 4),
                pulse: 0
            });
        }
    }

    togglePlay() {
        this.isPlaying = !this.isPlaying;
        return this.isPlaying;
    }

    goToStep(stepIndex) {
        if (stepIndex >= 0 && stepIndex < this.chapters.length) {
            this.currentStep = stepIndex;
            this.progress = 0;
            this.buildStageElements();
            if (this.onStepChange) this.onStepChange(this.currentStep);
        }
    }

    nextStep() {
        this.goToStep(this.currentStep < this.chapters.length - 1 ? this.currentStep + 1 : 0);
    }

    prevStep() {
        if (this.currentStep > 0) this.goToStep(this.currentStep - 1);
    }

    startLoop() {
        const render = (time) => {
            const dt = (time - this.lastTime) / 1000;
            this.lastTime = time;
            this.update(dt);
            this.draw();
            this.animationFrameId = requestAnimationFrame(render);
        };
        this.animationFrameId = requestAnimationFrame(render);
    }

    update(dt) {
        if (this.isPlaying && this.chapters.length > 0) {
            this.progress += dt / (6 / this.playbackSpeed);
            if (this.progress >= 1.0) {
                this.progress = 0;
                if (this.currentStep < this.chapters.length - 1) {
                    this.currentStep++;
                    this.buildStageElements();
                    if (this.onStepChange) this.onStepChange(this.currentStep);
                } else {
                    this.isPlaying = false;
                }
            }
        }

        const w = this.width || 800;
        const h = this.height || 450;
        this.particles.forEach(p => {
            p.x = (p.x + p.vx + w) % w;
            p.y = (p.y + p.vy + h) % h;
        });

        this.floatingNodes.forEach(node => {
            node.baseAngle += node.speed;
            node.pulse += 0.04;
        });
    }

    draw() {
        const ctx = this.ctx;
        const w = this.width || 800;
        const h = this.height || 450;
        ctx.clearRect(0, 0, w, h);

        // Background
        const grad = ctx.createRadialGradient(w / 2, h / 2, 20, w / 2, h / 2, w * 0.6);
        grad.addColorStop(0, "rgba(20, 28, 48, 0.95)");
        grad.addColorStop(1, "rgba(8, 10, 15, 1)");
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, w, h);

        // Ambient particles
        this.particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = p.alpha;
            ctx.fill();
        });
        ctx.globalAlpha = 1.0;

        if (!this.chapters || this.chapters.length === 0) {
            ctx.fillStyle = "rgba(255, 255, 255, 0.6)";
            ctx.font = "16px 'Outfit', sans-serif";
            ctx.textAlign = "center";
            ctx.fillText("✨ Lim inn tekst eller last opp PDF for å generere pedagogisk animasjon", w / 2, h / 2);
            return;
        }

        const currentChapter = this.chapters[this.currentStep];
        const centerX = w / 2;
        const centerY = h / 2 - 15;

        // Lines
        this.floatingNodes.forEach(node => {
            const nx = centerX + Math.cos(node.baseAngle) * node.dist;
            const ny = centerY + Math.sin(node.baseAngle) * (node.dist * 0.7);
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.lineTo(nx, ny);
            ctx.strokeStyle = `rgba(0, 242, 255, ${0.25 + Math.sin(node.pulse) * 0.15})`;
            ctx.lineWidth = 1.5;
            ctx.setLineDash([4, 4]);
            ctx.stroke();
            ctx.setLineDash([]);
        });

        // Center Orb
        const tension = currentChapter.tensionValue || 50;
        const orbRadius = 46 * (1 + Math.sin(Date.now() * 0.004) * (0.05 + tension / 500));
        const orbGrad = ctx.createRadialGradient(centerX, centerY, 5, centerX, centerY, orbRadius);
        orbGrad.addColorStop(0, "#00f2ff");
        orbGrad.addColorStop(0.6, tension > 70 ? "#ff007b" : "#7000ff");
        orbGrad.addColorStop(1, "rgba(0, 242, 255, 0)");

        ctx.beginPath();
        ctx.arc(centerX, centerY, orbRadius, 0, Math.PI * 2);
        ctx.fillStyle = orbGrad;
        ctx.fill();

        ctx.fillStyle = "#ffffff";
        ctx.font = "bold 13px 'Outfit', sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(`Fase ${this.currentStep + 1}`, centerX, centerY - 8);
        ctx.font = "11px sans-serif";
        ctx.fillStyle = "rgba(255,255,255,0.8)";
        ctx.fillText(`Spenning: ${tension}%`, centerX, centerY + 10);

        // Orbit nodes
        this.floatingNodes.forEach((node, idx) => {
            const nx = centerX + Math.cos(node.baseAngle) * node.dist;
            const ny = centerY + Math.sin(node.baseAngle) * (node.dist * 0.7);
            ctx.beginPath();
            ctx.arc(nx, ny, node.radius, 0, Math.PI * 2);
            ctx.fillStyle = idx % 2 === 0 ? "#7000ff" : "#10b981";
            ctx.fill();
        });

        // Banner & Scrubber
        ctx.fillStyle = "rgba(0, 0, 0, 0.4)";
        ctx.fillRect(15, 15, w - 30, 42);
        ctx.strokeStyle = "rgba(255, 255, 255, 0.1)";
        ctx.strokeRect(15, 15, w - 30, 42);
        ctx.fillStyle = "#00f2ff";
        ctx.font = "bold 15px 'Outfit', sans-serif";
        ctx.textAlign = "left";
        ctx.fillText(currentChapter.stageTitle, 30, 36);

        const barY = h - 10;
        ctx.fillStyle = "rgba(255, 255, 255, 0.1)";
        ctx.fillRect(15, barY, w - 30, 6);
        const totalProgress = (this.currentStep + this.progress) / this.chapters.length;
        const progGrad = ctx.createLinearGradient(15, barY, w - 30, barY);
        progGrad.addColorStop(0, "#00f2ff");
        progGrad.addColorStop(1, "#7000ff");
        ctx.fillStyle = progGrad;
        ctx.fillRect(15, barY, (w - 30) * totalProgress, 6);
    }

    destroy() {
        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
    }
}
