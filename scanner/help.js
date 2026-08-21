document.addEventListener('DOMContentLoaded', async () => {
  const langBtnNo = document.getElementById('lang-no');
  const langBtnEn = document.getElementById('lang-en');
  const btnCloseHelp = document.getElementById('btn-close-help');

  const TRANSLATIONS = {
    no: {
      docTitle: "ScanExtension Hjelpesenter",
      docSubtitle: "Komplett guide, tastatursnarveier og tips for digital skanning",
      btnClose: "Lukk",
      secQuickStart: "⚡ Hurtigstart (3 enkle steg)",
      step1Title: "Åpne boken i nettleseren",
      step1Desc: "Gå til den digitale boken eller nett-dokumentet du vil skanne i Chrome.",
      step2Title: "Velg modus & trykk Start",
      step2Desc: "Åpne ScanExtension fra verktøylinjen, velg ønsket modus (f.eks. «Helt kapittel») og trykk <b>Start Skanning</b>.",
      step3Title: "Ferdig PDF lagres automatisk",
      step3Desc: "Skanneren blar automatisk gjennom boken og laster ned en ferdig, søkbar PDF med usynlig tekstlag direkte til datamaskinen din.",
      secModes: "🎯 De 4 Skannemodusene",
      modeAutoChapBadge: "Helt kapittel",
      modeAutoChapTitle: "Automatisk kapittelskanning",
      modeAutoChapDesc: "Ruller eller blar gjennom hele kapittelet og stopper automatisk når bunnen eller neste hovedkapittel nås. Kutter automatisk bort starten på neste kapittel slik at PDF-en slutter 100% rent.",
      modeContinuousBadge: "Løpende",
      modeContinuousTitle: "Manuell start / stopp",
      modeContinuousDesc: "Skanner sammenhengende i eget tempo til du selv trykker på den store <b>«STOPP & LAST NED PDF»</b>-knappen.",
      modeSubchapBadge: "Undertema",
      modeSubchapTitle: "Stopp ved undertittel",
      modeSubchapDesc: "Skanner gjennom et bestemt avsnitt eller delkapittel og avslutter automatisk når neste store overskrift dukker opp.",
      modeFixedBadge: "Fast antall",
      modeFixedTitle: "Spesifikt sideantall",
      modeFixedDesc: "Lar deg oppgi et nøyaktig antall opptak (f.eks. 15 sider) for kontrollerte utdrag.",
      secShortcuts: "⌨️ Globale Tastatursnarveier",
      thAction: "Handling",
      actionStartStop: "Start / Stopp skanning",
      actionPause: "Pause / Fortsett",
      actionGallery: "Åpne Galleri / Forhåndsvisning",
      actionSnipper: "✂️ Klippeverktøy (Utsnitt & Kopiering)",
      shortcutNote: "Tips: Du kan alltids endre snarveiene ved å åpne chrome://extensions/shortcuts i nettleseren din.",
      secFAQ: "❓ Ofte Stilte Spørsmål (FAQ)",
      faq1Q: "Hvorfor er PDF-en søkbar med Cmd+F / Ctrl+F?",
      faq1A: "ScanExtension legger inn et mikroskopisk, usynlig tekstlag nøyaktig der ordene står på boksiden. Dette gjør at du kan søke i teksten, markere enkeltkolonner og kopiere tekst til Word akkurat som i et vanlig digitalt dokument.",
      faq2Q: "Hvordan velger jeg mindre filstørrelse?",
      faq2A: "I menyen eller Galleriet kan du velge «⚡ Kompakt (150 DPI)». Dette reduserer filstørrelsen med opptil 80-90%, perfekt for deling på e-post, Teams eller Google Classroom.",
      faq3Q: "Hvordan sletter jeg en side før PDF lagres?",
      faq3A: "Huk av for «Forhåndsvis og slett sider før PDF lagres» i popup-menyen, eller trykk på «[👁 Se / Rediger opptak]» underveis. I Galleriet trykker du bare på den røde Slett-knappen på siden du vil fjerne.",
      faq4Q: "Lagres noe data eksternt?",
      faq4A: "Nei, absolutt ingenting. ScanExtension kjører 100% lokalt i din egen nettleser. Ingen bilder, tekster eller filer sendes noen gang til eksterne servere.",
      faq5Q: "Hvordan fungerer Klippeverktøyet (Snipping Tool)?",
      faq5A: "Trykk på «✂️ Klipp» i popup-menyen eller bruk snarveien (Option+Shift+C / Alt+Shift+C). Dra et rektangel over ønsket figur, tabell eller tekst for å kopiere bildet direkte til utklippstavlen (Cmd+V), hente ut kun teksten, eller laste ned som PNG.",
      secSupportTitle: "Trenger du hjelp eller har forslag?",
      secSupportDesc: "Ta gjerne kontakt med oss direkte via e-post:",
      privacyLink: "Personvernerklæring"
    },
    en: {
      docTitle: "ScanExtension Help Center",
      docSubtitle: "Complete guide, keyboard shortcuts, and tips for digital e-book scanning",
      btnClose: "Close",
      secQuickStart: "⚡ Quick Start (3 Easy Steps)",
      step1Title: "Open the book in your browser",
      step1Desc: "Navigate to the digital e-book or online document in Chrome.",
      step2Title: "Select mode & click Start",
      step2Desc: "Open ScanExtension from your toolbar, choose your desired mode (e.g. \"Full Chapter\"), and click <b>Start Scanning</b>.",
      step3Title: "Finished PDF saves automatically",
      step3Desc: "The scanner flips through pages automatically and downloads a complete, searchable PDF with an invisible text layer directly to your computer.",
      secModes: "🎯 The 4 Scanning Modes",
      modeAutoChapBadge: "Full Chapter",
      modeAutoChapTitle: "Automatic chapter scan",
      modeAutoChapDesc: "Scrolls or flips through the entire chapter and automatically stops when the chapter ends. Trims off the next chapter start so the PDF finishes cleanly.",
      modeContinuousBadge: "Continuous",
      modeContinuousTitle: "Manual start / stop",
      modeContinuousDesc: "Scans continuously at your own pace until you click the prominent <b>\"STOP & DOWNLOAD PDF\"</b> button.",
      modeSubchapBadge: "Subchapter",
      modeSubchapTitle: "Stop at heading",
      modeSubchapDesc: "Scans through a specific section and stops automatically when the next major heading is reached.",
      modeFixedBadge: "Fixed Count",
      modeFixedTitle: "Specific page count",
      modeFixedDesc: "Allows you to specify an exact number of captures (e.g. 15 pages) for controlled extracts.",
      secShortcuts: "⌨️ Global Keyboard Shortcuts",
      thAction: "Action",
      actionStartStop: "Start / Stop scanning",
      actionPause: "Pause / Resume",
      actionGallery: "Open Gallery / Preview",
      actionSnipper: "✂️ Snippet Tool (Crop & Copy)",
      shortcutNote: "Tip: You can customize shortcuts anytime by visiting chrome://extensions/shortcuts in your browser.",
      secFAQ: "❓ Frequently Asked Questions (FAQ)",
      faq1Q: "Why is the PDF searchable with Cmd+F / Ctrl+F?",
      faq1A: "ScanExtension injects an invisible text layer positioned precisely over each rendered word on the page. This lets you search, highlight individual columns, and copy text into Word/Docs just like a native digital document.",
      faq2Q: "How do I reduce the PDF file size?",
      faq2A: "In the popup menu or Gallery, select \"⚡ Compact (150 DPI)\". This reduces the file size by up to 80-90%, perfect for email, Teams, or Google Classroom.",
      faq3Q: "How can I delete a page before saving the PDF?",
      faq3A: "Enable \"Preview and delete pages before saving PDF\" in the popup menu, or click \"[👁 View / Edit Captures]\". In the Gallery, click the red Delete button on any page you wish to remove.",
      faq4Q: "Is any data stored externally?",
      faq4A: "No, absolutely none. ScanExtension runs 100% locally in your own browser session. No images, text extracts, or files are ever sent to external servers.",
      faq5Q: "How does the Snippet Tool work?",
      faq5A: "Click \"✂️ Clip\" in the popup menu or use the shortcut (Option+Shift+C / Alt+Shift+C). Drag a selection box over any diagram, table, or text to copy the image straight to your clipboard (Cmd+V), copy only the selected text, or download as PNG.",
      secSupportTitle: "Need help or have suggestions?",
      secSupportDesc: "Feel free to reach out to us directly via email:",
      privacyLink: "Privacy Policy"
    }
  };

  let currentLang = 'no';

  function applyLanguage(lang) {
    currentLang = lang;
    const t = TRANSLATIONS[lang] || TRANSLATIONS.no;

    if (lang === 'en') {
      langBtnEn.classList.add('active');
      langBtnNo.classList.remove('active');
    } else {
      langBtnNo.classList.add('active');
      langBtnEn.classList.remove('active');
    }

    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (t[key]) {
        el.innerHTML = t[key];
      }
    });

    chrome.storage.local.set({ uiLang: lang });
  }

  langBtnNo.addEventListener('click', () => applyLanguage('no'));
  langBtnEn.addEventListener('click', () => applyLanguage('en'));

  const storedData = await chrome.storage.local.get('uiLang');
  if (storedData && storedData.uiLang) {
    applyLanguage(storedData.uiLang);
  } else {
    const navLang = (navigator.language || 'no').toLowerCase();
    if (navLang.startsWith('no') || navLang.startsWith('nb') || navLang.startsWith('nn')) {
      applyLanguage('no');
    } else {
      applyLanguage('en');
    }
  }

  btnCloseHelp.addEventListener('click', () => {
    window.close();
  });
});
