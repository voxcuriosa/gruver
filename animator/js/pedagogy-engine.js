// Intelligent pedagogisk analysemotor for kildetekst og PDF
import {
    generateSimulationScenarios,
    generateSourceCritique,
    generatePedagogicalQuiz
} from "./pedagogy-helpers.js";

export function analyzePedagogicalText(rawText) {
    if (!rawText || rawText.trim().length < 30) {
        throw new Error("Teksten er for kort til å generere en pedagogisk animasjon. Vennligst legg inn minst et par avsnitt.");
    }

    const cleanText = rawText.trim();
    const sentences = splitIntoSentences(cleanText);
    const paragraphs = cleanText.split(/\n\s*\n/).map(p => p.trim()).filter(p => p.length > 0);

    const themeInfo = detectTheme(cleanText, sentences);
    const timelineEvents = extractTimelineEvents(cleanText, sentences, paragraphs);
    const networkGraph = buildConceptNetwork(cleanText, timelineEvents, themeInfo);
    const storyboardChapters = buildStoryboard(cleanText, paragraphs, timelineEvents, themeInfo);

    const simulations = generateSimulationScenarios(themeInfo, cleanText);
    const quiz = generatePedagogicalQuiz(timelineEvents, themeInfo, cleanText);
    const sourceCritique = generateSourceCritique(cleanText, themeInfo);

    return {
        title: themeInfo.title,
        subtitle: themeInfo.subtitle,
        category: themeInfo.category,
        wordCount: cleanText.split(/\s+/).length,
        readingTimeMin: Math.max(1, Math.round(cleanText.split(/\s+/).length / 180)),
        timeline: timelineEvents,
        network: networkGraph,
        storyboard: storyboardChapters,
        simulations,
        sourceCritique,
        quiz
    };
}

function splitIntoSentences(text) {
    return text
        .replace(/([.?!])\s*(?=[A-ZÆØÅ0-9])/g, "$1|")
        .split("|")
        .map(s => s.trim())
        .filter(s => s.length > 5);
}

function detectTheme(text, sentences) {
    const lower = text.toLowerCase();
    
    if (lower.includes("1814") || lower.includes("eidsvoll") || lower.includes("kielfreden") || lower.includes("grunnlov")) {
        return {
            title: "1814 & Den norske Grunnloven",
            subtitle: "Fra Kielfreden til Eidsvoll og selvstendighet",
            category: "Norgeshistorie & Demokrati",
            icon: "🇳🇴",
            color: "#00f2ff"
        };
    }
    if (lower.includes("bastill") || lower.includes("fransk") || lower.includes("ludvig 16") || lower.includes("robespierre")) {
        return {
            title: "Den franske revolusjon",
            subtitle: "Fra enevelde og stendersamfunn til menneskerettigheter",
            category: "Verdenshistorie",
            icon: "🇫🇷",
            color: "#ff007b"
        };
    }
    if (lower.includes("industri") || lower.includes("dampmaskin") || lower.includes("fabrikk") || lower.includes("spinning jenny")) {
        return {
            title: "Den industrielle revolusjon",
            subtitle: "Mekaniseringsbølgen, urbanisering og klasseskille",
            category: "Økonomi & Samfunn",
            icon: "⚙️",
            color: "#f59e0b"
        };
    }
    if (lower.includes("kald") || lower.includes("sovjet") || lower.includes("cuba") || lower.includes("kennedy") || lower.includes("nato")) {
        return {
            title: "Den kalde krigen & Cubakrisen",
            subtitle: "Supermaktsrivalisering og atomavskrekking",
            category: "Geopolitikk",
            icon: "🧊",
            color: "#7000ff"
        };
    }
    if (lower.includes("maktfordeling") || lower.includes("storting") || lower.includes("domstol") || lower.includes("regjering")) {
        return {
            title: "Demokrati & Maktfordeling",
            subtitle: "Institusjoner, rettsstat og folkesuverenitet",
            category: "Samfunnskunnskap",
            icon: "⚖️",
            color: "#10b981"
        };
    }

    const firstSentence = sentences[0] || "Historisk og samfunnsfaglig forløp";
    const cleanFirst = firstSentence.replace(/^[#*\s-]+/, "").slice(0, 60);
    return {
        title: cleanFirst + (cleanFirst.length >= 60 ? "..." : ""),
        subtitle: "Pedagogisk analyse og interaktiv visualisering",
        category: "Kildestudie & Samfunnsanalyse",
        icon: "💡",
        color: "#00f2ff"
    };
}

function extractTimelineEvents(text, sentences, paragraphs) {
    const yearRegex = /\b(1[0-9]{3}|20[0-9]{2})\b/g;
    const events = [];
    const seenTitles = new Set();

    paragraphs.forEach((para) => {
        const matches = [...para.matchAll(yearRegex)];
        if (matches.length > 0) {
            matches.forEach(m => {
                const year = m[1];
                const paraSentences = para.split(/[.?!]\s+/);
                const relevantSentence = paraSentences.find(s => s.includes(year)) || paraSentences[0];
                const shortSummary = relevantSentence.trim();
                
                if (shortSummary && !seenTitles.has(year + shortSummary.slice(0, 30))) {
                    seenTitles.add(year + shortSummary.slice(0, 30));
                    events.push({
                        timeLabel: year,
                        timestamp: parseInt(year, 10),
                        title: extractTitleFromSentence(shortSummary),
                        description: shortSummary,
                        importance: events.length === 0 ? "høy" : "middels"
                    });
                }
            });
        }
    });

    if (events.length < 3) {
        const phases = ["Fase 1: Bakgrunn & Årsaker", "Fase 2: Utløsende hendelse", "Fase 3: Hovedforløp", "Fase 4: Konsekvenser"];
        paragraphs.slice(0, 4).forEach((p, idx) => {
            events.push({
                timeLabel: `Trinn ${idx + 1}`,
                timestamp: idx + 1,
                title: phases[idx] || `Trinn ${idx + 1}`,
                description: p.slice(0, 180) + (p.length > 180 ? "..." : ""),
                importance: idx === 1 || idx === 2 ? "høy" : "middels"
            });
        });
    }

    events.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));
    return events.slice(0, 8);
}

