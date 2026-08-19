// PDF Parser module using PDF.js CDN

export async function extractTextFromPDF(file, onProgress) {
    if (!window.pdfjsLib) {
        // Dynamically load PDF.js if not already present
        await loadPdfJsLibrary();
    }

    try {
        const arrayBuffer = await file.arrayBuffer();
        const loadingTask = window.pdfjsLib.getDocument({ data: arrayBuffer });
        
        loadingTask.onProgress = function (progress) {
            if (onProgress && progress.total > 0) {
                const percent = Math.round((progress.loaded / progress.total) * 50);
                onProgress(percent, "Laster PDF-dokument...");
            }
        };

        const pdf = await loadingTask.promise;
        const numPages = pdf.numPages;
        let fullText = "";

        for (let i = 1; i <= numPages; i++) {
            const page = await pdf.getPage(i);
            const textContent = await page.getTextContent();
            const pageText = textContent.items
                .map(item => item.str)
                .join(" ");
            
            fullText += `\n\n--- Side ${i} ---\n` + pageText;

            if (onProgress) {
                const percent = 50 + Math.round((i / numPages) * 50);
                onProgress(percent, `Ekstraherer side ${i} av ${numPages}...`);
            }
        }

        return cleanExtractedText(fullText);
    } catch (err) {
        console.error("Feil ved lesing av PDF:", err);
        throw new Error("Kunne ikke lese PDF-filen. Vennligst sjekk at filen er gyldig og ikke passordbeskyttet.");
    }
}

function cleanExtractedText(text) {
    return text
        .replace(/\r\n/g, "\n")
        .replace(/[ \t]+/g, " ")
        .replace(/\n\s*\n\s*\n+/g, "\n\n")
        .trim();
}

function loadPdfJsLibrary() {
    return new Promise((resolve, reject) => {
        if (window.pdfjsLib) {
            resolve();
            return;
        }

        const script = document.createElement("script");
        script.src = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js";
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
            resolve();
        };
        script.onerror = () => reject(new Error("Kunne ikke laste PDF-bibliotek."));
        document.head.appendChild(script);
    });
}
