// Main Game Engine for Tidskrøll (Retina High-DPI & High-Clarity Graphics)
import { drawCharacter } from './characters.js';
import { OBSTACLE_TYPES, drawObstacle, drawMine } from './obstacles.js';

export class Game {
    constructor(canvas, quizManager, onGameOver) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.quiz = quizManager;
        this.onGameOver = onGameOver;
        this.tileSize = 40;
        this.cols = 13;
        this.rowsVisible = 16;
        this.dpr = window.devicePixelRatio || 1;
        this.logicalWidth = this.cols * this.tileSize;
        this.logicalHeight = this.rowsVisible * this.tileSize;
        this.canvas.width = this.logicalWidth * this.dpr;
        this.canvas.height = this.logicalHeight * this.dpr;
        this.canvas.style.width = this.logicalWidth + 'px';
        this.canvas.style.height = this.logicalHeight + 'px';
        this.player = { gridX: 6, gridY: 0, x: 0, y: 0, hop: 0, lives: 3, character: 'chicken', shield: 0 };
        this.cameraY = 0;
        this.lanes = [];
        this.maxForwardRow = 0;
        this.score = 0;
        this.running = false;
        this.lastTime = 0;
        this.freezeInterval = 15;
        this.nextFreezeRow = 15;
        this.consecutiveRoads = 0;
        this.consecutiveRivers = 0;
        this.setupControls();
    }

    init(characterId) {
        this.player.character = characterId;
        this.player.gridX = Math.floor(this.cols / 2);
        this.player.gridY = 0;
        this.player.x = this.player.gridX * this.tileSize;
        this.player.y = 0;
        this.player.hop = 0;
        this.player.lives = 3;
        this.player.shield = 0;
        this.cameraY = this.player.y - this.logicalHeight * 0.65;
        this.maxForwardRow = 0;
        this.score = 0;
        this.nextFreezeRow = this.freezeInterval;
        this.consecutiveRoads = 0;
        this.consecutiveRivers = 0;
        this.lanes = [];
        for (let i = -5; i <= 25; i++) this.generateLane(i);
        this.running = true;
        this.lastTime = performance.now();
        requestAnimationFrame((t) => this.loop(t));
    }

    generateLane(rowIndex) {
        if (rowIndex <= 2) {
            this.lanes.push({ row: rowIndex, type: 'GRASS', obstacles: [], mines: [] });
            return;
        }
        if (rowIndex % this.freezeInterval === 0) {
            this.consecutiveRoads = 0; this.consecutiveRivers = 0;
            this.lanes.push({ row: rowIndex, type: 'PORTAL', obstacles: [], mines: [] });
            return;
        }

        let type = 'GRASS';
        if (this.consecutiveRoads >= 3 || this.consecutiveRivers >= 2) {
            type = 'GRASS';
            this.consecutiveRoads = 0; this.consecutiveRivers = 0;
        } else {
            const rand = Math.random();
            if (rand < 0.55 && this.consecutiveRoads < 3) {
                type = 'ROAD'; this.consecutiveRoads++; this.consecutiveRivers = 0;
            } else if (rand < 0.72 && this.consecutiveRivers < 2) {
                type = 'RIVER'; this.consecutiveRivers++; this.consecutiveRoads = 0;
            } else {
                type = 'GRASS'; this.consecutiveRoads = 0; this.consecutiveRivers = 0;
            }
        }

        let obsList = [], mines = [];
        if (type === 'ROAD') {
            const vehicles = ['TANK', 'CHARIOT', 'KANGAROO', 'CAR', 'TREX'];
            const obsType = vehicles[Math.floor(Math.random() * vehicles.length)];
            const def = OBSTACLE_TYPES[obsType];
            const dir = Math.random() > 0.5 ? 1 : -1;
            const spacing = 280 + Math.random() * 120;
            for (let x = -200; x < this.logicalWidth + 250; x += spacing) {
                obsList.push({ x: x + Math.random() * 20, y: 0, width: def.width, height: def.height, type: obsType, speed: def.speed * (0.85 + Math.random() * 0.4), dir });
            }
        } else if (type === 'RIVER') {
            const waterObs = ['LOG', 'SHIELD', 'ICE'];
            const obsType = waterObs[Math.floor(Math.random() * waterObs.length)];
            const def = OBSTACLE_TYPES[obsType];
            const dir = Math.random() > 0.5 ? 1 : -1;
            const spacing = 180 + Math.random() * 80;
            for (let x = -200; x < this.logicalWidth + 250; x += spacing) {
                obsList.push({ x, y: 0, width: def.width, height: def.height, type: obsType, speed: def.speed * 0.95, dir });
            }
        } else if (type === 'GRASS' && Math.random() < 0.25) {
            mines.push({ id: Math.random(), x: (Math.floor(Math.random() * (this.cols - 2)) + 1) * this.tileSize + this.tileSize / 2, y: this.tileSize / 2 });
        }
        this.lanes.push({ row: rowIndex, type, obstacles: obsList, mines });
    }

    move(dx, dy) {
        if (!this.running || this.quiz.isFrozen) return;
        const newX = this.player.gridX + dx, newY = this.player.gridY + dy;
        if (newX < 0 || newX >= this.cols || newY < this.player.gridY - 3) return;

        this.player.gridX = newX;
        this.player.gridY = newY;
        this.player.hop = 14;

        if (this.player.gridY > this.maxForwardRow) {
            this.maxForwardRow = this.player.gridY;
            this.score += 10;
            this.updateHUD();

            while (this.lanes[this.lanes.length - 1].row < this.maxForwardRow + 25) {
                this.generateLane(this.lanes[this.lanes.length - 1].row + 1);
            }

            if (this.maxForwardRow >= this.nextFreezeRow) {
                this.nextFreezeRow += this.freezeInterval;
                this.quiz.triggerFreeze((correct, bonus) => {
                    if (correct) {
                        this.score += bonus;
                        this.player.shield = 180;
                        this.showFloatingText('❄️ TIDS-BOOST! +300', '#00f2ff');
                    } else {
                        this.handleHit('Feil svar på tidsportalen!');
                    }
                    this.updateHUD();
                });
            }
        }
    }

    handleHit(reason) {
        if (this.player.shield > 0 || !this.running || this.quiz.isFrozen) return;

        this.player.lives--;
        this.updateHUD();

        if (this.player.lives > 0) {
            this.player.shield = 180;
            this.showFloatingText(`💔 -1 LIV! Skjold aktivt (3s)`, '#ef4444');
        } else {
            this.showFloatingText('⚠️ SISTE SJANSE! Svar for å overleve!', '#facc15');
            this.quiz.triggerFreeze((correct, bonus) => {
                if (correct) {
                    this.player.lives = 1;
                    this.score += bonus;
                    this.player.shield = 180;
                    this.showFloatingText('✨ OVERLEVDE! +1 LIV ❤️', '#22c55e');
                    this.updateHUD();
                } else {
                    this.running = false;
                    if (this.onGameOver) this.onGameOver(this.score, 'Svarte feil på pensumspørsmålet!');
                }
            });
        }
    }

    loop(time) {
        if (!this.running) return;
        const dt = Math.min(32, time - this.lastTime);
        this.lastTime = time;
        if (!this.quiz.isFrozen) this.update(dt, time);
        this.render(time);
        requestAnimationFrame((t) => this.loop(t));
    }

    update(dt, time) {
        this.player.x += (this.player.gridX * this.tileSize - this.player.x) * 0.35;
        this.player.y += (-this.player.gridY * this.tileSize - this.player.y) * 0.35;
        if (this.player.hop > 0) this.player.hop = Math.max(0, this.player.hop - 1.2);
        if (this.player.shield > 0) this.player.shield--;

        this.cameraY += (this.player.y - this.logicalHeight * 0.65 - this.cameraY) * 0.15;
        const currentLane = this.lanes.find(l => l.row === this.player.gridY);
        let onFloatingObj = false;

        this.lanes.forEach(lane => {
            const laneY = -lane.row * this.tileSize;
            if (Math.abs(laneY - this.player.y) > this.logicalHeight * 1.5) return;

            lane.obstacles.forEach(obs => {
                obs.x += obs.speed * obs.dir;
                if (obs.dir === 1 && obs.x > this.logicalWidth + 150) obs.x = -150;
                if (obs.dir === -1 && obs.x < -150) obs.x = this.logicalWidth + 150;

                if (lane.row === this.player.gridY) {
                    const pBox = { x: this.player.x + 8, y: this.player.y + 8, w: this.tileSize - 16, h: this.tileSize - 16 };
                    const oBox = { x: obs.x, y: laneY + 4, w: obs.width, h: obs.height };
                    if (this.checkCollision(pBox, oBox)) {
                        if (lane.type === 'RIVER') {
                            onFloatingObj = true;
                            this.player.x += obs.speed * obs.dir;
                            this.player.gridX = Math.round(this.player.x / this.tileSize);
                        } else if (lane.type === 'ROAD' && this.player.shield <= 0) {
                            this.handleHit(`Truffet av ${obs.type}`);
                        }
                    }
                }
            });

            if (lane.row === this.player.gridY && lane.mines && this.player.shield <= 0) {
                lane.mines.forEach(m => {
                    if (Math.hypot(this.player.x + this.tileSize / 2 - m.x, this.player.y + this.tileSize / 2 - (laneY + m.y)) < 18) {
                        this.handleHit('Tråkket på landmine');
                    }
                });
            }
        });

        if (currentLane && currentLane.type === 'RIVER' && !onFloatingObj && this.player.shield <= 0) this.handleHit('Falt i elven');
        if (this.player.x < -10 || this.player.x > this.logicalWidth) this.handleHit('Drev ut av kartet');
    }

    checkCollision(a, b) {
        return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
    }

    render(time) {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.save();
        this.ctx.scale(this.dpr, this.dpr);
        this.ctx.translate(0, -this.cameraY);

        this.lanes.forEach(lane => {
            const laneY = -lane.row * this.tileSize;

            if (lane.type === 'GRASS') {
                this.ctx.fillStyle = lane.row % 2 === 0 ? '#166534' : '#15803d';
                this.ctx.fillRect(0, laneY, this.logicalWidth, this.tileSize);
                this.ctx.fillStyle = '#fef08a'; this.ctx.fillRect((lane.row * 73) % this.logicalWidth, laneY + 12, 3, 3);
            } else if (lane.type === 'ROAD') {
                this.ctx.fillStyle = '#1e293b';
                this.ctx.fillRect(0, laneY, this.logicalWidth, this.tileSize);
                this.ctx.fillStyle = '#facc15';
                for (let rx = 10; rx < this.logicalWidth; rx += 50) this.ctx.fillRect(rx, laneY + this.tileSize / 2 - 2, 20, 3);
            } else if (lane.type === 'RIVER') {
                this.ctx.fillStyle = '#0284c7';
                this.ctx.fillRect(0, laneY, this.logicalWidth, this.tileSize);
                this.ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
                const waveOffset = Math.sin(time * 0.003 + lane.row) * 15;
                for (let wx = -20; wx < this.logicalWidth + 40; wx += 60) this.ctx.fillRect(wx + waveOffset, laneY + 14, 24, 2);
            } else if (lane.type === 'PORTAL') {
                const pulse = Math.sin(time * 0.008) * 0.25 + 0.75;
                this.ctx.fillStyle = '#991b1b';
                this.ctx.fillRect(0, laneY, this.logicalWidth, this.tileSize);
                this.ctx.fillStyle = `rgba(239, 68, 68, ${0.5 * pulse})`;
                this.ctx.fillRect(0, laneY, this.logicalWidth, this.tileSize);
                this.ctx.strokeStyle = '#f87171'; this.ctx.lineWidth = 2.5;
                this.ctx.strokeRect(0, laneY, this.logicalWidth, this.tileSize);
                this.ctx.fillStyle = '#ffffff';
                this.ctx.font = 'bold 12px monospace';
                this.ctx.fillText('🔴 TIDS-PORTAL / SJEKKPUNKT 🔴', 20, laneY + 25);
            }

            if (lane.mines) lane.mines.forEach(m => drawMine(this.ctx, { x: m.x, y: laneY + m.y, id: m.id }, time));
            lane.obstacles.forEach(obs => drawObstacle(this.ctx, { ...obs, y: laneY + 4 }, time));
        });

        if (this.player.shield > 0) {
            const flash = Math.floor(time / 80) % 2 === 0;
            this.ctx.strokeStyle = flash ? '#ef4444' : '#00f2ff';
            this.ctx.lineWidth = 3;
            this.ctx.beginPath();
            this.ctx.arc(this.player.x + this.tileSize / 2, this.player.y + this.tileSize / 2 - this.player.hop, this.tileSize * 0.68, 0, Math.PI * 2);
            this.ctx.stroke();
        }

        drawCharacter(this.ctx, this.player.character, this.player.x, this.player.y, this.tileSize, this.player.hop);
        this.ctx.restore();
    }

    showFloatingText(text, color) {
        const toast = document.getElementById('game-toast');
        if (toast) {
            toast.textContent = text;
            toast.style.color = color;
            toast.classList.add('active');
            setTimeout(() => toast.classList.remove('active'), 1500);
        }
    }

    updateHUD() {
        const s = document.getElementById('hud-score'), l = document.getElementById('hud-lives');
        if (s) s.textContent = this.score;
        if (l) l.textContent = '❤️'.repeat(Math.max(0, this.player.lives));
    }

    setupControls() {
        window.addEventListener('keydown', (e) => {
            if (['ArrowUp', 'KeyW'].includes(e.code)) { e.preventDefault(); this.move(0, 1); }
            if (['ArrowDown', 'KeyS'].includes(e.code)) { e.preventDefault(); this.move(0, -1); }
            if (['ArrowLeft', 'KeyA'].includes(e.code)) { e.preventDefault(); this.move(-1, 0); }
            if (['ArrowRight', 'KeyD'].includes(e.code)) { e.preventDefault(); this.move(1, 0); }
        });
    }
}