function extractTitleFromSentence(sentence) {
    const words = sentence.split(" ");
    if (words.length <= 6) return sentence;
    return words.slice(0, 6).join(" ") + "...";
}

function buildConceptNetwork(text, timeline, themeInfo) {
    const stopWords = new Set(["dette", "eller", "og", "i", "på", "for", "med", "som", "at", "en", "et", "den", "det", "til", "av", "om", "var", "ble", "har", "hadde", "ikke", "seg", "fra", "ved", "under", "etter", "kunne", "ville", "skulle", "over"]);
    const words = text.split(/\s+/);
    const candidateMap = new Map();

    for (let i = 1; i < words.length; i++) {
        const w = words[i].replace(/[^a-zA-ZæøåÆØÅ0-9]/g, "");
        if (w.length > 3 && /^[A-ZÆØÅ]/.test(w) && !stopWords.has(w.toLowerCase())) {
            candidateMap.set(w, (candidateMap.get(w) || 0) + 1);
        }
    }

    const sortedConcepts = [...candidateMap.entries()]
        .sort((a, b) => b[1] - a[1])
        .slice(0, 8)
        .map(entry => entry[0]);

    const nodes = [
        { id: "core", label: themeInfo.title, group: "center", radius: 32, color: themeInfo.color }
    ];

    sortedConcepts.forEach((c, idx) => {
        nodes.push({
            id: `concept_${idx}`,
            label: c,
            group: idx % 2 === 0 ? "actor" : "concept",
            radius: 20 + Math.min(10, (candidateMap.get(c) || 1) * 3),
            color: idx % 2 === 0 ? "#7000ff" : "#10b981"
        });
    });

    const links = [];
    for (let i = 1; i < nodes.length; i++) {
        links.push({ source: "core", target: nodes[i].id, value: 3 });
        if (i > 1 && i % 2 === 0) {
            links.push({ source: nodes[i].id, target: nodes[i - 1].id, value: 1 });
        }
    }

    return { nodes, links };
}

function buildStoryboard(text, paragraphs, timeline, themeInfo) {
    const chapters = [];
    const count = Math.min(paragraphs.length, 5);
    const stageNames = [
        "1. Bakgrunn & Forutsetninger",
        "2. Den utløsende gnisten",
        "3. Konfliktens kjerne & Brytning",
        "4. Det avgjørende vendepunktet",
        "5. Ringvirkninger & Arv til i dag"
    ];

    const tensionCurve = [25, 60, 95, 80, 45];
    const freedomCurve = [30, 40, 65, 85, 90];
    const powerCurve = [85, 75, 45, 60, 50];

    for (let i = 0; i < count; i++) {
        const p = paragraphs[i];
        chapters.push({
            step: i + 1,
            stageTitle: stageNames[i] || `Kapittel ${i + 1}`,
            narrative: p,
            keyTakeaway: p.split(/[.?!]/)[0] + ".",
            tensionValue: tensionCurve[i] || 50,
            freedomValue: freedomCurve[i] || 50,
            powerValue: powerCurve[i] || 50,
            pedagogicalPrompt: generatePrompt(i)
        });
    }

    return chapters;
}

function generatePrompt(index) {
    const prompts = [
        "Hvilke strukturelle årsaker og maktforhold la grunnlaget for denne situasjonen?",
        "Hva var den direkte utløsende faktoren som gjorde at hendelsene ikke kunne stoppes?",
        "Hvilke aktører sto mot hverandre, og hva var deres motstridende interesser?",
        "Hvordan ble kompromisset eller seieren formet, og hvem måtte gi etter?",
        "Hva kan vi lære av dette i dag, og hvordan preger det samfunnet vårt nå?"
    ];
    return prompts[index] || "Reflekter over årsak og virkning i dette avsnittet.";
}
