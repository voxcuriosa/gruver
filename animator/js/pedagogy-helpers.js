// Helper functions for Pedagogical Analysis: Simulations, Source Critique, and Quiz

export function generateSimulationScenarios(themeInfo, text) {
    return [
        {
            id: "var_1",
            title: "Ytringsfrihet & Informasjonsflyt",
            description: "Hva skjer med samfunnsutviklingen dersom innbyggerne fritt kan spre idéer versus streng sensur?",
            min: 0,
            max: 100,
            default: 70,
            unit: "%",
            lowImpact: "Streng sensur kveler opplysningstidens idéer, men skaper grobunn for underjordiske opprør og voldelig revolusjon.",
            highImpact: "Full ytringsfrihet akselererer reformer, men krever kildekritiske borgere og sterke demokratiske institusjoner for å unngå polarisering."
        },
        {
            id: "var_2",
            title: "Skatte- & Ressursfordeling",
            description: "Hvordan påvirker ulikhet og skattebyrde samfunnets stabilitet?",
            min: 0,
            max: 100,
            default: 50,
            unit: "indeks",
            lowImpact: "Ekstrem skjevfordeling tapper statskassen eller skaper dyp fattigdom og opptøyer blant de svakeste.",
            highImpact: "Bred skattefinansiering og rettferdig fordeling sikrer fellesgoder, men forutsetter tillit mellom styresmaktene og folket."
        },
        {
            id: "var_3",
            title: "Diplomati vs. Militær maktbruk",
            description: "Bruk av allianser, forhandlinger og fredsavtaler opp mot krigsmakt.",
            min: 0,
            max: 100,
            default: 65,
            unit: "%",
            lowImpact: "Ensidig militærmakt skaper varig bitterhet, revansjisme og uforutsigbare krigskonflikter.",
            highImpact: "Bredt diplomati og forhandlede kompromisser gir varige traktater og institusjonalisert fred."
        }
    ];
}

export function generateSourceCritique(text, themeInfo) {
    return [
        {
            aspect: "🔍 Opphavsperson & Formål",
            question: "Hvem har forfattet teksten, og hvilket formål har fremstillingen?",
            pedagogyNote: "Vurder om kilden er en samtidig primærkilde (f.eks. Grunnlovsdokumentet eller et brev fra 1814) eller en sekundærkilde (en historisk oppsummering skrevet i ettertid)."
        },
        {
            aspect: "⚖️ Perspektiv & Taushet",
            question: "Hvilke stemmer eller grupper er lite representert i denne kilden?",
            pedagogyNote: "Historiske kilder fokuserte ofte på mannlige ledere og eliter. Hva med kvinner, arbeidere, husmenn og minoriteter?"
        },
        {
            aspect: "📌 Årsakssammenhenger",
            question: "Fremstilles hendelsene som uunngåelige, eller som resultat av bevisste menneskelige valg?",
            pedagogyNote: "Mennesker former historien gjennom handlinger, men innenfor gitte rammer av geografi, økonomi og teknologi."
        }
    ];
}

export function generatePedagogicalQuiz(timeline, themeInfo, text) {
    const questions = [];

    if (timeline.length >= 2) {
        questions.push({
            question: `Hva skjedde i eller rundt tidsrommet for ${timeline[0].timeLabel}?`,
            options: [
                timeline[0].title,
                "Det ble innført fullstendig ro uten politiske endringer.",
                "En isolert hendelse som ikke fikk videre konsekvenser.",
                "Avtalen ble forkastet uten at noen møttes."
            ],
            correct: 0,
            explanation: `I ${timeline[0].timeLabel} ser vi: ${timeline[0].description}`
        });
    }

    questions.push({
        question: "Hva er den viktigste pedagogiske lærdommen om årsak og virkning i dette temaet?",
        options: [
            "Store historiske og samfunnsmessige endringer skyldes et samspill mellom bakenforliggende årsaker og utløsende hendelser.",
            "Alt skjer tilfeldig uten sammenheng med tidligere handlinger.",
            "Kun én enkeltperson bestemmer alene hele samfunnsutviklingen uten motstand.",
            "Teknologi og økonomi har aldri noen innvirkning på politikk."
        ],
        correct: 0,
        explanation: "Pedagogisk historielæring viser at bakenforliggende strukturer (økonomi, maktforhold, idéer) skaper sprengkraften som utløses av konkrete hendelser."
    });

    questions.push({
        question: "Hvorfor er kildekritikk avgjørende når vi analyserer samfunnsfaglige tekster?",
        options: [
            "For å avdekke hvilke interesser, formål og perspektiver som ligger bak fremstillingen.",
            "Fordi alle kilder på internett alltid er 100% nøytrale og fullstendige.",
            "Fordi kildekritikk kun trengs i naturfag og aldri i historie.",
            "For å pugge årstall uten å forstå sammenhenger."
        ],
        correct: 0,
        explanation: "Kildekritikk lar oss vurdere troverdighet, tendens og manglende perspektiver."
    });

    return questions;
}
