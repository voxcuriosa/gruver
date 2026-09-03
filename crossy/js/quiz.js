// Quiz and Time-Freeze Mechanic for Tidsreisen

export class QuizManager {
    constructor() {
        this.topics = [];
        this.currentTopic = null;
        this.currentQuestions = [];
        this.questionIndex = 0;
        this.isFrozen = false;
        this.timer = null;
        this.timeLeft = 10;
        this.onAnswerCallback = null;
    }

    async loadTopics() {
        try {
            const res = await fetch('api.php?action=get_topics');
            const data = await res.json();
            this.topics = data.topics || [];
            return this.topics;
        } catch (e) {
            console.warn('Failed to load topics, using fallback', e);
            return [];
        }
    }

    setTopic(topicId) {
        if (topicId === 'all') {
            this.currentQuestions = this.topics.flatMap(t => t.questions);
        } else {
            const topic = this.topics.find(t => t.id === topicId);
            this.currentQuestions = topic ? [...topic.questions] : [];
        }
        // Shuffle questions
        this.currentQuestions.sort(() => Math.random() - 0.5);
        this.questionIndex = 0;
    }

    getNextQuestion() {
        if (this.currentQuestions.length === 0) return null;
        const q = this.currentQuestions[this.questionIndex % this.currentQuestions.length];
        this.questionIndex++;
        return q;
    }

    triggerFreeze(onAnswer) {
        this.isFrozen = true;
        this.onAnswerCallback = onAnswer;
        const q = this.getNextQuestion();
        if (!q) {
            this.isFrozen = false;
            if (onAnswer) onAnswer(true, 100);
            return;
        }

        const modal = document.getElementById('quiz-modal');
        const questionText = document.getElementById('quiz-question');
        const optionsContainer = document.getElementById('quiz-options');
        const timerBar = document.getElementById('quiz-timer-bar');

        modal.classList.add('active');
        questionText.textContent = q.question;
        optionsContainer.innerHTML = '';

        this.timeLeft = 12; // 12 seconds per question
        timerBar.style.width = '100%';

        q.options.forEach((optText, idx) => {
            const btn = document.createElement('button');
            btn.className = 'quiz-opt-btn';
            btn.textContent = `${['A', 'B', 'C', 'D'][idx]}: ${optText}`;
            btn.onclick = () => this.handleSelection(idx, q.answer, btn, q.options);
            optionsContainer.appendChild(btn);
        });

        // Countdown timer
        clearInterval(this.timer);
        const startTime = Date.now();
        const duration = this.timeLeft * 1000;

        this.timer = setInterval(() => {
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(0, duration - elapsed);
            const pct = (remaining / duration) * 100;
            timerBar.style.width = pct + '%';

            if (remaining <= 0) {
                clearInterval(this.timer);
                this.handleTimeout();
            }
        }, 50);
    }

    handleSelection(selectedIdx, correctIdx, btnElement, options) {
        clearInterval(this.timer);
        const isCorrect = selectedIdx === correctIdx;
        const allBtns = document.querySelectorAll('.quiz-opt-btn');
        allBtns.forEach(b => b.disabled = true);

        if (isCorrect) {
            btnElement.classList.add('correct');
            this.playAudio(true);
        } else {
            btnElement.classList.add('wrong');
            if (allBtns[correctIdx]) allBtns[correctIdx].classList.add('correct');
            this.playAudio(false);
        }

        setTimeout(() => {
            document.getElementById('quiz-modal').classList.remove('active');
            this.isFrozen = false;
            if (this.onAnswerCallback) {
                this.onAnswerCallback(isCorrect, isCorrect ? 300 : 0);
            }
        }, 1200);
    }

    handleTimeout() {
        const allBtns = document.querySelectorAll('.quiz-opt-btn');
        allBtns.forEach(b => b.disabled = true);
        this.playAudio(false);

        setTimeout(() => {
            document.getElementById('quiz-modal').classList.remove('active');
            this.isFrozen = false;
            if (this.onAnswerCallback) {
                this.onAnswerCallback(false, 0);
            }
        }, 800);
    }

    playAudio(isSuccess) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            if (isSuccess) {
                osc.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
                osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1); // E5
                osc.frequency.setValueAtTime(783.99, ctx.currentTime + 0.2); // G5
            } else {
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                osc.frequency.setValueAtTime(150, ctx.currentTime + 0.15);
            }
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch (e) {}
    }
}
