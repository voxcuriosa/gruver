// Highscore management for Tidsreisen

export async function fetchHighscores() {
    try {
        const res = await fetch('api.php?action=get_highscores');
        const data = await res.json();
        return data.scores || [];
    } catch (e) {
        console.warn('Could not load highscores', e);
        return [];
    }
}

export async function submitHighscore(name, score, character, topic) {
    try {
        const res = await fetch('api.php?action=save_highscore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, score, character, topic })
        });
        return await res.json();
    } catch (e) {
        console.error('Failed to save score', e);
        return null;
    }
}

export function renderHighscoresList(containerElement, scores) {
    if (!scores || scores.length === 0) {
        containerElement.innerHTML = '<p style="text-align:center; color:#94a3b8;">Ingen poengsummer registrert ennå. Bli den første!</p>';
        return;
    }

    const charEmojis = {
        viking: '⚔️',
        napoleon: '🎩',
        cleopatra: '👑',
        chicken: '🐔',
        einstein: '🧠'
    };

    let html = `
        <table class="hs-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Spiller</th>
                    <th>Tema</th>
                    <th>Poeng</th>
                </tr>
            </thead>
            <tbody>
    `;

    scores.slice(0, 15).forEach((s, idx) => {
        let rankBadge = `${idx + 1}`;
        if (idx === 0) rankBadge = '🥇';
        else if (idx === 1) rankBadge = '🥈';
        else if (idx === 2) rankBadge = '🥉';

        const emoji = charEmojis[s.character] || '👤';
        html += `
            <tr class="${idx < 3 ? 'top-rank' : ''}">
                <td class="rank-col">${rankBadge}</td>
                <td><span class="char-icon">${emoji}</span> <strong>${escapeHtml(s.name)}</strong></td>
                <td class="topic-col">${escapeHtml(s.topic || 'Historie')}</td>
                <td class="score-col">${s.score}</td>
            </tr>
        `;
    });

    html += `</tbody></table>`;
    containerElement.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
