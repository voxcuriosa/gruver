// Enhanced High-Detail Character Rendering for Tidskrøll

export const CHARACTERS = [
    { id: 'chicken', name: 'Tids-Kylling', title: 'Astronaut-kylling', emoji: '🐔', color: '#f8fafc', speedBonus: 'Standard' },
    { id: 'viking', name: 'Vikingen Torvald', title: 'Fryktløs kriger', emoji: '⚔️', color: '#3b82f6', speedBonus: '+10% Fart' },
    { id: 'napoleon', name: 'Napoleon', title: 'Keiser & strateg', emoji: '🎩', color: '#1e3a8a', speedBonus: '+15% Quizbonus' },
    { id: 'cleopatra', name: 'Kleopatra', title: 'Nildronning', emoji: '👑', color: '#eab308', speedBonus: 'Ekstra skjold' },
    { id: 'einstein', name: 'Einstein / Sokrates', title: 'Tidsreisende geni', emoji: '🧠', color: '#a855f7', speedBonus: '2x Tidsbonus' }
];

export function drawCharacter(ctx, charId, x, y, size, hop = 0) {
    ctx.save();
    const cx = x + size / 2;
    const cy = y + size / 2 - hop;
    ctx.translate(cx, cy);

    // Dynamic 3D Drop Shadow on ground
    const shadowScale = Math.max(0.2, 1 - hop / 30);
    ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
    ctx.beginPath();
    ctx.ellipse(0, size * 0.4 + hop * 0.4, size * 0.38 * shadowScale, size * 0.16 * shadowScale, 0, 0, Math.PI * 2);
    ctx.fill();

    const s = size;
    const hs = size / 2;

    switch (charId) {
        case 'viking':
            // Body (Chainmail & Tunic with 3D gradient)
            ctx.fillStyle = '#1e3a8a';
            ctx.fillRect(-hs * 0.55, -hs * 0.15, s * 0.55, s * 0.6);
            ctx.fillStyle = '#172554';
            ctx.fillRect(0, -hs * 0.15, hs * 0.55, s * 0.6); // 3D side shadow
            // Belt with Gold Buckle
            ctx.fillStyle = '#78350f';
            ctx.fillRect(-hs * 0.55, hs * 0.15, s * 0.55, 4);
            ctx.fillStyle = '#fbbf24';
            ctx.fillRect(-3, hs * 0.15 - 1, 6, 6);
            // Face & Nordic Beard
            ctx.fillStyle = '#fed7aa';
            ctx.fillRect(-hs * 0.45, -hs * 0.65, s * 0.45, s * 0.45);
            ctx.fillStyle = '#ea580c'; // Braided Red Beard
            ctx.beginPath();
            ctx.moveTo(-hs * 0.5, -hs * 0.25);
            ctx.lineTo(hs * 0.5, -hs * 0.25);
            ctx.lineTo(0, hs * 0.35);
            ctx.fill();
            // Iron Helmet with Brow Guard
            ctx.fillStyle = '#64748b';
            ctx.fillRect(-hs * 0.55, -hs * 0.85, s * 0.55, s * 0.25);
            ctx.fillStyle = '#475569';
            ctx.fillRect(-hs * 0.1, -hs * 0.7, s * 0.1, s * 0.2); // Nose guard
            // Golden Horns
            ctx.fillStyle = '#fef08a';
            ctx.beginPath();
            ctx.moveTo(-hs * 0.5, -hs * 0.8);
            ctx.quadraticCurveTo(-hs * 0.9, -hs * 1.1, -hs * 0.8, -hs * 1.25);
            ctx.lineTo(-hs * 0.4, -hs * 0.85);
            ctx.moveTo(hs * 0.5, -hs * 0.8);
            ctx.quadraticCurveTo(hs * 0.9, -hs * 1.1, hs * 0.8, -hs * 1.25);
            ctx.lineTo(hs * 0.4, -hs * 0.85);
            ctx.fill();
            // Round Shield with Boss
            ctx.fillStyle = '#92400e';
            ctx.beginPath();
            ctx.arc(-hs * 0.6, 0, s * 0.22, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = 2; ctx.stroke();
            ctx.fillStyle = '#eab308';
            ctx.beginPath(); ctx.arc(-hs * 0.6, 0, 4, 0, Math.PI * 2); ctx.fill();
            break;

        case 'napoleon':
            // Dark Blue Military Coat with Golden Epaulets
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(-hs * 0.55, -hs * 0.2, s * 0.55, s * 0.65);
            ctx.fillStyle = '#f8fafc'; // White lapels
            ctx.fillRect(-hs * 0.2, -hs * 0.1, s * 0.2, s * 0.45);
            // Golden Buttons
            ctx.fillStyle = '#eab308';
            ctx.fillRect(-hs * 0.58, -hs * 0.22, 6, 4); // Left epaulet
            ctx.fillRect(hs * 0.43, -hs * 0.22, 6, 4);  // Right epaulet
            ctx.beginPath(); ctx.arc(0, 0, 2, 0, Math.PI * 2); ctx.fill();
            // Face & Sideburns
            ctx.fillStyle = '#fed7aa';
            ctx.fillRect(-hs * 0.45, -hs * 0.65, s * 0.45, s * 0.45);
            // Eyes
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(-hs * 0.25, -hs * 0.5, 3, 3);
            ctx.fillRect(hs * 0.1, -hs * 0.5, 3, 3);
            // Grand Bicorn Hat with French Cockade
            ctx.fillStyle = '#09090b';
            ctx.beginPath();
            ctx.ellipse(0, -hs * 0.78, s * 0.62, s * 0.24, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#eab308'; ctx.lineWidth = 1.5; ctx.stroke();
            // Tricolor Cockade
            ctx.fillStyle = '#ef4444';
            ctx.beginPath(); ctx.arc(-hs * 0.2, -hs * 0.9, 4, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#3b82f6';
            ctx.beginPath(); ctx.arc(-hs * 0.2, -hs * 0.9, 2, 0, Math.PI * 2); ctx.fill();
            break;

        case 'cleopatra':
            // Royal Teal & Gold Gown
            ctx.fillStyle = '#0f766e';
            ctx.fillRect(-hs * 0.55, -hs * 0.2, s * 0.55, s * 0.65);
            // Gold Collar (Usekh)
            ctx.fillStyle = '#fbbf24';
            ctx.fillRect(-hs * 0.4, -hs * 0.2, s * 0.4, 5);
            // Face & Egyptian Eyeliner
            ctx.fillStyle = '#fcd34d';
            ctx.fillRect(-hs * 0.45, -hs * 0.65, s * 0.45, s * 0.45);
            // Lustrous Black Hair Bob
            ctx.fillStyle = '#18181b';
            ctx.fillRect(-hs * 0.58, -hs * 0.72, s * 0.58, s * 0.26);
            ctx.fillRect(-hs * 0.62, -hs * 0.55, s * 0.18, s * 0.45);
            ctx.fillRect(hs * 0.44, -hs * 0.55, s * 0.18, s * 0.45);
            // Golden Headdress with Royal Snake
            ctx.fillStyle = '#f59e0b';
            ctx.fillRect(-hs * 0.45, -hs * 0.88, s * 0.45, 6);
            ctx.fillStyle = '#ef4444'; // Cobra jewel
            ctx.fillRect(-2, -hs * 1.0, 4, 5);
            // Cyan/Blue Sunglasses
            ctx.fillStyle = '#0284c7';
            ctx.fillRect(-hs * 0.38, -hs * 0.54, s * 0.38, 5);
            ctx.strokeStyle = '#38bdf8'; ctx.lineWidth = 1; ctx.strokeRect(-hs * 0.38, -hs * 0.54, s * 0.38, 5);
            break;

        case 'einstein':
            // Tweed Suit / Toga
            ctx.fillStyle = '#334155';
            ctx.fillRect(-hs * 0.55, -hs * 0.2, s * 0.55, s * 0.65);
            // Face & Mustache
            ctx.fillStyle = '#ffedd5';
            ctx.fillRect(-hs * 0.45, -hs * 0.65, s * 0.45, s * 0.45);
            ctx.fillStyle = '#e2e8f0'; // White Mustache
            ctx.fillRect(-hs * 0.35, -hs * 0.38, s * 0.35, 5);
            // Wild Albert Einstein Cloud Hair
            ctx.fillStyle = '#f8fafc';
            ctx.beginPath();
            ctx.arc(-hs * 0.5, -hs * 0.75, s * 0.24, 0, Math.PI * 2);
            ctx.arc(hs * 0.5, -hs * 0.75, s * 0.24, 0, Math.PI * 2);
            ctx.arc(0, -hs * 0.92, s * 0.28, 0, Math.PI * 2);
            ctx.arc(-hs * 0.25, -hs * 0.85, s * 0.22, 0, Math.PI * 2);
            ctx.arc(hs * 0.25, -hs * 0.85, s * 0.22, 0, Math.PI * 2);
            ctx.fill();
            // Glasses / Eyes
            ctx.strokeStyle = '#64748b'; ctx.lineWidth = 1.5;
            ctx.strokeRect(-hs * 0.35, -hs * 0.54, 7, 7);
            ctx.strokeRect(hs * 0.1, -hs * 0.54, 7, 7);
            break;

        case 'chicken':
        default:
            // 3D Voxel White Chicken Body with 3D Shading
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(-hs * 0.5, -hs * 0.4, s * 0.5, s * 0.6);
            ctx.fillStyle = '#e2e8f0'; // Right side shadow
            ctx.fillRect(0, -hs * 0.4, hs * 0.5, s * 0.6);
            // Yellow Beak
            ctx.fillStyle = '#f59e0b';
            ctx.fillRect(-hs * 0.12, -hs * 0.25, s * 0.24, s * 0.18);
            // Red Comb & Wattle
            ctx.fillStyle = '#dc2626';
            ctx.fillRect(-hs * 0.12, -hs * 0.68, s * 0.24, s * 0.24);
            ctx.fillRect(-hs * 0.08, -hs * 0.04, s * 0.16, s * 0.16);
            // Sci-Fi Astronaut Helmet Dome with Neon Visor Glow
            ctx.strokeStyle = '#00f2ff';
            ctx.lineWidth = 2.5;
            ctx.fillStyle = 'rgba(0, 242, 255, 0.4)';
            ctx.beginPath();
            ctx.arc(0, -hs * 0.25, s * 0.42, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            // Helmet Glint
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(-hs * 0.2, -hs * 0.45, 3, 0, Math.PI * 2);
            ctx.fill();
            break;
    }

    ctx.restore();
}
