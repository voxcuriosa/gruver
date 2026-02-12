<?php
session_start();

$DATA_FILE = 'data.json';
$ADMIN_USER = 'Voxcuriosa';
$ADMIN_PASS = '587777';
$COOKIE_NAME = 'vox_admin_token';
$COOKIE_TIME = time() + (86400 * 30); // 30 days

// Check for cookie on load
if (!isset($_SESSION['loggedin']) && isset($_COOKIE[$COOKIE_NAME])) {
    if ($_COOKIE[$COOKIE_NAME] === md5($ADMIN_PASS)) {
        $_SESSION['loggedin'] = true;
    }
}

// Handle Login
if (isset($_POST['login'])) {
    if ($_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
        $_SESSION['loggedin'] = true;

        // Handle Remember Me
        if (isset($_POST['remember'])) {
            setcookie($COOKIE_NAME, md5($ADMIN_PASS), $COOKIE_TIME, "/", "", true, true);
        }
    } else {
        $error = "Feil brukernavn eller passord.";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie($COOKIE_NAME, "", time() - 3600, "/"); // Clear cookie
    header("Location: admin.php");
    exit;
}

// Handle Delete
if (isset($_POST['delete_link']) && isset($_SESSION['loggedin'])) {
    $del_cat = $_POST['delete_category'];
    $del_idx = $_POST['delete_index'];

    if (file_exists($DATA_FILE)) {
        $json = file_get_contents($DATA_FILE);
        $data = json_decode($json, true);

        if (isset($data[$del_cat]) && isset($data[$del_cat][$del_idx])) {
            array_splice($data[$del_cat], $del_idx, 1); // Remove item and re-index
            file_put_contents($DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $success = "Link slettet.";
        }
    }
}

// Handle Add / Edit Logic
if (isset($_POST['add_link']) && isset($_SESSION['loggedin'])) {
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    $edit_idx = $_POST['edit_index']; // Hidden field from form

    if ($title && $url) {
        $json = file_get_contents($DATA_FILE);
        $data = json_decode($json, true);

        if (!isset($data[$category])) {
            $data[$category] = [];
        }

        $newItem = [
            "title" => $title,
            "url" => $url
        ];

        // Check if updating or adding
        if ($edit_idx !== "" && is_numeric($edit_idx)) {
            // Update existing
            $data[$category][$edit_idx] = $newItem;
            $success = "Link oppdatert!";
        } else {
            // Add new
            $data[$category][] = $newItem;
            $success = "Link lagt til!";
        }

        file_put_contents($DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'];

// Fetch latest data for JS Auto-Fill
$current_data = [];
if ($loggedin && file_exists($DATA_FILE)) {
    $current_data = json_decode(file_get_contents($DATA_FILE), true);
}
?>
<!DOCTYPE html>
<html lang="no">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Historienotater</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container,
        .admin-container {
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 400px;
            margin: 50px auto;
            text-align: center;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #444;
            color: #fff;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .remember-me {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            margin: 10px 0;
            color: #ccc;
            font-size: 0.9rem;
        }

        .remember-me input {
            width: auto;
            margin: 0;
        }

        button {
            background: var(--primary);
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
        }

        button:hover {
            opacity: 0.9;
        }

        .msg {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid red;
        }

        .success {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid green;
        }
    </style>
    <style>
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
    <a href="/" class="top-home-link" title="Tilbake til forsiden">⌂ Hjem</a>
    <header>
        <h1>Admin</h1>
        <p class="subtitle">Administrer Notater</p>
    </header>

    <main>
        <?php if (!$loggedin): ?>
            <div class="login-container">
                <?php if (isset($error))
                    echo "<div class='msg error'>$error</div>"; ?>
                <form method="POST">
                    <input type="text" name="username" placeholder="Brukernavn" required>
                    <input type="password" name="password" placeholder="Passord" required>
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Husk meg
                    </label>
                    <button type="submit" name="login">Logg Inn</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-container">
                <?php if (isset($success))
                    echo "<div class='msg success'>$success</div>"; ?>
                <h2 id="form-title">Legg til ny link</h2>
                <form method="POST">
                    <input type="hidden" name="edit_category" id="edit_category">
                    <input type="hidden" name="edit_index" id="edit_index">
                    <select name="category" id="category_select">
                        <option value="VG2">VG2</option>
                        <option value="VG3/Påbygg">VG3/Påbygg</option>
                        <option value="Filmer">Filmer</option>
                    </select>
                    <input type="text" name="title" id="title_input"
                        placeholder="Tittel (f.eks. 'Den Industrielle Revolusjon')" required>
                    <input type="url" name="url" id="url_input" placeholder="Link URL" required>
                    <button type="submit" name="add_link" id="submit_btn">Lagre Link</button>
                    <button type="button" id="cancel_btn" onclick="resetForm()"
                        style="display:none; background:#555; margin-top:5px;">Avbryt</button>
                </form>
                <br>
                <a href="index.php" style="color:var(--primary);">← Tilbake til oversikt</a> |
                <a href="?logout=true" style="color:#aaa;">Logg ut</a>
            </div>

            <script>
                const ALL_DATA = <?php echo json_encode($current_data); ?>;

                function editLink(data) {
                    document.getElementById('form-title').innerText = "Rediger Link";
                    document.getElementById('edit_category').value = data.cat;
                    document.getElementById('edit_index').value = data.idx;

                    document.getElementById('category_select').value = data.cat;
                    document.getElementById('title_input').value = data.title;
                    document.getElementById('url_input').value = data.url;

                    document.getElementById('submit_btn').innerText = "Oppdater Link";
                    document.getElementById('cancel_btn').style.display = "block";

                    window.scrollTo(0, 0);
                }

                function resetForm() {
                    document.getElementById('form-title').innerText = "Legg til ny link";
                    document.getElementById('edit_category').value = "";
                    document.getElementById('edit_index').value = "";

                    document.getElementById('title_input').value = "";
                    document.getElementById('url_input').value = "";

                    document.getElementById('submit_btn').innerText = "Lagre Link";
                    document.getElementById('cancel_btn').style.display = "none";
                }

                // Check for Auto-Edit from Public Page
                window.addEventListener('DOMContentLoaded', () => {
                    const params = new URLSearchParams(window.location.search);
                    if (params.get('auto_edit') === 'true') {
                        const cat = params.get('cat');
                        const idx = params.get('idx');

                        if (ALL_DATA[cat] && ALL_DATA[cat][idx]) {
                            const item = ALL_DATA[cat][idx];
                            editLink({
                                cat: cat,
                                idx: idx,
                                title: item.title,
                                url: item.url
                            });
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </main>
</body>

</html>