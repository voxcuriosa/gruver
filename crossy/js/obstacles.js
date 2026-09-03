// High-Clarity Obstacle Rendering for Tidskrøll

export const OBSTACLE_TYPES = {
    TANK: { width: 88, height: 38, speed: 2.0, name: 'Panservogn', emoji: '🪖' },
    CHARIOT: { width: 76, height: 34, speed: 2.8, name: 'Stridsvogn', emoji: '🐎' },
    KANGAROO: { width: 50, height: 36, speed: 3.0, name: 'Kenguru', emoji: '🦘', hop: true },
    CAR: { width: 68, height: 32, speed: 2.6, name: 'Veteranbil', emoji: '🚗' },
    TREX: { width: 94, height: 42, speed: 2.4, name: 'T-Rex', emoji: '🦖' },
    LOG: { width: 92, height: 34, speed: 1.6, isWater: true },
    SHIELD: { width: 52, height: 34, speed: 1.4, isWater: true },
    ICE: { width: 70, height: 34, speed: 1.2, isWater: true }
};

export function drawObstacle(ctx, obs, time) {
    const { x, y, width, height, type, dir } = obs;
    ctx.save();
    ctx.translate(x + width / 2, y + height / 2);
    if (dir === -1) ctx.scale(-1, 1);

    const hw = width / 2;
    const hh = height / 2;

    // Drop shadow
    ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
    ctx.beginPath();
    ctx.ellipse(0, hh * 0.9, hw * 0.9, 6, 0, 0, Math.PI * 2);
    ctx.fill();

    switch (type) {
        case 'TANK':
            // Dark outline
            ctx.fillStyle = '#14532d'; // Dark Green Base
            ctx.fillRect(-hw, -hh + 3, width, height - 6);
            // Black Tread belts
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(-hw, -hh, width, 8);
            ctx.fillRect(-hw, hh - 8, width, 8);
            // Tread Wheels
            ctx.fillStyle = '#94a3b8';
            for (let wx = -hw + 8; wx <= hw - 8; wx += 14) {
                ctx.beginPath(); ctx.arc(wx, -hh + 4, 3, 0, Math.PI * 2); ctx.fill();
                ctx.beginPath(); ctx.arc(wx, hh - 4, 3, 0, Math.PI * 2); ctx.fill();
            }
            // Camo Turret
            ctx.fillStyle = '#15803d';
            ctx.beginPath(); ctx.roundRect(-hw * 0.35, -hh * 0.6, width * 0.45, height * 0.6, 6); ctx.fill();
            ctx.strokeStyle = '#052e16'; ctx.lineWidth = 2; ctx.stroke();
            // Long Gun Barrel
            ctx.fillStyle = '#1e293b';
            ctx.fillRect(hw * 0.1, -4, width * 0.55, 8);
            ctx.fillRect(hw * 0.6, -6, 6, 12); // Muzzle Brake
            // Army Star Badge
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px sans-serif';
            ctx.fillText('★', -hw * 0.1, 4);
            break;

        case 'CHARIOT':
            // Wooden Cart with Spoked Wheels
            ctx.fillStyle = '#78350f';
            ctx.fillRect(-hw, -hh * 0.85, width * 0.45, height * 0.85);
            ctx.strokeStyle = '#f59e0b'; ctx.lineWidth = 2;
            ctx.strokeRect(-hw, -hh * 0.85, width * 0.45, height * 0.85);
            // Big Bronze Wheel
            ctx.fillStyle = '#fbbf24';
            ctx.beginPath(); ctx.arc(-hw * 0.55, hh * 0.3, 10, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = '#451a03'; ctx.lineWidth = 2; ctx.stroke();
            // Galloping Stallion (Horse)
            ctx.fillStyle = '#b45309';
            ctx.beginPath(); ctx.roundRect(0, -hh * 0.75, width * 0.48, height * 0.75, 6); ctx.fill();
            // Horse Head & Ears
            ctx.fillStyle = '#92400e';
            ctx.fillRect(hw * 0.3, -hh * 1.1, width * 0.2, height * 0.5);
            // Golden Mane & Reins
            ctx.fillStyle = '#fbbf24';
            ctx.fillRect(0, -hh * 0.85, width * 0.3, 4);
            ctx.strokeStyle = '#fef08a'; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(-hw * 0.5, -hh * 0.2); ctx.lineTo(hw * 0.3, -hh * 0.4); ctx.stroke();
            break;

        case 'KANGAROO':
            const hopY = Math.abs(Math.sin(time * 0.012 + x * 0.08)) * 12;
            // Kangaroo Body
            ctx.fillStyle = '#d97706';
            ctx.beginPath(); ctx.roundRect(-hw * 0.45, -hh * 0.85 - hopY, width * 0.5, height * 0.85, 8); ctx.fill();
            // Head & Snout
            ctx.fillRect(hw * 0.05, -hh * 0.95 - hopY, 14, 14);
            // Camo Army Helmet
            ctx.fillStyle = '#166534';
            ctx.fillRect(-hw * 0.15, -hh * 1.25 - hopY, width * 0.4, 8);
            // Red Boxing Gloves
            ctx.fillStyle = '#ef4444';
            ctx.beginPath(); ctx.arc(hw * 0.2, -hh * 0.3 - hopY, 5, 0, Math.PI * 2); ctx.fill();
            // Muscular Tail
            ctx.strokeStyle = '#d97706'; ctx.lineWidth = 5;
            ctx.beginPath();
            ctx.moveTo(-hw * 0.4, -hh * 0.2 - hopY);
            ctx.quadraticCurveTo(-hw * 0.9, -hh * 0.1 - hopY * 0.5, -hw * 0.8, hh * 0.35);
            ctx.stroke();
            break;

        case 'TREX':
            // Big Green Scaly Dinosaur Body
            ctx.fillStyle = '#15803d';
            ctx.beginPath(); ctx.roundRect(-hw * 0.8, -hh * 0.85, width * 0.75, height * 0.85, 10); ctx.fill();
            ctx.strokeStyle = '#052e16'; ctx.lineWidth = 2; ctx.stroke();
            // Heavy Snapping Head
            ctx.fillStyle = '#16a34a';
            ctx.fillRect(hw * 0.05, -hh * 1.15, width * 0.44, height * 0.7);
            // Glowing Yellow Eye with Slit
            ctx.fillStyle = '#facc15';
            ctx.beginPath(); ctx.arc(hw * 0.22, -hh * 0.95, 4, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#000'; ctx.fillRect(hw * 0.22, -hh * 0.95 - 2, 2, 4);
            // Sharp White Teeth
            ctx.fillStyle = '#ffffff';
            for (let tx = hw * 0.1; tx <= hw * 0.45; tx += 6) {
                ctx.fillRect(tx, -hh * 0.55, 3, 5);
            }
            break;

        case 'LOG':
            // Floating Bark Log with Rings
            ctx.fillStyle = '#78350f';
            ctx.beginPath(); ctx.roundRect(-hw, -hh * 0.75, width, height * 0.75, 8); ctx.fill();
            ctx.strokeStyle = '#451a03'; ctx.lineWidth = 2; ctx.stroke();
            // Tree rings
            ctx.fillStyle = '#d97706';
            ctx.beginPath(); ctx.ellipse(-hw + 8, -hh * 0.38, 6, 9, 0, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = '#78350f'; ctx.lineWidth = 1.5; ctx.stroke();
            break;

        case 'SHIELD':
            // Viking War Shield (Round with heraldic blue/yellow colors)
            ctx.fillStyle = '#1d4ed8';
            ctx.beginPath(); ctx.arc(0, 0, height * 0.48, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = '#f8fafc'; ctx.lineWidth = 3; ctx.stroke();
            // Center Gold Boss
            ctx.fillStyle = '#fbbf24';
            ctx.beginPath(); ctx.arc(0, 0, height * 0.2, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = '#92400e'; ctx.lineWidth = 2; ctx.stroke();
            break;

        case 'ICE':
            // Crystalline Ice Floe
            ctx.fillStyle = '#e0f2fe';
            ctx.beginPath(); ctx.roundRect(-hw, -hh * 0.8, width, height * 0.8, 8); ctx.fill();
            ctx.strokeStyle = '#38bdf8'; ctx.lineWidth = 2; ctx.stroke();
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(-hw * 0.5, -hh * 0.5, width * 0.35, 4);
            break;

        case 'CAR':
        default:
            // Red Vintage Roadster
            ctx.fillStyle = '#dc2626';
            ctx.beginPath(); ctx.roundRect(-hw, -hh * 0.8, width, height * 0.8, 8); ctx.fill();
            ctx.strokeStyle = '#7f1d1d'; ctx.lineWidth = 2; ctx.stroke();
            // Windshield & White Roof
            ctx.fillStyle = '#38bdf8';
            ctx.fillRect(hw * 0.05, -hh * 0.65, width * 0.25, height * 0.5);
            // Big Glowing Yellow Headlights
            ctx.fillStyle = '#fef08a';
            ctx.beginPath(); ctx.arc(hw - 2, -hh * 0.5, 4, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath(); ctx.arc(hw - 2, hh * 0.5 - 4, 4, 0, Math.PI * 2); ctx.fill();
            // Wheels
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(-hw * 0.75, hh * 0.1, 14, 6);
            ctx.fillRect(hw * 0.3, hh * 0.1, 14, 6);
            break;
    }

    ctx.restore();
}

export function drawMine(ctx, mine, time) {
    ctx.save();
    const blink = Math.sin(time * 0.01 + mine.id) > 0;
    // Metal Base
    ctx.fillStyle = '#18181b';
    ctx.beginPath(); ctx.arc(mine.x, mine.y, 11, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#eab308'; ctx.lineWidth = 2; ctx.stroke();
    // Glowing Center Warning Light
    ctx.fillStyle = blink ? '#ef4444' : '#7f1d1d';
    ctx.beginPath(); ctx.arc(mine.x, mine.y, 5, 0, Math.PI * 2); ctx.fill();
    if (blink) {
        ctx.strokeStyle = 'rgba(239, 68, 68, 0.6)';
        ctx.lineWidth = 3;
        ctx.beginPath(); ctx.arc(mine.x, mine.y, 9, 0, Math.PI * 2); ctx.stroke();
    }
    ctx.restore();
}
