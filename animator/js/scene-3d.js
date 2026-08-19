// Three.js 3D Pedagogical Interactive Scene Visualizer

export class Scene3D {
    constructor(containerElement) {
        this.container = containerElement;
        this.chapters = [];
        this.currentStep = 0;
        this.isPlaying = false;
        this.playbackSpeed = 1.0;
        this.progress = 0;
        this.onStepChange = null;

        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.nodesGroup = null;
        this.particles = null;
        this.centerCore = null;
        this.animationFrameId = null;
        this.lastTime = 0;

        this.isMouseDown = false;
        this.mouseX = 0;
        this.mouseY = 0;
        this.targetRotationY = 0;
        this.targetRotationX = 0;

        this.initThree();
        this.bindEvents();
        this.startLoop();
    }

    initThree() {
        if (!window.THREE) return;
        const THREE = window.THREE;
        const width = this.container.clientWidth || 800;
        const height = Math.min(this.container.clientHeight || 420, 500);

        this.scene = new THREE.Scene();
        this.scene.fog = new THREE.FogExp2(0x0a0c10, 0.002);
        this.camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 1000);
        this.camera.position.set(0, 30, 140);
        this.camera.lookAt(0, 0, 0);

        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.container.appendChild(this.renderer.domElement);

        this.scene.add(new THREE.AmbientLight(0xffffff, 0.6));
        const pointLight = new THREE.PointLight(0x00f2ff, 2, 300);
        pointLight.position.set(0, 50, 50);
        this.scene.add(pointLight);

        this.nodesGroup = new THREE.Group();
        this.scene.add(this.nodesGroup);

