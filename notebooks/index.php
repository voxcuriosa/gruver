<?php
session_start();
// Check if logged in via session OR cookie
$DATA_FILE = 'data.json';
$ADMIN_USER = 'Voxcuriosa';
$ADMIN_PASS = '587777';
$COOKIE_NAME = 'vox_admin_token';

// Cookie check same as admin.php to ensure seamless auth
if (!isset($_SESSION['loggedin']) && isset($_COOKIE[$COOKIE_NAME])) {
    if ($_COOKIE[$COOKIE_NAME] === md5($ADMIN_PASS)) {
        $_SESSION['loggedin'] = true;
    }
}

$isAdmin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'];
?>
<!DOCTYPE html>
<html lang="no">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historienotater - VoxCuriosa</title>
    <link rel="stylesheet" href="style.css">
    <script>
        const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    </script>
    <style>
        /* Admin Button Styles */
        .admin-controls {
            display: flex;
            gap: 5px;
            margin-left: 10px;
        }

        .btn-mini {
            padding: 2px 6px;
            font-size: 0.7rem;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ccc;
            background: rgba(0, 0, 0, 0.5);
            transition: all 0.2s;
        }

        .btn-mini:hover {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
        }

        .btn-del:hover {
            background: #ff4444;
            border-color: #ff4444;
        }

        .admin-badge {
            background: var(--primary);
            color: #000;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
            vertical-align: middle;
            margin-left: 10px;
        }

        .top-admin-link {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #555;
            text-decoration: none;
            font-size: 0.8rem;
            opacity: 0.3;
            transition: opacity 0.3s;
            z-index: 100;
        }

        .top-admin-link:hover {
            opacity: 1;
            color: var(--primary);
        }

        .top-home-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #888;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: bold;
            opacity: 0.8;
            transition: all 0.3s;
            z-index: 100;
        }

        .top-home-link:hover {
            opacity: 1;
            color: var(--primary);
        }
    </style>
</head>

<body>
    <!-- Home Link -->
    <a href="/" class="top-home-link" title="Tilbake til forsiden">
        ⌂ Hjem
    </a>

    <!-- Admin Link -->
    <a href="admin.php" class="top-admin-link">
        <?php echo $isAdmin ? 'Admin Panel' : 'π'; ?>
    </a>

    <header>
        <h1>Historienotater
            <?php if ($isAdmin)
                echo '<span class="admin-badge">ADMIN</span>'; ?>
        </h1>
        <p class="subtitle">Samling av NotebookLM notatblokker</p>
    </header>

    <main>
        <div class="columns-container" id="content">
            <!-- Columns will be injected here -->
            <div style="grid-column: 1/-1; text-align: center; padding: 50px; color: #666;">
                Laster notater...
            </div>
        </div>
    </main>

    <footer>
        <!-- Footer empty/clean -->
    </footer>

    <script>
        const COLUMNS = ["VG2", "VG3/Påbygg", "Filmer"];

        async function loadContent() {
            try {
                const response = await fetch('data.json?t=' + new Date().getTime());
                if (!response.ok) throw new Error("Kunne ikke laste data");
                const data = await response.json();
                renderColumns(data);
            } catch (err) {
                console.error(err);
                document.getElementById('content').innerHTML = `
                    <div style="text-align: center; color: #ff5555;">
                        <h2>Noe gikk galt</h2>
                        <p>Kunne ikke laste notater. Prøv igjen senere.</p>
                    </div>`;
            }
        }

        function renderColumns(data) {
            const container = document.getElementById('content');
            container.innerHTML = '';

            COLUMNS.forEach(colName => {
                let items = data[colName] || [];

                // Map to preserve original index, then Sort
                items = items.map((item, idx) => ({ ...item, originalIdx: idx }));
                items.sort((a, b) => a.title.localeCompare(b.title, 'nb', { numeric: true }));

                const colDiv = document.createElement('div');
                colDiv.className = 'column';

                const header = document.createElement('div');
                header.className = 'column-header';
                header.innerHTML = `<h2>${colName}</h2>`;

                const list = document.createElement('ul');
                list.className = 'link-list';

                if (items.length === 0) {
                    list.innerHTML = `<li style="text-align:center; color:#555; padding:10px; font-style:italic;">Ingen notater enda</li>`;
                } else {
                    items.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.className = 'link-item-container'; // Wrapper for flex layout

                        let adminHtml = '';
                        if (IS_ADMIN) {
                            // Find original index in data.json logic is tricky if sorted.
                            // BUT: Admin.php uses data.json array indices. 
                            // Since frontend sorts, the index here does NOT match backend index if unsorted backend.
                            // SOLUTION: We need to rely on the backend to provide IDs or rely on matching title/url.
                            // OR: Quick fix -> Client side sorting is visual only. 
                            // If we want to edit, we need the REAL index.
                            // The simplest way for this "simple PHP app" is to NOT sort on client, 
                            // OR to send the index from backend.
                            // Let's modify data.json to include IDs? No, user wants simple.
                            // Let's assume for now we just link to admin.php and let user find it in the list there.
                            // Wait, user asked: "Kan jeg ikke... se redigeringsmuligheter... på hovedsiden"
                            // If I click "Edit" here, I want to edit THIS item.

                            // Let's pass the Title/URL to admin.php and let it find the index? 
                            // Or, since we only have a few items, we pass match params.
                            // admin.php?action=edit_match&cat=...&title=...

                            // Actually, let's just use the visual sort for now and link to admin list.
                            // Or better: Re-implement sort in PHP later?
                            // For now: Just button causing redirect to admin with auto-fill?

                            // Let's use a "smart" edit link that pre-fills form by matching title.
                            // Use originalIdx for reliable editing/deleting
                            const editUrl = `admin.php?auto_edit=true&cat=${encodeURIComponent(colName)}&idx=${item.originalIdx}`;

                            adminHtml = `
                                <div class="admin-controls">
                                    <a href="${editUrl}" class="btn-mini">✎</a>
                                    
                                    <form method="POST" action="admin.php" onsubmit="return confirm('Er du sikker på at du vil slette «${item.title}»?');" style="margin:0;">
                                        <input type="hidden" name="delete_category" value="${colName}">
                                        <input type="hidden" name="delete_index" value="${item.originalIdx}">
                                        <button type="submit" name="delete_link" class="btn-mini btn-del">🗑</button>
                                    </form>
                                </div>
                            `;
                        }

                        li.innerHTML = `
                            <a href="${item.url}" target="_blank" class="link-item" style="flex:1;">
                                <span class="link-title">${item.title}</span>
                                <span class="link-icon">↗</span>
                            </a>
                            ${adminHtml}
                        `;
                        // Adjust styling for container
                        li.style.display = 'flex';
                        li.style.alignItems = 'center';
                        li.style.marginBottom = '10px';

                        list.appendChild(li);
                    });
                }

                colDiv.appendChild(header);
                colDiv.appendChild(list);
                container.appendChild(colDiv);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadContent();

            // --- VISIT LOGGING ---
            const payload = {
                action: 'log_visit',
                app: 'notebooks',
                device: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ? 'Mobile' : 'PC',
                screen_resolution: window.screen.width + "x" + window.screen.height,
                referrer: document.referrer || 'Direct',
                language: navigator.language || 'en'
            };

            fetch('../history/auth_v2.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).catch(err => console.log("Analytics skipped."));
        });
    </script>
</body>

</html>