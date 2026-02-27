<?php
session_start();
?>
<!DOCTYPE html>
<html lang="no">

<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YYPRYXPN70"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());

        gtag('config', 'G-YYPRYXPN70');
    </script>

    <script>
        // Inject Admin Status from PHP Session
        window.isAdminMode = <?php echo (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ? 'true' : 'false'; ?>;
    </script>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Skiensmarka - Gruver & Severdigheter</title>
    <meta name="description"
        content="Interaktivt kart over Skiensmarka med gruver, kulturminner, mineralressurser, reguleringsplaner, radon, løsmasser, berggrunn, kvikkleire og eiendomsinformasjon. Mer brukervennlig enn offisielle kartløsninger fra NGU, Kartverket og Riksantikvaren.">
    <meta name="keywords"
        content="Skiensmarka kart, gruver Telemark, kulturminner, mineralressurser, reguleringsplan, radon kart, løsmasser, berggrunn, kvikkleire, eiendomskart, NGU, Kartverket, Riksantikvaren, brukervennlig kart">

    <!-- PWA Settings -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0b0f19">
    <link rel="apple-touch-icon" href="assets/app-icon-512.png?v=2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gruvekart">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-toolbar@0.4.0-alpha.2/dist/leaflet.toolbar.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-distortableimage@0.21.7/dist/leaflet.distortableimage.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="canonical" href="https://www.voxcuriosa.no/gruver/">

    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: rgba(17, 24, 39, 0.85);
            --accent-color: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.2);
            --text-main: #e2e8f0;
            --text-bright: #ffffff;
            --text-dim: #94a3b8;
            --glass-border: rgba(255, 255, 255, 0.1);
            --card-bg: rgba(30, 41, 59, 0.5);
            --card-hover: rgba(51, 65, 85, 0.8);
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        #app-container {
            display: flex;
            height: 100vh;
            width: 100vw;
            position: relative;
            /* CRITICAL for absolute children outside #map */
        }

        #map {
            flex-grow: 1;
            height: 100%;
        }

        #sidebar {
            width: 342px;
            /* Narrowed by 10% */
            background: var(--panel-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-right: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            z-index: 2000;
            box-shadow: 20px 0 50px rgba(0, 0, 0, 0.6);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--glass-border) transparent;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebar.collapsed {
            margin-left: -342px;
            /* Collapse logic for flexbox on desktop */
            /* transform: translateX(-100%);  <-- Old logic */
        }

        #mobile-toggle {
            /* display: none; <-- Removed to show on desktop too */
            display: flex;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 2100;
            background: var(--panel-bg);
            border: 1px solid var(--glass-border);
            color: var(--accent-color);
            padding: 0;
            width: 52px;
            height: 52px;
            border-radius: 12px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            font-size: 1.2rem;
            flex-direction: column;
            gap: 0;
            line-height: 1;
            align-items: center;
            justify-content: flex-end;
            padding-bottom: 7px;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        #mobile-toggle::after {
            content: "Meny";
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 1px;
        }

        #mobile-toggle:hover {
            background: var(--card-hover);
            transform: scale(1.05) translateZ(0);
            border-color: var(--accent-color);
        }

        #mobile-toggle {
            transform: translateZ(0);
            backface-visibility: hidden;
        }



        .desktop-btn {
            box-sizing: border-box;
            background: var(--panel-bg);
            border: 1px solid var(--glass-border);
            color: var(--accent-color);
            padding: 0;
            padding-bottom: 7px;
            width: 52px;
            height: 52px;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            gap: 0;
            align-items: center;
            justify-content: flex-end;
            transition: all 0.2s;
            line-height: 1;
        }

        .desktop-btn span {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent-color);
            white-space: nowrap;
            width: 100%;
            text-align: center;
            margin-top: 1px;
        }

        .desktop-btn svg {
            margin-bottom: auto;
            margin-top: 13px;
        }

        .desktop-btn:hover {
            background: var(--card-hover);
            transform: scale(1.05);
            border-color: var(--accent-color);
        }

        .desktop-btn.active {
            background: var(--accent-color);
            color: #0b0f19;
        }

        .desktop-btn.active span {
            color: #0b0f19;
        }

        /* Hide default Leaflet Control */
        .leaflet-control-layers-toggle {
            display: none !important;
        }

        /* Flyfoto Menu - Standardized to match Leaflet Layout */
        #flyfoto-menu {
            position: fixed;
            top: 82px;
            /* Rett under knappen (20px start + 52px høyde + 10px luft) */
            right: 20px;
            width: auto;
            min-width: 180px;
            background: var(--panel-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            display: none;
            z-index: 9000;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
        }

        /* Desktop Map Menu Position - ONLY STYLED WHEN EXPANDED */
        .leaflet-top.leaflet-right .leaflet-control-layers.leaflet-control-layers-expanded {
            margin-top: 82px !important;
            right: 20px !important;
            min-width: 240px;
            max-height: 88vh !important;
            border: 1px solid var(--glass-border) !important;
            background: var(--panel-bg) !important;
            backdrop-filter: blur(20px) saturate(180%) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
        }

        /* Support for layering on mobile */
        .leaflet-right {
            z-index: 8000 !important;
        }

        /* Ensure no border when collapsed to fix the "thin black line" */
        .leaflet-top.leaflet-right .leaflet-control-layers:not(.leaflet-control-layers-expanded) {
            border: none !important;
            background: none !important;
            box-shadow: none !important;
            min-width: 0 !important;
        }

        #flyfoto-list {
            padding: 10px 0;
            /* Mer luft i topp/bunn som i kartmenyen */
        }

        /* --- 3. FJERN DE TO LINJENE I MENYEN --- */
        /* Dette skjuler dem selv om de finnes i JavaScript-objektet */
        label[for*="Historiske Flyfoto"],
        .leaflet-control-layers-base label:nth-last-child(-n+2) {
            display: none !important;
        }

        /* Fjern X-knappen i Flyfoto-menyen */
        #flyfoto-menu .menu-header,
        #flyfoto-menu .close-btn {
            display: none !important;
        }

        .flyfoto-item {
            padding: 2px 12px;
            /* Litt mer horisontal luft */
            font-size: 13px;
            /* Standard Leaflet font size */
            display: flex;
            align-items: center;
            gap: 8px;
            /* Litt mer gap */
            transition: background 0.1s;
            color: var(--text-main);
            min-height: 24px;
            /* Uniform height */
        }

        .flyfoto-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--accent-color);
        }

        .flyfoto-item.active {
            background: transparent;
            border-left: none;
            font-weight: normal;
            color: var(--text-main);
        }

        .flyfoto-item input[type="radio"] {
            margin: 0;
            cursor: pointer;
            width: 14px;
            height: 14px;
        }

        @media (max-width: 1024px) {
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 85%;
                max-width: 320px;
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.5);
            }

            #mobile-toggle {
                display: flex;
                position: fixed !important;
                top: 82px !important;
                left: 15px !important;
                z-index: 9999 !important;
            }

            #desktop-controls {
                top: 82px !important;
                right: 15px !important;
                z-index: 2200 !important;
                display: flex !important;
            }

            /* Plasser begge menyene likt rett under knappene */
            .leaflet-top.leaflet-right .leaflet-control-layers {
                margin-top: 144px !important;
                /* Under knappene på mobil */
                right: 15px !important;
            }

            #flyfoto-menu {
                top: 144px !important;
                right: 15px !important;
            }

            /* Skjul den tomme beholderen som Leaflet lager for å fjerne "skygger" */
            .leaflet-top.leaflet-right {
                pointer-events: none !important;
                /* Lar deg trykke "gjennom" beholderen til knappene under */
            }

            .leaflet-control {
                pointer-events: auto !important;
                /* Re-aktiver trykking for selve knappene */
            }

            /* Sikre at Flyfoto-knappen er helt ren og ligger øverst */
            #desktop-flyfoto-btn {
                position: relative !important;
                z-index: 1001 !important;
            }





            /* FJERN X-KNAPP PÅ MOBIL - ENDRET: X skal vises nå */
            /* .sidebar-close-btn, */
            .leaflet-control-layers .close-btn,
            .leaflet-control-layers .menu-header {
                display: none !important;
            }
        }

        .sidebar-header {
            padding: 80px 24px 32px 24px;
            /* Increased top padding to clear X button */
            background: radial-gradient(circle at top left, rgba(56, 189, 248, 0.1), transparent);
            border-bottom: 1px solid var(--glass-border);
            position: relative;
        }

        .sidebar-close-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: var(--text-dim);
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.2s;
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .sidebar-close-btn:hover {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
            transform: scale(1.05);
        }

        .sidebar-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--text-main);
            font-weight: 800;
            letter-spacing: -0.025em;
            background: linear-gradient(to bottom right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-container {
            padding: 16px 24px;
        }

        .leaflet-map-pane {
            z-index: auto !important;
            /* Standard fix to allow inner panes to layer over map siblings */
        }

        .leaflet-popup-pane {
            z-index: 40000 !important;
            /* Ensure popups are on top of everything */
        }

        .leaflet-popup {
            z-index: 40000 !important;
            max-width: none !important;
        }

        /* Fix scrollbar overlap/close button */
        .leaflet-container a.leaflet-popup-close-button {
            top: 20px !important;
            right: 45px !important;
            /* Move SIGNIFICANTLY left to avoid scrollbar */
            color: white !important;
            background: #ef4444;
            padding: 4px 12px !important;
            border-radius: 6px;
            text-decoration: none;
            width: auto !important;
            height: auto !important;
            font-size: 0.9rem !important;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .leaflet-container a.leaflet-popup-close-button:hover {
            background: #dc2626;
            color: white !important;
        }

        /* Use pseudo-element for text label */
        .leaflet-container a.leaflet-popup-close-button::after {
            content: "Lukk";
            margin-left: 0;
        }

        /* Hide the default 'x' content if possible, or just style around it */
        .leaflet-container a.leaflet-popup-close-button {
            text-indent: -9999px;
            /* Hide the 'x' */
            position: absolute;
            width: 60px !important;
        }

        .leaflet-container a.leaflet-popup-close-button::after {
            text-indent: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .property-popup .leaflet-popup-content-wrapper,
        .point-popup .leaflet-popup-content-wrapper {
            background: #111827 !important;
            border: 1px solid var(--accent-color);
            color: #e2e8f0 !important;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .property-popup .leaflet-popup-content,
        .point-popup .leaflet-popup-content {
            margin: 15px !important;
            margin-top: 60px !important;
            /* Increased top margin for close button space */
            margin-right: 15px !important;
            line-height: 1.5;
            width: calc(100% - 30px) !important;
            /* Force fill width */
            box-sizing: border-box !important;
            max-height: 80vh !important;
            /* Limit height for scrollability */
            overflow-y: auto !important;
            /* Enable scrollbar */
        }

        /* Custom scrollbar for popup content */
        .leaflet-popup-content::-webkit-scrollbar {
            width: 8px;
        }

        .leaflet-popup-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        .leaflet-popup-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .leaflet-popup-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .point-popup .leaflet-popup-content {
            margin-top: 60px !important;
        }

        /* Classes for viewer.js integration */
        .popup-container {
            font-family: 'Outfit', sans-serif;
            min-width: 220px;
        }

        .popup-category {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .popup-category-icon {
            font-size: 1.1em;
        }

        .popup-title {
            margin: 4px 0 0 0;
            color: #38bdf8;
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .popup-coords {
            font-size: 0.9rem;
            color: #00f2ff;
            margin-top: 4px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @media (min-width: 1025px) {
            .popup-title {
                font-size: 1.2rem;
            }

            .popup-category {
                font-size: 0.85rem;
            }

            .popup-coords {
                font-size: 0.8rem;
            }

            .image-carousel img {
                max-height: 250px;
                object-fit: cover;
            }
        }

        /* Sikre at innholdet i popoveren bruker hele bredden symmetrisk */
        .popup-content {
            padding-right: 0 !important;
        }

        /* Tynnere og penere scrollbar som kun vises ved behov */
        .popup-content::-webkit-scrollbar {
            width: 5px;
        }

        .popup-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .popup-content::-webkit-scrollbar-thumb {
            background: rgba(56, 189, 248, 0.4);
            border-radius: 10px;
        }

        .popup-content::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        .property-popup table {
            width: 100%;
            border-collapse: collapse;
        }

        .property-popup td {
            padding: 10px 4px !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.9rem;
        }

        /* STOR RØD LUKK-KNAPP I POPUP */
        .property-popup .leaflet-popup-close-button,
        .point-popup .leaflet-popup-close-button,
        .modal-back {
            background: #475569 !important;
            /* Dark gray for back button */
            color: white !important;
            width: auto !important;
            height: 30px !important;
            padding: 0 15px !important;
            font-size: 0.85rem !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            opacity: 1 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: flex !important;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            transition: all 0.2s !important;
            z-index: 16000 !important;
            text-decoration: none !important;
            cursor: pointer;
        }

        .modal-back:hover {
            background: var(--accent-color) !important;
            color: #0b0f19 !important;
            transform: scale(1.05);
        }

        .property-popup .leaflet-popup-close-button,
        .point-popup .leaflet-popup-close-button {
            background: #ef4444 !important;
            color: white !important;
            width: 70px !important;
            height: 30px !important;
            font-size: 0 !important;
            /* Hide default X */
            border-radius: 8px !important;
            opacity: 1 !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            display: flex !important;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            transition: all 0.2s !important;
            z-index: 16000 !important;
            text-decoration: none !important;
        }

        .property-popup .leaflet-popup-close-button::after,
        .point-popup .leaflet-popup-close-button::after {
            content: "Lukk" !important;
            font-size: 0.85rem !important;
            font-weight: 700 !important;
            color: white !important;
            display: block !important;
        }

        @media (min-width: 1025px) {
            .point-popup .leaflet-popup-close-button {
                top: 20px !important;
                right: 15px !important;
                width: 60px !important;
                height: 28px !important;
            }
        }



        .property-popup .leaflet-popup-close-button::after,
        .point-popup .leaflet-popup-close-button::after {
            content: "Lukk";
            font-size: 13px !important;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
        }

        .property-popup .leaflet-popup-close-button:hover,
        .point-popup .leaflet-popup-close-button:hover {
            transform: scale(1.05);
            background: #dc2626 !important;
        }

        /* 1. Fjerner den irriterende teksten som \u00f8delegger sjekkboksene */
        .layer-zoom-hint {
            display: none !important;
        }

        @media (max-width: 1024px) {
            .property-popup .leaflet-popup-content {
                width: calc(100vw - 60px) !important;
                max-width: 380px !important;
                /* Keep a reasonable max on tablets */
                max-height: 50vh !important;
                overflow-y: auto !important;
                padding: 15px !important;
            }

            /* Målestokk og attributasjon nede på mobil – samme linje */
            .leaflet-bottom.leaflet-left {
                margin-left: 10px !important;
                margin-bottom: 0 !important;
            }

            .leaflet-bottom.leaflet-right {
                margin-right: 0 !important;
                margin-bottom: 0 !important;
            }

            /* Flytt Fullfør-knappen høyere på mobil for å unngå nettleser-UI */
            #finish-tool-btn {
                bottom: 80px !important;
                padding: 14px 28px;
                font-size: 1rem;
            }
        }

        /* STYLING FOR MÅLESTOKK (Dark theme) */
        .leaflet-control-scale-line {
            background: rgba(15, 23, 42, 0.7) !important;
            backdrop-filter: blur(4px);
            border: 1px solid var(--accent-color) !important;
            border-top: none !important;
            color: white !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 600 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        }

        /* KRAFTIG GLOW FOR EIENDOMSKART */
        .inverted-layer {
            /* filter: invert(1) hue-rotate(180deg) brightness(2.2) contrast(1.4) drop-shadow(0 0 8px rgba(56, 189, 248, 0.9)) drop-shadow(0 0 3px rgba(255, 255, 255, 0.4)) !important; */
            filter: invert(1) hue-rotate(180deg) brightness(2.2) contrast(1.4) !important;
            will-change: filter;
        }

        .dynamic-street-label-marker {
            background: none !important;
            border: none !important;
            box-shadow: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: auto !important;
        }

        .street-label-content {
            color: white !important;
            font-weight: 800 !important;
            font-size: 0.85rem !important;
            text-shadow: -1.2px -1.2px 0 #000, 1.2px -1.2px 0 #000, -1.2px 1.2px 0 #000, 1.2px 1.2px 0 #000, 0 0 4px rgba(0, 0, 0, 0.9) !important;
            white-space: nowrap;
            cursor: pointer;
            padding: 5px 10px;
            /* background: rgba(255,0,0,0.2); // For debugging click area */
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            border-color: var(--accent-color);
        }

        /* SØKERESULTATER DROPDOWN */
        #search-results-list {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            width: 100% !important;
            background: #111827 !important;
            border: 1px solid var(--glass-border) !important;
            border-top: none !important;
            border-radius: 0 0 12px 12px !important;
            z-index: 1000 !important;
            max-height: 300px !important;
            overflow-y: auto !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4) !important;
            backdrop-filter: none !important;
            /* Stable on mobile */
        }

        /* Knapp for å avslutte måling/høyde */
        #finish-tool-btn {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 5000;
            display: none;
            /* Skjult som standard */
            background: #10b981;
            /* Grønn for fullfør */
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        #finish-tool-btn:hover {
            background: #059669;
            transform: translateX(-50%) scale(1.05);
        }

        /* INSTALL APP BUTTON - Styling only, positioning at end */
        #install-app-btn {
            background: var(--accent-color);
            color: #0b0f19;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.4);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
            display: none;
            /* Hidden by default */
            align-items: center;
            gap: 8px;
        }

        .install-close-x {
            margin-left: 10px;
            padding: 2px 8px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            transition: background 0.2s;
            line-height: 1;
        }

        .install-close-x:hover {
            background: rgba(255, 0, 0, 0.2);
            color: #ff4444;
        }

        #install-app-btn:hover {
            transform: translateX(-50%) scale(1.05);
            background: #fff;
        }

        /* INSTALL MODAL */
        #install-instructions-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .install-modal-content {
            background: #1e293b;
            padding: 30px;
            border-radius: 20px;
            max-width: 90%;
            width: 340px;
            text-align: center;
            border: 1px solid var(--accent-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .install-modal-content h3 {
            margin-top: 0;
            color: white;
            font-size: 1.4rem;
        }

        .install-step {
            margin: 20px 0;
            color: #cbd5e1;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .share-icon-svg {
            width: 24px;
            height: 24px;
            fill: #38bdf8;
        }

        .close-install-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #94a3b8;
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
        }

        .search-result-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .search-result-item:hover {
            background: rgba(56, 189, 248, 0.2);
        }

        #coord-search-container {
            /* Basic styling, positioning at end */
            display: flex;
            align-items: center;
            gap: 10px;
            background: #111827;
            /* SOLID background */
            backdrop-filter: blur(12px);
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            flex-wrap: nowrap !important;
        }

        #desktop-controls,
        #mobile-toggle,
        #finish-tool-btn {
            /* Positioning at end */
        }

        #coord-search-input {
            background: transparent;
            border: none;
            color: white;
            padding: 4px 0;
            font-size: 0.95rem;
            width: 100%;
            min-width: 0;
            outline: none;
        }

        #coord-search-input:focus {
            border-color: var(--accent-color);
            background: rgba(0, 0, 0, 0.5);
        }

        #coord-search-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0 !important;
        }

        #coord-search-btn:hover {
            background: #0284c7;
            transform: translateY(-1px);
        }

        #coord-search-btn:active {
            transform: translateY(0);
        }

        #coord-picker-btn {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #coord-picker-btn.active {
            background: var(--accent-color);
            color: var(--bg-color);
            border-color: var(--accent-color);
            box-shadow: 0 0 15px var(--accent-glow);
        }

        #coord-picker-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        #map.picking-mode,
        #map.measuring-mode {
            cursor: crosshair !important;
        }

        /* Måleverktøy styling */
        #measure-btn {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #measure-btn.active {
            background-color: #38bdf8 !important;
            color: #0b0f19 !important;
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.5);
            border-color: #38bdf8;
        }

        #elevation-btn {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #elevation-btn.active {
            background-color: #ef4444 !important;
            color: #fff !important;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.5);
            border-color: #ef4444;
        }

        #map.elevation-mode {
            cursor: crosshair !important;
        }

        .elevation-popup img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .elevation-dl-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            background: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Mobile adjustments at end */

        #site-list {
            flex-grow: 1;
            padding: 0 12px 24px 12px;
        }

        .site-card {
            padding: 18px;
            margin: 12px 0;
            border-radius: 16px;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .site-card:hover {
            background: var(--card-hover);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateX(6px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }

        /* Modal Navigation Buttons */
        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            /* Shown via JS */
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 10;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .modal-nav:hover {
            background: var(--accent-color);
            color: #0b0f19;
            transform: translateY(-51%) scale(1.1);
            box-shadow: 0 0 20px var(--accent-glow);
            border-color: var(--accent-color);
        }

        .modal-nav:active {
            transform: translateY(-50%) scale(0.95);
        }

        .modal-nav.prev {
            left: 20px;
        }

        .modal-nav.next {
            right: 20px;
        }

        @media (max-width: 768px) {
            .modal-nav {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .modal-nav.prev {
                left: 10px;
            }

            .modal-nav.next {
                right: 10px;
            }
        }

        .site-card h3 {
            margin: 6px 0 0 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .site-card .category {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-dim);
            font-weight: 700;
            opacity: 0.8;
        }

        /* Popup Styles */
        .leaflet-popup-content-wrapper {
            background: #111827 !important;
            color: white !important;
            border: 1px solid var(--accent-color);
            border-radius: 16px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6) !important;
            width: fit-content !important;
            max-width: 300px !important;
            min-width: 0 !important;
        }

        .leaflet-popup-content {
            width: auto !important;
            margin: 0 !important;
            box-sizing: border-box;
        }

        .leaflet-popup-tip {
            background: #111827 !important;
        }

        /* Spesialfiks for Søkehjelp (smalere og uten den røde Lukk-knappen) */
        .compact-popup .leaflet-popup-content-wrapper {
            max-width: 280px !important;
            width: fit-content !important;
            padding: 0 !important;
        }

        .compact-popup .leaflet-popup-content {
            width: auto !important;
            margin: 0 !important;
        }

        .compact-popup .leaflet-popup-close-button {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #38bdf8 !important;
            width: 24px !important;
            height: 24px !important;
            font-size: 14px !important;
            box-shadow: none !important;
            top: 10px !important;
            right: 10px !important;
            display: flex !important;
        }

        .compact-popup .leaflet-popup-close-button::after {
            content: "✕" !important;
            font-size: 14px !important;
            color: #38bdf8 !important;
        }

        .popup-content {
            padding: 8px;
        }

        .popup-content h2 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: var(--accent-color);
        }

        .popup-content p {
            font-size: 0.9rem;
            line-height: 1.5;
            color: #cbd5e1;
        }

        .popup-content img {
            width: 100%;
            border-radius: 8px;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* Popup Carousel Buttons */
        .image-carousel {
            position: relative;
            margin-top: 10px;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.8rem;
            z-index: 10;
            transition: background 0.2s;
        }

        .carousel-btn:hover {
            background: var(--accent-color);
        }

        .carousel-btn.prev {
            left: 5px;
        }

        .carousel-btn.next {
            right: 5px;
        }

        .image-counter {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        /* Modal Styles */
        #content-modal,
        #about-modal,
        #site-list-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(2, 6, 17, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100000;
        }

        /* Mer gjennomsiktig bakgrunn for info-modaler så man ser kartet bak */
        #about-modal,
        #site-list-modal {
            background: rgba(2, 6, 17, 0.8) !important;
        }

        /* No backdrop for compact modal */
        #content-modal.compact {
            background: transparent;
        }

        .modal-container {
            width: 90%;
            height: 90%;
            background: #111827;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            border: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
        }

        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(17, 24, 39, 0.85);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.2s;
        }

        .modal-nav:hover {
            background: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-50%) scale(1.1);
        }

        .modal-nav.prev {
            left: 20px;
        }

        .modal-nav.next {
            right: 20px;
        }

        #about-modal .modal-container {
            max-width: 550px;
            height: auto;
            max-height: 85vh;
        }

        /* Compact modal for reguleringsplan */
        #content-modal.compact .modal-container {
            width: auto;
            max-width: 800px;
            min-width: 600px;
            height: auto;
            max-height: 80vh;
        }

        .modal-header {
            background: var(--bg-color);
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--glass-border);
        }

        .modal-close {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .modal-close:hover {
            background: #dc2626;
        }

        iframe#modal-iframe {
            width: 100%;
            height: calc(100% - 60px);
            border: none;
        }

        /* Recent Changes Style */
        .recent-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin: 4px 0;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .recent-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .recent-icon {
            margin-right: 8px;
            font-size: 1rem;
        }

        /* Customizing Leaflet Layer Control */
        .leaflet-control-layers {
            background: var(--panel-bg) !important;
            border: 1px solid var(--glass-border) !important;
            color: white !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
        }

        .leaflet-control-layers-list {
            padding: 5px;
        }

        /* Highlights & Stats */
        .sidebar-section {
            padding: 20px 24px;
            border-bottom: 1px solid var(--glass-border);
        }

        .section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-dim);
            margin-bottom: 12px;
            font-weight: 700;
        }

        .highlight-card {
            background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), rgba(56, 189, 248, 0.05));
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .highlight-card:hover {
            transform: scale(1.02);
            border-color: var(--accent-color);
        }

        .highlight-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 14px 10px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--glass-border);
            transition: all 0.2s;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-2px);
        }

        .stat-value {
            display: block;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--accent-color);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-dim);
            font-weight: 600;
            margin-top: 4px;
            display: block;
        }

        .btn-back {
            position: fixed;
            top: 20px;
            left: 95px !important;
            /* Flytt Portal-knappen litt mer til høyre slik at den ikke overlapper Meny-knappen */
            /* Initially overlapping, will adjust via JS/class if needed, or set static */
            z-index: 1100;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .btn-back:hover {
            background: var(--accent-color);
            color: #0b0f19;
            transform: translateX(-5px);
        }

        /* Adjust back button when sidebar is open */
        #sidebar:not(.collapsed)~.btn-back {
            left: 400px;
            /* sidebar width 380 + 20 */
        }

        /* Adjust back button when sidebar is collapsed (Desktop) */
        #sidebar.collapsed~.btn-back {
            left: 150px;
            /* Move further right to avoid Meny button */
        }

        /* Emoji Marker Styles */
        .emoji-marker {
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer !important;
        }

        .emoji-marker:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: scale(1.4) translateY(-4px);
            z-index: 1000 !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
        }

        .btn-back:hover {
            background: var(--accent-color);
            color: var(--bg-color);
        }

        /* Loading Overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            transition: opacity 0.5s;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.7;
                transform: scale(0.95);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Mobile adjustments */
        @media (max-width: 1024px) {

            /* Nullstill leaflet-bottom så de ikke overstyrer */
            .leaflet-bottom {
                bottom: env(safe-area-inset-bottom, 5px) !important;
            }

            /* Bruk absolute posisjonering for å bryte ut av Leaflets 'float' logikk som skaper stack overflow på smale skjermer */
            .leaflet-control-scale {
                position: absolute !important;
                bottom: 0 !important;
                /* Bottom 0 inside .leaflet-bottom.leaflet-left */
                left: 10px !important;
                margin: 0 !important;
                height: 22px !important;
            }

            .leaflet-control-scale-line {
                height: 22px !important;
                line-height: 20px !important;
                box-sizing: border-box !important;
                margin: 0 !important;
            }

            .leaflet-control-attribution {
                position: absolute !important;
                bottom: 0 !important;
                /* Bottom 0 inside .leaflet-bottom.leaflet-right */
                right: 10px !important;
                margin: 0 !important;
                height: 22px !important;
                line-height: 22px !important;
                box-sizing: border-box !important;
                padding: 0 5px !important;
            }

            /* Flytt zoom- og locate-knapper opp slik at de ikke dekker attributasjon/målestokk */
            .leaflet-control-location,
            .leaflet-control-locate {
                margin-bottom: 30px !important;
                /* Clear the 22px attribution + 8px margin */
            }

            .leaflet-control-zoom {
                margin-bottom: 10px !important;
            }

            /* Disable heavy effects for better performance on mobile */
            .emoji-marker {
                background: #ffffff !important;
                /* Solid on mobile */
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                border: 1px solid #ccc !important;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3) !important;
            }

            .leaflet-popup-content-wrapper {
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                background: #111827 !important;
                /* Fully opaque dark */
                border: 1px solid var(--accent-color) !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6) !important;
            }

            .leaflet-popup-tip {
                background: #111827 !important;
                backdrop-filter: none !important;
            }

            /* Enable scrolling in map layer list if it's too long */
            /* v117: Increased heights for less scrolling + hide-before-js-init trick */
            .leaflet-control-layers-expanded {
                max-height: 88vh !important;
                display: flex !important;
                flex-direction: column !important;
                width: 300px !important;
                box-sizing: border-box !important;
            }

            /* v117: Hide the panel initially to prevent full-size flash on first load */
            .leaflet-control-layers:not(.leaflet-control-layers-expanded) {
                visibility: hidden !important;
                max-height: 0 !important;
                overflow: hidden !important;
            }

            .leaflet-control-layers-list {
                max-height: 83vh !important;
                overflow-y: auto !important;
                padding-right: 15px !important;
                -webkit-overflow-scrolling: touch;
            }

            /* TVING KARTMENYEN NED UNDER KNAPPEN */
            /* Vi må treffe både selve kontrollen og den utvidede listen */
            .leaflet-top.leaflet-right .leaflet-control-layers {
                top: 82px !important;
                /* Rett under knappen */
                margin-top: 0 !important;
                margin-right: 0 !important;
                position: fixed !important;
                right: 20px !important;
                z-index: 10001 !important;
            }

            /* Sørg for at den utvidede menyen følger samme plassering */
            .leaflet-control-layers-expanded {
                top: 0 !important;
                /* Relativt til beholderen som nå er flyttet ned */
                right: 0 !important;
                margin: 0 !important;
                z-index: 10001 !important;
            }

            /* Prevent auto-collapse on mouseout */
            .leaflet-control-layers.leaflet-control-layers-expanded {
                display: block !important;
            }

            .leaflet-control-layers-toggle {
                display: none !important;
            }

            /* Hiding redundant elements */
            .leaflet-control-layers-toggle::before,
            .leaflet-control-layers-toggle::after {
                display: none !important;
            }

            .leaflet-control-layers-toggle::before {
                content: "☰";
                font-size: 1.2rem;
                line-height: 1;
                font-weight: 400;
                display: block;
                margin-bottom: auto;
                padding-top: 8px;
            }

            /* Fjern X-en som Leaflet lager på mobil/utvidet modus */
            .leaflet-control-layers-expanded .leaflet-control-layers-toggle::before,
            .leaflet-control-layers-close {
                content: none !important;
                display: none !important;
            }

            .leaflet-control-layers-toggle::after {
                content: "Kart";
                font-size: 0.6rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--accent-color);
                display: block;
                line-height: 1;
                text-decoration: none !important;
            }

            .leaflet-control-layers-expanded .leaflet-control-layers-toggle {
                display: none !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }

            .leaflet-control-layers-expanded .leaflet-control-layers-toggle:hover {
                background-color: var(--card-hover) !important;
                transform: scale(1.05) !important;
            }

            .leaflet-control-layers-expanded .leaflet-control-layers-toggle::after {
                display: none !important;
            }

            .leaflet-control-layers-expanded {
                background: #111827 !important;
                /* Solid dark background */
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                border: 1px solid var(--accent-color) !important;
                border-radius: 16px !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8) !important;
                padding: 15px !important;
                margin-top: 0 !important;
                position: relative !important;
            }
        }

        /* Custom Multiselect styles */
        .category-multiselect {
            position: relative;
            margin-top: 10px;
            width: 100%;
        }

        .multiselect-trigger {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .multiselect-trigger:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-color);
        }

        .multiselect-trigger::after {
            content: '▼';
            font-size: 0.7rem;
            opacity: 0.6;
            transition: transform 0.2s;
        }

        .category-multiselect.open .multiselect-trigger::after {
            transform: rotate(180deg);
        }

        .multiselect-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #111827;
            /* Darker solid background for the dropdown */
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            margin-top: 5px;
            z-index: 1000;
            max-height: 350px;
            overflow-y: auto;
            padding: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
        }

        .category-multiselect.open .multiselect-dropdown {
            display: block;
        }

        .category-checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 8px;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.1s;
        }

        .category-checkbox-item:hover,
        .flyfoto-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .flyfoto-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            /* 12px sideveis luft for å matche Kartmeny */
            font-size: 13px;
            min-height: 20px;
            /* Tvinger linjene tett sammen */
            color: #e2e8f0;
            cursor: pointer;
            transition: background 0.1s;
            line-height: 1.1;
        }

        .flyfoto-item input[type="radio"] {
            margin: 0;
            /* Fjerner margin som dytter linjen opp/ned */
            padding: 0;
            cursor: pointer;
            width: 14px;
            height: 14px;
        }

        .flyfoto-item.active {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-color);
        }

        .category-checkbox-item input {
            width: 20px;
            height: 20px;
            accent-color: var(--accent-color);
            cursor: pointer;
        }

        .category-checkbox-item label {
            cursor: pointer;
            font-size: 0.95rem;
            color: #e2e8f0;
            flex-grow: 1;
        }

        .multiselect-actions {
            display: flex;
            gap: 8px;
            padding: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 8px;
        }

        .multiselect-btn {
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.2);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .multiselect-btn:hover {
            background: rgba(56, 189, 248, 0.2);
            border-color: var(--accent-color);
        }

        .sidebar-close-btn {
            background: transparent;
            border: none;
            color: var(--text-dim);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            line-height: 1;
            transition: all 0.2s;
            margin-top: -8px;
            margin-right: -8px;
        }

        .sidebar-close-btn:hover {
            color: var(--accent-color);
            background: rgba(255, 255, 255, 0.1);
        }

        /* ==========================================================================
           GLOBAL UI LAYOUT & LAYER HIERARCHY
           ========================================================================== */

        /* 1. Global Overlay: Modals */
        #content-modal,
        #about-modal,
        #site-list-modal {
            z-index: 1000000 !important;
        }

        /* 2. Global Menu: Sidebar */
        #sidebar {
            z-index: 50000 !important;
        }

        /* 3. Map Content: Popups must win over floating UI */
        .leaflet-popup-pane,
        .leaflet-popup {
            z-index: 40000 !important;
            max-width: none !important;
            /* Allow custom widths */
        }

        .leaflet-popup-content-wrapper {
            width: min(95vw, 850px) !important;
            /* Dynamic widening */
            max-width: none !important;
        }

        /* 4. Floating UI Positioning - DESKTOP & MOBILE */

        /* Coordinate Search: Centered Top */
        #coord-search-container {
            position: fixed !important;
            top: 20px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            z-index: 2147483647 !important;
            display: flex !important;
            align-items: center !important;
            width: calc(100% - 40px) !important;
            max-width: 500px !important;
            box-sizing: border-box !important;
            background: #111827 !important;
            border-radius: 12px !important;
            border: none !important;
            padding: 8px 12px !important;
            box-shadow: none !important;
        }

        /* Desktop Controls (Kart/Flyfoto): Top Right */
        #desktop-controls {
            position: fixed !important;
            /* Fixed to follow scroll/stay in place correctly */
            top: 20px !important;
            right: 20px !important;
            display: flex !important;
            gap: 15px !important;
            z-index: 30000 !important;
            transform: translateZ(0) !important;
            backface-visibility: hidden !important;
        }

        #flyfoto-menu {
            position: fixed !important;
            top: 85px !important;
            right: 20px !important;
            z-index: 30001 !important;
            background: #111827;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
            display: none;
            /* Her setter vi bredden lik kartmenyen */
            width: 240px !important;
            min-width: 240px !important;
            padding: 4px 0 !important;
            /* Minimal luft topp/bunn */
        }

        /* Install App Button: Centered Bottom */
        #install-app-btn {
            position: fixed !important;
            bottom: 12px !important;
            /* Lowered */
            top: auto !important;
            /* CRITICAL: Override any accidental top */
            left: 50% !important;
            transform: translateX(-50%) !important;
            z-index: 45000 !important;
            padding: 8px 16px !important;
            /* Smaller */
            font-size: 0.85rem !important;
            /* Smaller */
        }

        /* Mobile Adjustments (Override) */
        @media (max-width: 1024px) {
            #mobile-toggle {
                position: fixed !important;
                top: 95px !important;
                left: 15px !important;
                z-index: 2147483647 !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                background: #111827 !important;
            }

            #coord-search-container {
                left: 15px !important;
                right: 15px !important;
                width: auto !important;
                top: 20px !important;
                transform: none !important;
                max-width: none !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                background: #111827 !important;
                z-index: 2147483647 !important;
                transform: translate3d(0, 0, 0) !important;
                box-shadow: none !important;
                border: none !important;
            }

            #desktop-controls {
                position: fixed !important;
                top: 95px !important;
                right: 15px !important;
                z-index: 2147483647 !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                background: #111827 !important;
                border-radius: 12px !important;
                border: none !important;
                box-shadow: none !important;
            }

            #desktop-controls .desktop-btn {
                background: transparent !important;
                backdrop-filter: none !important;
                box-shadow: none !important;
                border: none !important;
            }

            #flyfoto-menu {
                top: 154px !important;
                /* Adjusted for buttons move */
                right: 15px !important;
            }

            #mobile-slider-wrapper {
                display: none;
                position: fixed !important;
                top: 95px !important;
                left: 82px !important;
                right: 149px !important;
                height: auto !important;
                min-height: 52px !important;
                z-index: 2147483647 !important;
                background: #111827 !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                transform: translate3d(0, 0, 0) !important;
                border: none !important;
                border-radius: 12px !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 10px !important;
                box-sizing: border-box !important;
                flex-direction: column !important;
                box-shadow: none !important;
            }

            #mobile-slider-wrapper:empty {
                display: none;
            }
        }

        @media (min-width: 1025px) {
            #mobile-slider-wrapper {
                display: none;
            }
        }

        /* Narrow mobile fixes for search button visibility */
        @media (max-width: 480px) {
            #coord-search-container {
                gap: 5px !important;
                padding: 6px 8px !important;
            }

            #coord-picker-btn,
            #measure-btn,
            #elevation-btn {
                padding: 6px 8px !important;
                font-size: 14px !important;
            }

            #coord-search-input {
                width: 120px !important;
                flex: 1 !important;
                /* Allow it to shrink */
                min-width: 80px !important;
            }

            #search-help-toggle {
                display: none !important;
                /* Hide help on very narrow screens to save space */
            }

            #coord-search-btn {
                padding: 8px 12px !important;
                font-size: 0.85rem !important;
            }
        }

        /* Mobile Modal Close Button Fix */
        /* Mobile Modal Adjustments */
        @media (max-width: 850px) {

            /* Make modal narrower to allow map interaction on sides */
            .modal-container {
                width: 100% !important;
                height: 100% !important;
                max-width: 100% !important;
                max-height: 100% !important;
                border-radius: 0 !important;
                padding: 60px 10px 80px 10px !important;
                /* Space for top/bottom bars */
                position: relative !important;
                margin: auto !important;
                /* Center it */
                transform: none !important;
                /* CRITICAL: Prevent trapping fixed elements */
            }

            /* Force the close button to be fixed at the bottom */
            .modal-close,
            .close-install-modal,
            #content-modal .modal-close,
            #site-list-modal .modal-close {
                position: fixed !important;
                bottom: 30px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                top: auto !important;
                right: auto !important;
                z-index: 2000002 !important;
                /* Higher than map controls */
                display: flex !important;
                width: auto !important;
                padding: 12px 24px !important;
                background: #ef4444 !important;
                color: white !important;
                border-radius: 50px !important;
                box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4) !important;
                font-weight: 600 !important;
                font-size: 16px !important;
                align-items: center !important;
                justify-content: center !important;
            }

            /* Move close button to bottom center FIXED on screen */
            #site-list-modal .modal-close,
            .close-install-modal {
                position: fixed !important;
                /* Fixed to viewport */
                bottom: 30px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                top: auto !important;
                right: auto !important;
                z-index: 60000 !important;
                /* Extremely high to sit on top of everything */
                background: #ef4444 !important;
                color: white !important;
                padding: 12px 30px !important;
                border-radius: 50px !important;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4) !important;
                font-size: 1rem !important;
                font-weight: 700 !important;
                width: auto !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
                border: 2px solid white !important;
                /* Make it pop */
                text-decoration: none !important;
                /* For a tags if used */
            }

        }
    </style>
