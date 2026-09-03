// PDF Text Extraction & Advanced MCQ Generator for Tidskrøll

export async function extractTextFromPDF(file) {
    if (!window.pdfjsLib) {
        throw new Error('PDF.js bibliotek er ikke lastet.');
    }
    const arrayBuffer = await file.arrayBuffer();
    const pdf = await window.pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    let fullText = '';

    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const textContent = await page.getTextContent();
        const pageText = textContent.items.map(item => item.str).join(' ');
        fullText += `\n--- Side ${i} ---\n` + pageText;
    }
    return fullText;
}

export function generateQuestionsFromText(text) {
    const questions = [];
    const rawSentences = text.split(/[.!?\n]+/).map(s => s.trim().replace(/\s+/g, ' ')).filter(s => s.length > 30 && s.length < 220);

    const yearRegex = /\b(1[0-9]{3}|20[0-2][0-9]|[7-9][0-9]{2})\b/;
    const usedSentences = new Set();

    // 1. Extract Date / Year based questions
    for (const sent of rawSentences) {
        if (questions.length >= 15) break;
        const match = sent.match(yearRegex);
        if (match && !usedSentences.has(sent)) {
            usedSentences.add(sent);
            const year = parseInt(match[1], 10);
            const fakeYears = [year - 10, year + 5, year - 2, year + 12, year - 4].filter(y => y !== year).map(String);
            const prompt = `Hvilket årstall omtales her: "${sent.replace(match[0], '____')}"?`;
            const options = [String(year), ...fakeYears.slice(0, 3)].sort(() => Math.random() - 0.5);

            questions.push({
                id: questions.length + 1,
                question: prompt,
                options: options,
                answer: options.indexOf(String(year))
            });
        }
    }

    // 2. Extract Concept / Keyword / Name based questions
    const historyKeywords = ['grunnloven', 'stortinget', 'unionen', 'alliansen', 'revolusjonen', 'kongen', 'kirken', 'regjeringen', 'demokratiet', 'parlamentarismen', 'okkupasjonen', 'motstandsbevegelsen', 'samlingen', 'reformasjonen', 'eneveldet'];

    for (const sent of rawSentences) {
        if (questions.length >= 20) break;
        if (usedSentences.has(sent)) continue;

        for (const kw of historyKeywords) {
            const regex = new RegExp(`\\b${kw}\\b`, 'i');
            if (regex.test(sent)) {
                usedSentences.add(sent);
                const matchedWord = sent.match(regex)[0];
                const prompt = `Hvilket begrep mangler i setningen: "${sent.replace(regex, '_______')}"?`;
                const fakeKeywords = historyKeywords.filter(k => k.toLowerCase() !== kw.toLowerCase());
                fakeKeywords.sort(() => Math.random() - 0.5);
                const options = [matchedWord, ...fakeKeywords.slice(0, 3)].sort(() => Math.random() - 0.5);

                questions.push({
                    id: questions.length + 1,
                    question: prompt,
                    options: options,
                    answer: options.indexOf(matchedWord)
                });
                break;
            }
        }
    }

    return questions;
}
