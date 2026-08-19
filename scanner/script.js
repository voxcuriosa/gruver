document.addEventListener('DOMContentLoaded', () => {
    const authScreen = document.getElementById('auth-screen');
    const appScreen = document.getElementById('app-screen');
    const unlockBtn = document.getElementById('unlock-btn');
    const pinInput = document.getElementById('pin-input');

    const checkPersistedPIN = async () => {
        const savedPIN = localStorage.getItem('vox_pin');
        const expiry = localStorage.getItem('vox_pin_expiry');

        if (savedPIN && expiry && Date.now() < parseInt(expiry)) {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pin: savedPIN })
                });

                if (response.ok) {
                    showApp();
                }
            } catch (err) {
                console.error('Auto-auth failed', err);
            }
        }
    };

    checkPersistedPIN();

    const handleUnlock = async () => {
        const pin = pinInput.value.trim();
        if (!pin) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pin: pin })
            });

            if (response.ok) {
                localStorage.setItem('vox_pin', pin);
                localStorage.setItem('vox_pin_expiry', Date.now() + 7 * 24 * 60 * 60 * 1000); // 7 dager
                showApp();
            } else {
                alert('Feil PIN-kode');
                pinInput.value = '';
            }
        } catch (error) {
            alert('Kunne ikke validere PIN. Sjekk internettforbindelsen.');
        }
    };

    function showApp() {
        authScreen.classList.add('hidden');
        appScreen.classList.remove('hidden');
    }

    unlockBtn.addEventListener('click', handleUnlock);
    pinInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleUnlock();
    });
});

function revealEmail() {
    const placeholder = document.getElementById('email-placeholder');
    const user = 'post';
    const domain = 'voxcuriosa.no';
    placeholder.innerHTML = `<a href="mailto:${user}@${domain}" style="color: var(--primary);">${user}@${domain}</a>`;
    placeholder.onclick = null;
}