</head>

<body>

    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top: 20px;">Laster gruvekunnskap...</p>
    </div>







    <div id="app-container">
        <div id="sidebar">
            <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>Skiensmarka</h1>
                    <p>Gruver & Severdigheter</p>
                </div>
                <button onclick="toggleSidebar()" class="sidebar-close-btn" title="Lukk meny">✕</button>
            </div>


            <!-- Action Cards Section -->
            <div class="sidebar-section">
                <a href="../index.html" class="btn-back">← Tilbake til Portalen</a>
                <!-- Action Cards Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; width: 100%;">
                    <!-- About Card -->
                    <div class="site-card" onclick="openAboutModal()"
                        style="margin: 0; background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), rgba(56, 189, 248, 0.05)); border: 1px solid rgba(56, 189, 248, 0.3);box-shadow: 0 0 15px rgba(56, 189, 248, 0.05); padding: 8px 4px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px; cursor: pointer;">
                        <span style="font-size: 1.2rem;">ⓘ</span>
                        <h3 style="margin: 0; font-size: 0.75rem; font-weight: 700;">Om kartet</h3>
                    </div>

                    <!-- Site List Card -->
                    <div class="site-card" onclick="openSiteListModal()"
                        style="margin: 0; background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05)); border: 1px solid rgba(34, 197, 94, 0.3); box-shadow: 0 0 15px rgba(34, 197, 94, 0.05); padding: 8px 4px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px; cursor: pointer;">
                        <span style="font-size: 1.2rem;">📜</span>
                        <h3 style="margin: 0; font-size: 0.75rem; font-weight: 700;">Alle steder</h3>
                    </div>

                    <!-- Album Card -->
                    <div class="site-card"
                        onclick="openModal('https://photos.app.goo.gl/QkDQDJ5XMjqQcnvo7', 'Skiensmarka Album')"
                        style="margin: 0; background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(168, 85, 247, 0.05)); border: 1px solid rgba(168, 85, 247, 0.3); box-shadow: 0 0 15px rgba(168, 85, 247, 0.05); padding: 8px 4px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px; cursor: pointer;">
                        <span style="font-size: 1.2rem;">🖼️</span>
                        <h3 style="margin: 0; font-size: 0.75rem; font-weight: 700;">Album</h3>
                    </div>
                </div>
            </div>


            <div class="search-container" style="padding-top: 5px;">
                <input type="text" id="search" class="search-input" placeholder="Søk i gruver, steder eller adresse..."
                    aria-label="Søk i steder">
                <div class="category-multiselect" id="cat-multiselect">
                    <div class="multiselect-trigger" onclick="toggleCatDropdown()">
                        <span id="multiselect-label">Alle Kategorier</span>
                    </div>
                    <div class="multiselect-dropdown">
                        <div class="multiselect-actions">
                            <button type="button" class="multiselect-btn" onclick="selectAllCats(true)">Alle</button>
                            <button type="button" class="multiselect-btn" onclick="selectAllCats(false)">Ingen</button>
                        </div>
                        <div id="category-checkbox-list">
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-ALL" value="ALL" checked
                                    onchange="handleCatChange(this)"><label for="cat-ALL">🌟 Alle Kategorier</label>
                            </div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-GRUVE" value="GRUVE"
                                    checked onchange="handleCatChange(this)"><label for="cat-GRUVE">⚒️ Gruver &
                                    Skjerp</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-BYGDEBORG"
                                    value="BYGDEBORG" checked onchange="handleCatChange(this)"><label
                                    for="cat-BYGDEBORG">🏰 Bygdeborger</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-HUSTUFT" value="HUSTUFT"
                                    checked onchange="handleCatChange(this)"><label for="cat-HUSTUFT">🧱 Hustufter &
                                    Ruiner</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-UTSIKT" value="UTSIKT"
                                    checked onchange="handleCatChange(this)"><label for="cat-UTSIKT">🔭
                                    Utsiktspunkter</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-VANN" value="VANN"
                                    checked onchange="handleCatChange(this)"><label for="cat-VANN">💧
                                    Vannsystemer</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-GRENSESTEIN"
                                    value="GRENSESTEIN" checked onchange="handleCatChange(this)"><label
                                    for="cat-GRENSESTEIN">🗿 Grensesteiner</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-GRAVHAUG"
                                    value="GRAVHAUG" checked onchange="handleCatChange(this)"><label
                                    for="cat-GRAVHAUG">🪨 Gravhauger</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-GAPAHUK" value="GAPAHUK"
                                    checked onchange="handleCatChange(this)"><label for="cat-GAPAHUK">⛺
                                    Gapahuker</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-HULE" value="HULE"
                                    checked onchange="handleCatChange(this)"><label for="cat-HULE">⛰️ Huler /
                                    Grotter</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-VEI" value="VEI" checked
                                    onchange="handleCatChange(this)"><label for="cat-VEI">🛣️ Veier</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-DIVERSE" value="DIVERSE"
                                    checked onchange="handleCatChange(this)"><label for="cat-DIVERSE">📦 Diverse /
                                    Kultur</label></div>
                            <div class="category-checkbox-item"><input type="checkbox" id="cat-DEFAULT" value="DEFAULT"
                                    checked onchange="handleCatChange(this)"><label for="cat-DEFAULT">📍
                                    Interessepunkter</label></div>
                        </div>
                    </div>
                </div>

                <!-- Marker Toggle Checkbox -->
                <div style="margin-top: 12px; display: flex; align-items: center; gap: 10px; padding: 4px 8px;">
                    <input type="checkbox" id="toggle-markers" checked onchange="toggleMarkers(this)"
                        style="width: 18px; height: 18px; accent-color: var(--accent-color); cursor: pointer;">
                    <label for="toggle-markers" style="font-size: 0.9rem; color: var(--text-dim); cursor: pointer;">Vis
                        alle punkter</label>
                </div>
            </div>

            <div class="sidebar-section" id="daily-photo-section" style="display: none;">
                <div class="section-title">Dagens Bilde</div>
                <div id="daily-photo-container"></div>
            </div>

            <div class="sidebar-section">
                <div class="section-title">Statistikk</div>
                <div class="stats-grid" id="stats-container">
                    <!-- Populated via JS -->
                </div>
            </div>

            <div class="sidebar-section">
                <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    Siste Endringer
                    <select id="recent-count" class="search-input"
                        style="width: auto; padding: 4px 8px; height: auto; margin: 0; font-size: 0.7rem;"
                        aria-label="Antall siste endringer">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                    </select>
                </div>
                <div id="recent-changes-container">
                    <!-- Populated via JS -->
                </div>
            </div>

            <!-- Sidebar Footer with Sync Info -->
            <?php
            $data_file = 'full_data.json';
            $last_updated = file_exists($data_file) ? filemtime($data_file) : 0;
            $needs_sync = (time() - $last_updated) > (7 * 24 * 60 * 60);
            ?>
            <div
                style="padding: 15px 24px; border-top: 1px solid var(--glass-border); font-size: 0.75rem; color: var(--text-dim); text-align: center; background: rgba(0,0,0,0.2);">
                Kartdata sist oppdatert: <span
                    id="last-sync-time"><?php echo date("d.m.Y H:i", $last_updated); ?></span>
                <?php if ($needs_sync): ?>
                    <script>                     // Lazy sync: Trigger background update since it's > 7 days                     setTimeout(() => {                         fetch('sync_smart.php?key=vox_cron_auto_7734').then(r => r.text()).then(data => {                             console.log("Automatic KML sync triggered.");                             // Verify success by checking if output contains "Done" or "Success"                             if (data.includes("Done") || data.includes("Success")) {                                 const syncLabel = document.getElementById('last-sync-time');                                 if (syncLabel) syncLabel.innerText = "Nettopp nå (oppdatert)";                             }                         }).catch(e => console.error("Sync failed", e));                     }, 2000);
                    </script>
                <?php endif; ?>

                <?php
                $last_img_sync_file = 'last_image_sync.txt';
                $last_img_sync = file_exists($last_img_sync_file) ? (int) file_get_contents($last_img_sync_file) : 0;
                if ((time() - $last_img_sync) > (7 * 24 * 60 * 60)):
                    file_put_contents($last_img_sync_file, time());
                    ?>
                    <script>                     fetch('sync_images_trigger.php').catch(e => console.error("Image sync failed", e));
                    </script>
                <?php endif; ?>

                <div style="margin-top: 5px; opacity: 0.6;">Automatisk synkronisering hver uke</div>
                <div style="margin-top: 25px; opacity: 0.2; cursor: pointer; display: inline-block; padding: 5px; font-weight: 800; font-size: 0.7rem; letter-spacing: 0.05em;"
                    id="georef-secret-trigger" onclick="handleSecretGeoref()" title="Georef-verktøy">GeoRef</div>
                <div style="margin-top: 5px; opacity: 0.2; cursor: pointer; display: inline-block; padding: 5px; font-weight: 800; font-size: 0.7rem; letter-spacing: 0.05em;"
                    onclick="handleAdminSecret()" title="Admin-verktøy">Admin</div>
                <div style="margin-top: 15px; cursor: pointer; display: inline-block; padding: 8px 14px; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.03em; background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; border-radius: 8px; opacity: 0.85;"
                    onclick="showGuestInfoModal()" title="Foreslå et nytt punkt til kartet">📍 Legg til punkt</div>
            </div>

        </div>

        <div id="map">
            <!-- Map content managed by Leaflet -->
        </div>
    </div>

    <div id="content-modal">
        <div class="modal-container">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span id="modal-title" style="font-weight: 700; color: var(--accent-color);">Innhold</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button id="modal-back-btn" class="modal-back" onclick="modalBack()"
                        style="display: none;">Tilbake</button>
                    <button class="modal-close" id="mobile-close-btn" onclick="closeModal()">Lukk</button>
                </div>
            </div>
            <div id="modal-content-wrapper"
                style="flex-grow: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; background: #000;">

                <div id="modal-loader"
                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none; flex-direction: column; align-items: center; gap: 15px; z-index: 5;">
                    <div class="spinner"></div>
                    <p style="color: var(--text-dim); font-size: 0.9rem;">Henter innhold...</p>
                </div>

                <iframe id="modal-iframe" style="width: 100%; height: 100%; border: none;"
                    allow="autoplay; fullscreen; clipboard-write; encrypted-media; picture-in-picture"></iframe>
                <img id="modal-image"
                    style="display: none; max-width: 100%; max-height: 100%; object-fit: contain; box-shadow: 0 0 30px rgba(0,0,0,0.5);">
                <div id="modal-html-content"
                    style="display: none; width: 100%; height: 100%; overflow-y: auto; -webkit-overflow-scrolling: touch;">
                </div>

                <button class="modal-nav prev" id="modal-prev" onclick="changeModalImage(-1)">❮</button>
                <button class="modal-nav next" id="modal-next" onclick="changeModalImage(1)">❯</button>
            </div>
        </div>
    </div>

    <div id="about-modal">
        <div class="modal-container">
            <div class="modal-header">
                <span style="font-weight: 700; color: var(--accent-color); font-size: 1.2rem;">Om kartet</span>
                <button class="modal-close" onclick="closeAboutModal()">Lukk</button>
            </div>
            <div
                style="padding: 30px; color: var(--text-main); line-height: 1.8; font-size: 1.05rem; overflow-y: auto;">
                <p>
                    Diverse fra Gulset, Gulsetmarka, Vestmarka, områder i nærheten og Skien generelt. Gruver,
                    bygdeborger, topper, gapahuker og andre severdigheter.</p>

                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--glass-border);">
                    <h4 style="color: var(--accent-color); margin-bottom: 10px;">Hvordan bruke kartet</h4>
                    <p style="font-size: 0.95rem;">
                        Her kan du utforske Skiensmarka på din egen måte. Klikk på punktene i kartet for å få
                        mer
                        informasjon, se bilder eller videoer.
                        Bruk menyen («Alle steder») for å få en oversikt over alle registrerte funn, eller bruk
                        søkefeltet for å lete etter spesifikke steder.
                    </p>
                    <p style="font-size: 0.95rem; margin-top: 10px;">
                        Du kan også <strong>filtrere</strong> visningen ved å velge kategorier, slik at du for
                        eksempel
                        kun ser gruver eller bygdeborger.
                    </p>
                </div>

                <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--glass-border);">
                    <h4 style="color: var(--accent-color); margin-bottom: 10px;">Bruk av kartlag</h4>
                    <ul style="padding-left: 20px; list-style-type: disc; font-size: 0.95rem;">
                        <li><strong>Flyfoto:</strong> Velg mellom moderne ortofoto og historiske flyfoto for å
                            se
                            endringer i landskapet over tid.</li>
                        <li><strong>Kulturminner:</strong> Viser data fra Askeladden (Riksantikvaren), inkludert
                            fredede
                            objekter og lokale kulturminner.</li>
                        <li><strong>Eiendomskart:</strong> Viser eiendomsgrenser. Klikk i kartet når laget er
                            aktivt for
                            å se gårds- og bruksnummer, samt link til <strong>SeEiendom</strong> for offisielle
                            detaljer.</li>
                        <li><strong>Gatenavn (Historikk):</strong> Viser historiske gatenavn i Skien. Klikk på navnet
                            for å lese om bakgrunnen og opprinnelsen til veinavnet.</li>
                        <li><strong>Reguleringsplan:</strong> Detaljerte planer som viser hvordan arealer er regulert
                            til ulike formål (bolig, industri, friområde etc.).</li>
                        <li><strong>Løsmasser:</strong> Viser kvartærgeologiske kart over løsmasser som morene, leire og
                            sand, basert på data fra NGU.</li>
                        <li><strong>Mineralressursplan:</strong> Viser geologiske forekomster, som er sentralt
                            for å
                            forstå områdets gruvehistorie.</li>
                        <li><strong>Kvikkleire:</strong> Viser faresoner for kvikkleireskred fra NVE.</li>
                        <li><strong>Radon:</strong> Viser aktsomhetsområder for radonfare basert på
                            berggrunnsgeologi
                            fra NGU.</li>
                        <li><strong>Berggrunn:</strong> Detaljerte geologiske kart som viser ulike steintyper i
                            undergrunnen.</li>
                    </ul>
                </div>

                <div style="margin-top: 20px;">
                    <h4 style="color: var(--accent-color); margin-bottom: 10px;">Zoom og Interaksjon</h4>
                    <p style="font-size: 0.95rem;">Enkelte lag (eiendom og detaljerte kulturminner) krever høyt
                        zoom-nivå. Kartet zoomer automatisk inn ved aktivering hvis nødvendig.</p>
                </div>

                <p style="margin-top: 25px; padding-top: 25px; border-top: 1px solid var(--glass-border);">
                    En spesiell takk til <strong style="color: var(--text-bright);">Alf Olav Larsen</strong> for
                    uvurderlig hjelp
                    i arbeidet med å finne mange av gruvene!
                    Hans kompendium om gruver og skjerp i Skien finner du
                    <a href="https://telemarkfylke-my.sharepoint.com/:b:/g/personal/c_borchgrevinkvigeland_telemarkfylke_no/IQAtsBBqe9UMSr7-SzxNbwiMAdhK9VO6O8NCbrrLr0fjLmg?e=m38MPq"
                        target="_blank"
                        style="color: var(--accent-color); text-decoration: none; font-weight: 700; border-bottom: 2px solid var(--accent-glow);">her</a>.
                </p>

                <p style="margin-top: 15px;">
                    En stor takk til <strong style="color: var(--text-bright);">Varden</strong> og <strong
                        style="color: var(--text-bright);">TA</strong> for lån av bilder fra arkivene.
                </p>

                <p style="margin-top: 25px;">Kontakt for kommentarer eller rettelser:<br>
                    <strong style="color: var(--text-bright);">Christian Borchgrevink-Vigeland</strong> (<a
                        href="mailto:cbv@cbv.no"
                        style="color: var(--accent-color); text-decoration: none; font-weight: 600;">cbv@cbv.no</a>)
                </p>

                <p style="margin-top: 25px;">Følg <a href="https://www.instagram.com/voxcuriosa" target="_blank"
                        style="color: var(--accent-color); text-decoration: none; font-weight: 700;">@voxcuriosa</a>
                    på
                    Instagram for utvalgte bilder fra turene.</p>
            </div>
        </div>

    </div>
    </div>

    <!-- ATTRIBUTION MODAL -->
    <div id="attribution-modal"
        style="display:none; position:fixed !important; top:0; left:0; width:100% !important; height:100% !important; background:rgba(0,0,0,0.85) !important; z-index:999999 !important; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); align-items:center; justify-content:center; pointer-events: auto !important;">
        <div class="modal-container"
            style="width:95% !important; height:92% !important; max-width:1400px; background:#111827; border:1px solid var(--accent-color); border-radius:16px; overflow:hidden; display:flex !important; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.8); pointer-events: auto !important;">
            <div
                style="padding:15px 25px; background:rgba(30,41,59,0.5); border-bottom:1px solid var(--glass-border); display:flex !important; justify-content:space-between; align-items:center;">
                <span id="attribution-modal-title"
                    style="font-weight:700; color:var(--accent-color); font-size:1.1rem; letter-spacing:0.02em;">Kildedokumentasjon</span>
                <button class="modal-back" onclick="window.closeAttributionModal()"
                    style="background:#ef4444 !important; border:none; color:white !important; padding:6px 16px; border-radius:8px; cursor:pointer; font-weight:700; font-size:0.9rem; margin:0">Lukk</button>
            </div>
            <iframe id="attribution-iframe"
                style="width:100%; flex-grow:1; border:none; background:white; pointer-events: auto !important;"></iframe>
        </div>
    </div>

    <!-- SITE LIST MODAL -->
    <div id="site-list-modal">
        <div class="modal-container" style="max-width: 600px; height: 90%;">
            <div class="modal-header">
                <span style="font-weight: 700; color: var(--accent-color); font-size: 1.2rem;">Alle Steder</span>
                <button class="modal-close" onclick="closeSiteListModal()">Lukk</button>
            </div>
            <div style="padding: 10px;">
                <input type="text" id="modal-search" class="search-input" placeholder="Søk i listen..."
                    onkeyup="filterModalList()" aria-label="Søk i listen over steder">
            </div>
            <div id="modal-site-list-content"
                style="padding: 10px 20px; color: var(--text-main); overflow-y: auto; height: calc(100% - 120px);">
                <!-- Populated via JS -->
            </div>
        </div>
    </div>

    <!-- PWA Install Button (Hidden by default) -->
    <button id="pwa-install-btn"
        style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 9999; background: #38bdf8; color: #0b0f19; border: none; padding: 12px 20px; border-radius: 30px; font-weight: 700; box-shadow: 0 4px 15px rgba(56,189,248,0.5); cursor: pointer; align-items: center; gap: 8px;">
        <span style="font-size: 1.2rem;">⬇️</span> Installer App
    </button>

    <!-- INSTALLATION MODAL -->
    <div id="install-instructions-modal">
        <div class="install-modal-content">
            <button class="close-install-modal"
                onclick="document.getElementById('install-instructions-modal').style.display='none'">×</button>
            <h3 id="install-modal-title">Installer App</h3>
            <p id="install-modal-desc">For å installere appen på din enhet:</p>

            <div class="install-step">
                1. Trykk på <span style="font-weight:bold">Del</span>
                <svg class="share-icon-svg" viewBox="0 0 24 24">
                    <path
                        d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z" />
                </svg>
            </div>

            <div class="install-step">
                2. Velg <span style="font-weight:bold; color:#38bdf8;" id="install-action-text">"Legg til på
                    Hjem-skjerm"</span>
            </div>

            <div class="install-step" style="font-size: 0.8rem; color: #64748b; margin-top: 20px;">
                (Se etter <span style="border:1px solid #64748b; padding:1px 4px; border-radius:4px;">+</span> ikonet i
                menyen)
            </div>
        </div>
    </div>

    <!-- UI Overlays (Relocated to bottom of body to fix stacking context issues) -->
    <div id="mobile-slider-wrapper"></div>
    <button id="mobile-toggle" onclick="toggleSidebar()" title="Vis/skjul meny">☰</button>
    <button id="finish-tool-btn">✅ Ferdig</button>
    <button id="install-app-btn">
        <span id="install-btn-text">📲 Installer App</span>
        <span class="install-close-x"
            onclick="event.stopPropagation(); document.getElementById('install-app-btn').style.display='none';"
            title="Skjul">✕</span>
    </button>

    <!-- Admin PIN Modal -->
    <div id="admin-pin-modal" class="modal"
        style="display:none; position:fixed; inset:0; z-index: 1000000; align-items:center; justify-content:center; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px);">
        <div class="modal-container"
            style="width:300px; height:auto; padding:20px; background:#1f2937; border:1px solid #374151; border-radius:12px; display:flex; flex-direction:column; gap:15px; position:relative; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <button class="modal-close" onclick="closeAdminPinModal()"
                style="position:absolute; top:10px; right:12px; padding:5px; background:transparent; border:none; color:white; font-size:1.2rem; cursor:pointer;">✕</button>
            <h3 style="margin:0; text-align:center; color:white;">Admin Login</h3>
            <input type="password" id="admin-pin-input-field" placeholder="PIN" inputmode="numeric"
                style="padding:10px; border-radius:4px; border:1px solid #4b5563; background:#374151; color:white; width:100%; box-sizing:border-box; text-align:center; font-size:1.5rem; letter-spacing:5px;"
                autofocus>
            <button onclick="submitAdminPin()"
                style="padding:10px; background:#38bdf8; border:none; border-radius:4px; color:#0b0f19; font-weight:bold; cursor:pointer; font-size:1rem;">Logg
                Inn</button>
        </div>
    </div>

    <div id="coord-search-container">
        <button id="coord-picker-btn" title="Koordinathenter: Klikk i kartet for å hente koordinater">📍</button>
        <button id="measure-btn"
            title="Måleverktøy: Klikk flere punkter for å måle avstand og areal. Avslutt med dobbeltklikk.">📏</button>
        <button id="elevation-btn"
            title="Høydeprofil: Tegn en rute i kartet og dobbeltklikk for å se profil og stigning.">⛰️</button>
        <div style="flex-grow: 1; position: relative; display: flex; align-items: center;">
            <input type="text" id="coord-search-input" placeholder="Søk adresse eller koordinater..."
                aria-label="Søk etter steder eller koordinater">
            <div id="search-results-list"></div>
        </div>
        <div id="search-help-toggle"
            style="cursor:pointer; margin: 0 5px; color: white; background: rgba(56, 189, 248, 0.4); border: 1px solid rgba(56, 189, 248, 0.6); width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 15px; transition: all 0.2s; box-shadow: 0 0 10px rgba(56, 189, 248, 0.2);"
            onclick="toggleSearchHelp()" title="Søke-hjelp">?</div>
        <button id="coord-search-btn">🔍 Søk</button>
    </div>

    <div id="desktop-controls"
        style="position: absolute; top: 20px; right: 20px; z-index: 2000; display: flex; gap: 10px;">
        <div class="desktop-btn" id="desktop-map-btn" title="Velg kartlag">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                <line x1="8" y1="2" x2="8" y2="18"></line>
                <line x1="16" y1="6" x2="16" y2="22"></line>
            </svg>
            <span>Kart</span>
        </div>
        <div class="desktop-btn" id="desktop-flyfoto-btn" title="Historiske flyfoto">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 2 L11 13"></path>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
            <span>Flyfoto</span>
        </div>
    </div>

    <div id="flyfoto-menu">
        <div id="flyfoto-list"></div>
    </div>

    <div id="map-loader"
        style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 2000000; background: #111827; padding: 8px 16px; border-radius: 20px; display: none; align-items: center; gap: 10px; border: 1px solid var(--accent-color);">
        <div class="spinner" style="width: 20px; height: 20px; border-width: 3px;"></div>
        <span style="color: white; font-size: 0.85rem; font-weight: 600;">Henter eiendomsdata...</span>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-toolbar@0.4.0-alpha.2/dist/leaflet.toolbar.js"></script>
    <script src="https://unpkg.com/leaflet-distortableimage@0.21.7/dist/vendor.js"></script>
    <script src="https://unpkg.com/leaflet-distortableimage@0.21.7/dist/leaflet.distortableimage.js"></script>

    <!-- Leaflet Grouped Layer Control JS/CSS (Must be after Leaflet) -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/leaflet-groupedlayercontrol/0.6.1/leaflet.groupedlayercontrol.min.css" />
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-groupedlayercontrol/0.6.1/leaflet.groupedlayercontrol.min.js"></script>

    <!-- Proj4 and Proj4Leaflet for CRS support (UTM32) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.11.0/proj4.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4leaflet/1.0.2/proj4leaflet.min.js"></script>

    <script src="viewer.js?v=v121"></script>
    <div id="version-tag"
        style="display:block; position:fixed; bottom:5px; right:5px; color:rgba(255,255,255,0.03); font-size:8px; z-index:99999; font-family:sans-serif; pointer-events:none;">
        v117</div>
    <script>document.title = document.title.replace(/\[v\d+\]\s*/, '');</script>
    <script src="admin_tools.js?v=5" charset="UTF-8"></script>
    <!-- PWA Install Logic -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker registered!', reg))
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
        }
    </script>
    <!-- Guest info modal (must be at body level to avoid sidebar stacking context) -->
    <div id="guest-info-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:20000; align-items:center; justify-content:center; backdrop-filter:blur(6px); overflow-y:auto;">
        <div
            style="background:#111827; width:90%; max-width:520px; border-radius:14px; border:1px solid #0ea5e9; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.6); font-family:'Outfit',sans-serif; color:#e2e8f0; margin:auto;">
            <h3 style="margin:0 0 16px 0; color:#0ea5e9; font-size:1.2rem;">📍 Legg til nytt punkt</h3>
            <p style="line-height:1.7; font-size:0.95rem; margin-bottom:12px;">
                Har du funnet noe interessant i Skiensmarka? En gammel gruve, en hustuft, en hule eller noe annet
                spennende? Her kan du foreslå et nytt punkt til kartet.
            </p>
            <p style="line-height:1.7; font-size:0.95rem; margin-bottom:12px;">
                <strong>Slik fungerer det:</strong>
            </p>
            <ul style="line-height:1.7; font-size:0.9rem; padding-left:20px; margin-bottom:16px;">
                <li>Du klikker på kartet for å plassere punktet</li>
                <li>Du fyller inn navn, kategori og eventuelt bilder/beskrivelse</li>
                <li>Punktet sendes til administrator for godkjenning</li>
                <li>Etter godkjenning blir det synlig for alle</li>
            </ul>
            <p style="line-height:1.7; font-size:0.85rem; color:#94a3b8; margin-bottom:20px;">
                Ditt navn lagres sammen med punktet som kreditering.
            </p>
            <div style="display:flex; gap:10px;">
                <button onclick="startGuestMode()"
                    style="flex:1; padding:12px; background:linear-gradient(135deg,#0ea5e9,#0284c7); border:none; color:white; border-radius:8px; cursor:pointer; font-weight:700; font-size:0.95rem;">
                    OK, jeg forstår – start
                </button>
                <button onclick="document.getElementById('guest-info-modal').style.display='none'"
                    style="padding:12px 18px; background:rgba(239,68,68,0.1); border:1px solid #ef4444; color:#ef4444; border-radius:8px; cursor:pointer; font-size:0.9rem;">
                    Avbryt
                </button>
            </div>
        </div>
    </div>
</body>

</html>