        this.createNebula();
        this.createHoloCore();
        window.addEventListener("resize", () => this.onWindowResize());
    }

    createNebula() {
        const THREE = window.THREE;
        const count = 500;
        const geo = new THREE.BufferGeometry();
        const pos = new Float32Array(count * 3);
        const col = new Float32Array(count * 3);

        for (let i = 0; i < count * 3; i += 3) {
            pos[i] = (Math.random() - 0.5) * 400;
            pos[i + 1] = (Math.random() - 0.5) * 300;
            pos[i + 2] = (Math.random() - 0.5) * 400;
            const isCyan = Math.random() > 0.5;
            col[i] = isCyan ? 0.0 : 0.44;
            col[i + 1] = isCyan ? 0.95 : 0.0;
            col[i + 2] = 1.0;
        }

        geo.setAttribute("position", new THREE.BufferAttribute(pos, 3));
        geo.setAttribute("color", new THREE.BufferAttribute(col, 3));
        this.particles = new THREE.Points(geo, new THREE.PointsMaterial({
            size: 2.5,
            vertexColors: true,
            transparent: true,
            opacity: 0.6,
            blending: THREE.AdditiveBlending
        }));
        this.scene.add(this.particles);
    }

    createHoloCore() {
        const THREE = window.THREE;
        this.centerCore = new THREE.Mesh(
            new THREE.IcosahedronGeometry(14, 2),
            new THREE.MeshStandardMaterial({ color: 0x00f2ff, emissive: 0x00a3cc, wireframe: true })
        );
        this.centerCore.add(new THREE.Mesh(
            new THREE.SphereGeometry(8, 16, 16),
            new THREE.MeshBasicMaterial({ color: 0x7000ff, transparent: true, opacity: 0.7 })
        ));
        this.scene.add(this.centerCore);
    }

    setChapters(chapters) {
        this.chapters = chapters || [];
        this.currentStep = 0;
        this.progress = 0;
        this.rebuildNodes();
    }

    rebuildNodes() {
        if (!window.THREE || !this.nodesGroup) return;
        const THREE = window.THREE;
        while (this.nodesGroup.children.length > 0) this.nodesGroup.remove(this.nodesGroup.children[0]);

        for (let i = 0; i < 6; i++) {
            const angle = (i / 6) * Math.PI * 2;
            const dist = 45 + (i % 2) * 15;
            const x = Math.cos(angle) * dist, z = Math.sin(angle) * dist, y = Math.sin(i * 1.5) * 12;

            const mesh = new THREE.Mesh(
                new THREE.SphereGeometry(4 + (i === 0 ? 2 : 0), 16, 16),
                new THREE.MeshStandardMaterial({ color: i % 2 === 0 ? 0x00f2ff : 0xff007b })
            );
            mesh.position.set(x, y, z);
            this.nodesGroup.add(mesh);

            const lineGeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, 0, 0), new THREE.Vector3(x, y, z)]);
            this.nodesGroup.add(new THREE.Line(lineGeo, new THREE.LineBasicMaterial({ color: 0x00f2ff, transparent: true, opacity: 0.3 })));
        }
    }

    bindEvents() {
        const dom = this.renderer?.domElement;
        if (!dom) return;

        const updatePos = (clientX, clientY) => {
            const dx = clientX - this.mouseX, dy = clientY - this.mouseY;
            this.targetRotationY += dx * 0.005;
            this.targetRotationX = Math.max(-0.6, Math.min(0.6, this.targetRotationX + dy * 0.005));
            this.mouseX = clientX;
            this.mouseY = clientY;
        };

        dom.addEventListener("mousedown", (e) => { this.isMouseDown = true; this.mouseX = e.clientX; this.mouseY = e.clientY; });
        window.addEventListener("mousemove", (e) => { if (this.isMouseDown) updatePos(e.clientX, e.clientY); });
        window.addEventListener("mouseup", () => this.isMouseDown = false);

        dom.addEventListener("touchstart", (e) => {
            if (e.touches.length > 0) { this.isMouseDown = true; this.mouseX = e.touches[0].clientX; this.mouseY = e.touches[0].clientY; }
        }, { passive: true });
        dom.addEventListener("touchmove", (e) => {
            if (this.isMouseDown && e.touches.length > 0) updatePos(e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: true });
        dom.addEventListener("touchend", () => this.isMouseDown = false);
    }

    startLoop() {
        const render = (time) => {
            const dt = (time - this.lastTime) / 1000;
            this.lastTime = time;
            this.update(dt);
            if (this.renderer && this.scene && this.camera) this.renderer.render(this.scene, this.camera);
            this.animationFrameId = requestAnimationFrame(render);
        };
        this.animationFrameId = requestAnimationFrame(render);
    }

    update(dt) {
        if (!this.scene) return;
        if (this.isPlaying && this.chapters.length > 0) {
            this.progress += dt / (6 / this.playbackSpeed);
            if (this.progress >= 1.0) {
                this.progress = 0;
                if (this.currentStep < this.chapters.length - 1) {
                    this.currentStep++;
                    if (this.onStepChange) this.onStepChange(this.currentStep);
                } else {
                    this.isPlaying = false;
                }
            }
        }

        if (this.centerCore) {
            this.centerCore.rotation.y += 0.008;
            this.centerCore.rotation.x += 0.004;
            const tension = this.chapters[this.currentStep]?.tensionValue || 50;
            const s = 1 + Math.sin(Date.now() * 0.004) * (0.05 + tension / 600);
            this.centerCore.scale.set(s, s, s);
        }
        if (this.nodesGroup) this.nodesGroup.rotation.y += 0.004;
        if (this.particles) this.particles.rotation.y -= 0.0008;

        this.camera.position.x += (Math.sin(this.targetRotationY) * 140 - this.camera.position.x) * 0.05;
        this.camera.position.z += (Math.cos(this.targetRotationY) * 140 - this.camera.position.z) * 0.05;
        this.camera.position.y += (30 + this.targetRotationX * 80 - this.camera.position.y) * 0.05;
        this.camera.lookAt(0, 0, 0);
    }

    onWindowResize() {
        if (!this.camera || !this.renderer || !this.container) return;
        const width = this.container.clientWidth;
        const height = Math.min(this.container.clientHeight || 420, 500);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }

    togglePlay() { this.isPlaying = !this.isPlaying; return this.isPlaying; }
    goToStep(stepIndex) {
        if (stepIndex >= 0 && stepIndex < this.chapters.length) {
            this.currentStep = stepIndex;
            this.progress = 0;
            if (this.onStepChange) this.onStepChange(this.currentStep);
        }
    }
    nextStep() { this.goToStep(this.currentStep < this.chapters.length - 1 ? this.currentStep + 1 : 0); }
    prevStep() { if (this.currentStep > 0) this.goToStep(this.currentStep - 1); }
    destroy() { if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId); }
}
