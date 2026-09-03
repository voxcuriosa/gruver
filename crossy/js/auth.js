// PIN Authentication for Tidsreisen
const AUTH_KEY = 'vox_crossy_pin';
const AUTH_EXPIRY = 'vox_crossy_expiry';

export async function checkAuth() {
    const savedPin = localStorage.getItem(AUTH_KEY);
    const expiry = localStorage.getItem(AUTH_EXPIRY);

    if (savedPin && expiry && Date.now() < parseInt(expiry, 10)) {
        return true;
    }
    return false;
}

export async function verifyPin(pin) {
    try {
        const response = await fetch('api.php?action=verify_pin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pin: pin.trim() })
        });
        const data = await response.json();
        if (response.ok && data.status === 'success') {
            // Save valid PIN for 30 days
            localStorage.setItem(AUTH_KEY, pin.trim());
            localStorage.setItem(AUTH_EXPIRY, (Date.now() + 30 * 24 * 3600 * 1000).toString());
            return true;
        }
        return false;
    } catch (err) {
        console.error('PIN verification failed', err);
        // Fallback for offline if PIN matches default 6666
        if (pin.trim() === '6666') {
            localStorage.setItem(AUTH_KEY, '6666');
            localStorage.setItem(AUTH_EXPIRY, (Date.now() + 30 * 24 * 3600 * 1000).toString());
            return true;
        }
        return false;
    }
}
