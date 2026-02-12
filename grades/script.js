document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const authScreen = document.getElementById('auth-screen');
    const appScreen = document.getElementById('app-screen');
    const pinInput = document.getElementById('pin-input');
    const unlockBtn = document.getElementById('unlock-btn');

    const studentPointsInput = document.getElementById('student-points');
    const maxPointsInput = document.getElementById('max-points');
    const resultContainer = document.getElementById('result-container');
    const gradeDisplay = document.getElementById('grade-display');
    const pointsDisplay = document.getElementById('points-display');

    // Standard Grade Mapping (based on rounded 15-point scale)
    const standardGradeMap = {
        '15': '6', '14.5': '6-', '14': '6/5', '13.5': '5/6', '13': '5+', '12.5': '5', '12': '5-', '11.5': '5/4', '11': '4/5', '10.5': '4+', '10': '4', '9.5': '4-', '9': '4/3', '8.5': '3/4', '8': '3+', '7.5': '3', '7': '3-', '6.5': '3/2', '6': '2/3', '5.5': '2+', '5': '2', '4.5': '2-', '4': '2/1', '3.5': '1/2'
    };

    // "Snill" Grade Mapping
    const snillGradeMap = {
        '13': '6', '12.5': '6-', '12': '6/5', '11.5': '5/6', '11': '5+', '10.5': '5', '10': '5-', '9.5': '5/4', '9': '4/5', '8.5': '4+', '8': '4', '7.5': '4-', '7': '4/3', '6.5': '3/4', '6': '3+', '5.5': '3', '5': '3-', '4.5': '3/2', '4': '2/3', '3.5': '2+', '3': '2', '2.5': '2-', '2': '2/1', '1.5': '1/2', '1': '1', '0.5': '1', '0': '1'
    };

    const isSnillVersion = window.location.pathname.includes('snill.html');
    const currentMap = isSnillVersion ? snillGradeMap : standardGradeMap;

    // Auth Logic
    unlockBtn.addEventListener('click', async () => {
        const pin = pinInput.value;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pin: pin })
            });

            if (response.ok) {
                authScreen.classList.add('hidden');
                appScreen.classList.remove('hidden');
            } else {
                alert('Feil PIN-kode');
                pinInput.value = '';
            }
        } catch (error) {
            alert('Kunne ikke validere PIN. Sjekk tilkoblingen.');
            console.error(error);
        }
    });

    pinInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') unlockBtn.click();
    });

    // Calculation Logic
    function calculateGrade() {
        const studentPoints = parseFloat(studentPointsInput.value);
        const maxPoints = parseFloat(maxPointsInput.value);

        if (isNaN(studentPoints) || isNaN(maxPoints) || maxPoints === 0) {
            resultContainer.classList.add('hidden');
            return;
        }

        // D7 = (D5/D6)*15
        const d7 = (studentPoints / maxPoints) * 15;

        // D8 = MROUND(D7, 0.5)
        const d8 = Math.round(d7 * 2) / 2;

        let grade = '1';

        if (isSnillVersion) {
            const lookupValue = Math.min(13, d8);
            grade = currentMap[String(lookupValue)] || '1';
        } else {
            if (d8 >= 3.5) {
                grade = currentMap[String(d8)] || '1';
            }
        }

        gradeDisplay.textContent = grade;
        pointsDisplay.textContent = `Skalert poengsum: ${d8.toFixed(1)} / 15.0`;
        resultContainer.classList.remove('hidden');
    }

    studentPointsInput.addEventListener('input', calculateGrade);
    maxPointsInput.addEventListener('input', calculateGrade);

    // Toggle Comparison Image
    const toggleImgBtn = document.getElementById('toggle-img-btn');
    const imgContainer = document.getElementById('comparison-img-container');

    if (toggleImgBtn && imgContainer) {
        toggleImgBtn.addEventListener('click', () => {
            const isHidden = imgContainer.classList.contains('hidden');
            if (isHidden) {
                imgContainer.classList.remove('hidden');
                toggleImgBtn.textContent = 'Skjul sammenligning ↑';
            } else {
                imgContainer.classList.add('hidden');
                toggleImgBtn.textContent = 'Se sammenligning av skalaene ↓';
            }
        });
    }
});

function revealEmail() {
    const user = "borchgrevink";
    const domain = "gmail.com";
    const el = document.getElementById('email-placeholder');
    el.textContent = user + "@" + domain;
    el.classList.remove('email-reveal');
    el.style.textDecoration = "none";
    el.style.cursor = "text";
    el.onclick = null;
}

