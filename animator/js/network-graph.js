// Interactive Concept & Actor Force-Directed Network Graph

export class NetworkGraph {
    constructor(canvasElement) {
        this.canvas = canvasElement;
        this.ctx = this.canvas.getContext("2d");
        this.nodes = [];
        this.links = [];
        this.selectedNode = null;
        this.draggedNode = null;
        this.animationFrameId = null;

        this.initResize();
        this.bindMouseEvents();
        this.startLoop();
    }

    initResize() {
        const resize = () => {
            const rect = this.canvas.parentElement.getBoundingClientRect();
            this.width = rect.width;
            this.height = Math.min(rect.height || 380, 480);
            this.canvas.width = this.width * window.devicePixelRatio;
            this.canvas.height = this.height * window.devicePixelRatio;
            this.canvas.style.width = `${this.width}px`;
            this.canvas.style.height = `${this.height}px`;
            this.ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        };
        window.addEventListener("resize", resize);
        setTimeout(resize, 50);
    }

    setData(data) {
        const w = this.width || 600;
        const h = this.height || 380;
        const centerX = w / 2;
        const centerY = h / 2;

        this.nodes = (data.nodes || []).map((n, i) => {
            const angle = (i / data.nodes.length) * Math.PI * 2;
            const dist = n.id === "core" ? 0 : 120 + Math.random() * 40;
            return {
                ...n,
                x: centerX + Math.cos(angle) * dist,
                y: centerY + Math.sin(angle) * dist,
                vx: 0,
                vy: 0,
                pulse: Math.random() * Math.PI * 2
            };
        });

        this.links = data.links || [];
    }

    bindMouseEvents() {
        let mouseX = 0;
        let mouseY = 0;

        const getPos = (e) => {
            const rect = this.canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
        };

        this.canvas.addEventListener("mousedown", (e) => {
            const pos = getPos(e);
            this.draggedNode = this.findNodeAt(pos.x, pos.y);
            if (this.draggedNode) {
                this.selectedNode = this.draggedNode;
            }
        });

        window.addEventListener("mousemove", (e) => {
            if (this.draggedNode) {
                const pos = getPos(e);
                this.draggedNode.x = pos.x;
                this.draggedNode.y = pos.y;
                this.draggedNode.vx = 0;
                this.draggedNode.vy = 0;
            }
        });

        window.addEventListener("mouseup", () => {
            this.draggedNode = null;
        });

        // Touch support
        this.canvas.addEventListener("touchstart", (e) => {
            if (e.touches.length > 0) {
                const rect = this.canvas.getBoundingClientRect();
                const tx = e.touches[0].clientX - rect.left;
                const ty = e.touches[0].clientY - rect.top;
                this.draggedNode = this.findNodeAt(tx, ty);
                if (this.draggedNode) this.selectedNode = this.draggedNode;
            }
        }, { passive: true });

        this.canvas.addEventListener("touchmove", (e) => {
            if (this.draggedNode && e.touches.length > 0) {
                const rect = this.canvas.getBoundingClientRect();
                this.draggedNode.x = e.touches[0].clientX - rect.left;
                this.draggedNode.y = e.touches[0].clientY - rect.top;
            }
        }, { passive: true });

        this.canvas.addEventListener("touchend", () => {
            this.draggedNode = null;
        });
    }

    findNodeAt(x, y) {
        return this.nodes.find(n => {
            const dx = n.x - x;
            const dy = n.y - y;
            return Math.sqrt(dx * dx + dy * dy) <= n.radius + 6;
        });
    }

    startLoop() {
        const render = () => {
            this.simulatePhysics();
            this.draw();
            this.animationFrameId = requestAnimationFrame(render);
        };
        this.animationFrameId = requestAnimationFrame(render);
    }

    simulatePhysics() {
        const w = this.width || 600;
        const h = this.height || 380;
        const centerX = w / 2;
        const centerY = h / 2;

        // Repulsion between nodes
        for (let i = 0; i < this.nodes.length; i++) {
            const n1 = this.nodes[i];
            if (n1 === this.draggedNode) continue;

            // Gravity towards center
            n1.vx += (centerX - n1.x) * 0.001;
            n1.vy += (centerY - n1.y) * 0.001;

            for (let j = i + 1; j < this.nodes.length; j++) {
                const n2 = this.nodes[j];
                const dx = n2.x - n1.x;
                const dy = n2.y - n1.y;
                const dist = Math.sqrt(dx * dx + dy * dy) || 1;
                const minDist = n1.radius + n2.radius + 40;

                if (dist < minDist) {
                    const force = (minDist - dist) / dist * 0.04;
                    n1.vx -= dx * force;
                    n1.vy -= dy * force;
                    if (n2 !== this.draggedNode) {
                        n2.vx += dx * force;
                        n2.vy += dy * force;
                    }
                }
            }

            // Damping & speed limits
            n1.vx *= 0.88;
            n1.vy *= 0.88;
            n1.x += n1.vx;
            n1.y += n1.vy;
            n1.pulse += 0.03;

            // Bounds constraint
            n1.x = Math.max(n1.radius, Math.min(w - n1.radius, n1.x));
            n1.y = Math.max(n1.radius, Math.min(h - n1.radius, n1.y));
        }
    }

    draw() {
        const ctx = this.ctx;
        const w = this.width || 600;
        const h = this.height || 380;

        ctx.clearRect(0, 0, w, h);

        if (this.nodes.length === 0) {
            ctx.fillStyle = "rgba(255, 255, 255, 0.4)";
            ctx.font = "14px 'Outfit', sans-serif";
            ctx.textAlign = "center";
            ctx.fillText("Generer en pedagogisk analyse for å se aktørnettverket.", w / 2, h / 2);
            return;
        }

        // 1. Draw Links
        this.links.forEach(l => {
            const source = this.nodes.find(n => n.id === l.source);
            const target = this.nodes.find(n => n.id === l.target);
            if (source && target) {
                ctx.beginPath();
                ctx.moveTo(source.x, source.y);
                ctx.lineTo(target.x, target.y);
                ctx.strokeStyle = "rgba(0, 242, 255, 0.25)";
                ctx.lineWidth = 1.5;
                ctx.stroke();
            }
        });

        // 2. Draw Nodes
        this.nodes.forEach(n => {
            const pulse = 1 + Math.sin(n.pulse) * 0.08;
            const r = n.radius * pulse;

            ctx.beginPath();
            ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
            ctx.fillStyle = n.color || "#00f2ff";
            ctx.shadowColor = n.color || "#00f2ff";
            ctx.shadowBlur = 12;
            ctx.fill();
            ctx.shadowBlur = 0;

            // Border
            ctx.strokeStyle = "rgba(255, 255, 255, 0.8)";
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // Label
            ctx.fillStyle = "#ffffff";
            ctx.font = `bold ${n.id === "core" ? "12px" : "11px"} 'Outfit', sans-serif`;
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            
            // Short label
            const displayLabel = n.label.length > 18 ? n.label.slice(0, 16) + ".." : n.label;
            ctx.fillText(displayLabel, n.x, n.y + (n.id === "core" ? 0 : n.radius + 14));
        });
    }

    destroy() {
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
        }
    }
}
