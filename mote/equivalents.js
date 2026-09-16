// equivalents.js - Morsomme sammenligninger for MøteSluket
const EQUIVALENT_ITEMS = [
    // Småting / pausemat (under 100 kr)
    { id: 'kanelbolle', name: 'saftige kanelboller på bakeri', cost: 45, icon: '🥮' },
    { id: 'coffee', name: 'kopper flat white / kaffe latte', cost: 62, icon: '☕' },
    { id: 'twist', name: 'poser med Twist til pauserommet', cost: 69, icon: '🍬' },
    { id: 'grandiosa', name: 'Grandiosa frossenpizzaer', cost: 65, icon: '🍕' },
    { id: 'energy_drink', name: 'bokser Red Bull til trøtte deltakere', cost: 35, icon: '⚡' },
    { id: 'flax', name: 'MillionFlax-lodd med håp om gevinst', cost: 50, icon: '🎟️' },
    { id: 'waffle', name: 'nystekte vafler med rømme og syltetøy', cost: 45, icon: '🧇' },
    
    // Mat og lunsj (100 - 600 kr per enhet/porsjon)
    { id: 'sushi_lunch', name: 'porsjoner fersk sushilunsj fra restaurant', cost: 195, icon: '🍣' },
    { id: 'lunch_buffet', name: 'luksus lunsjbuffeter ute i byen', cost: 295, icon: '🍱' },
    { id: 'chatgpt', name: 'måneder med ChatGPT Plus (som kunne holdt møtet)', cost: 230, icon: '🤖' },
    { id: 'spotify', name: 'måneder med Spotify Premium for å drukne møtelyd', cost: 139, icon: '🎵' },
    { id: 'flowers', name: 'blomsterbuketter til noen som fortjener det', cost: 390, icon: '💐' },
    { id: 'wine', name: 'flasker god trøstevin til fredagspilsen', cost: 220, icon: '🍷' },

    // Utstyr og velvære (600 - 3000 kr)
    { id: 'massage', name: 'behandlinger hos fysioterapeut for stiv møtenakke', cost: 950, icon: '💆' },
    { id: 'keyboard', name: 'stillegående kvalitetstastaturer', cost: 890, icon: '⌨️' },
    { id: 'board_dinner', name: 'tre-retters middager på gourmetrestaurant', cost: 1450, icon: '🍽️' },
    { id: 'flight_ticket', name: 'flybilletter tur/retur London for å rømme kontoret', cost: 1850, icon: '✈️' },
    { id: 'airpods', name: 'par støydempende AirPods Pro', cost: 2990, icon: '🎧' },
    { id: 'consultant_hour', name: 'timer med ekstern seniorkonsulent', cost: 2300, icon: '💼' },

    // Kontor og reiser (3000 kr +)
    { id: 'hotel_weekend', name: 'spa-helgeopphold på fjellet', cost: 3200, icon: '🏨' },
    { id: 'ergonomic_chair', name: 'ergonomiske kontorstoler med korsryggstøtte', cost: 4500, icon: '🪑' },
    { id: 'gokart', name: 'hele kvelder med gokart og pizza for hele gjengen', cost: 7500, icon: '🏎️' },
    { id: 'macbook', name: 'splitter nye MacBook Air', cost: 14990, icon: '💻' },
    { id: 'espresso_machine', name: 'profesjonelle espressomaskiner til pauserommet', cost: 18500, icon: '☕' },
    { id: 'seminar_trip', name: 'firmaturer til Hemsedal i høysesongen', cost: 35000, icon: '⛷️' }
];

function getFunEquivalents(totalAmount) {
    if (!totalAmount || totalAmount < 1) {
        totalAmount = 50;
    }

    const candidates = EQUIVALENT_ITEMS.map(item => ({
        ...item,
        count: totalAmount / item.cost
    }));

    const highCounts = candidates.filter(c => c.count >= 2 && c.count <= 500);
    const midCounts = candidates.filter(c => c.count >= 0.7 && c.count < 2);

    const shuffle = (arr) => [...arr].sort(() => 0.5 - Math.random());
    const selected = [];

    for (const item of shuffle(highCounts)) {
        if (selected.length < 3) selected.push(item);
    }
    for (const item of shuffle(midCounts)) {
        if (selected.length < 4) selected.push(item);
    }
    const leftovers = shuffle(candidates.filter(c => !selected.some(s => s.id === c.id)));
    for (const item of leftovers) {
        if (selected.length < 5) selected.push(item);
    }

    return selected.slice(0, 5).map(item => {
        let countFormatted;
        if (item.count >= 10) {
            countFormatted = Math.round(item.count).toLocaleString('no-NO');
        } else if (item.count >= 1.5) {
            countFormatted = (Math.round(item.count * 10) / 10).toLocaleString('no-NO', { maximumFractionDigits: 1 });
        } else {
            countFormatted = (Math.round(item.count * 10) / 10).toLocaleString('no-NO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        }

        return {
            icon: item.icon,
            countText: countFormatted,
            name: item.name,
            fullText: `${countFormatted} ${item.name}`
        };
    });
}

/**
 * Returnerer en dynamisk og presis sammenligning tilpasset den løpende kostnaden
 * @param {number} cost - Nåværende kostnad i kroner
 * @returns {{icon: string, text: string}}
 */
function getLiveEquivalent(cost) {
    if (cost < 35) {
        return { icon: '⏳', text: 'Taksametret ruller – penger forsvinner ut vinduet...' };
    }

    // Finn varer der kostnaden dekker minst 1 enhet
    const valid = EQUIVALENT_ITEMS
        .map(item => ({ ...item, count: cost / item.cost }))
        .filter(item => item.count >= 1);

    if (valid.length === 0) {
        return { icon: '☕', text: `Passert ${Math.round(cost)} kr – kaffebudsjettet ryker snart!` };
    }

    // Finn de som er i et fint og håndgripelig antall (1 til 30 stk)
    const sweetSpot = valid.filter(item => item.count >= 1 && item.count <= 30);
    const pool = sweetSpot.length > 0 ? sweetSpot : valid;

    // Roter basert på tidsintervall (~12 sekunder) så brukerne rekker å lese i ro og mak
    const timeIndex = Math.floor(Date.now() / 12000);
    const item = pool[timeIndex % pool.length];

    let countStr;
    if (item.count >= 10) {
        countStr = Math.floor(item.count).toLocaleString('no-NO');
    } else if (item.count >= 2) {
        countStr = (Math.floor(item.count * 10) / 10).toLocaleString('no-NO', { maximumFractionDigits: 1 });
    } else {
        countStr = '1';
    }

    return {
        icon: item.icon,
        text: `Tilsvarer nå: <strong>${countStr} ${item.name}</strong>`
    };
}
