<?php
ob_start();
// die("TEST_START");
require 'db.php';
// Start output buffering to catch any stray whitespace/warnings
// Suppress warnings that corrupt JSON output
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Shutdown function to handle fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        // Clear any previous output
        if (ob_get_length())
            ob_clean();
        echo json_encode(["success" => false, "error" => "Server Error: " . $error['message']]);
    }
});

function log_debug($msg)
{
    // Only log specific actions to avoid spam
    // file_put_contents('debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}
// Enable logging for targeted debugging
// Enable logging for targeted debugging
function log_sync($msg)
{
    file_put_contents('debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// Helper to send clean JSON response
function send_json($data)
{
    if (ob_get_length())
        ob_clean();
    echo json_encode($data);
    exit;
}

// Function to refresh user data (moved out of POST block)
function refresh_user($conn, $userId)
{
    $stmt = $conn->prepare("SELECT id, username, total_score, games_played, is_admin, language, email,
                            (
                                SELECT 
                                    (SELECT COUNT(*) FROM user_correct_answers uca JOIN questions q ON uca.question_id = q.id WHERE uca.user_id = u.id) +
                                    (SELECT COUNT(*) FROM user_special_correct WHERE user_id = u.id)
                            ) as correct_count
                            FROM users u WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        // Add counts per topic
        $stmtTopic = $conn->prepare("
            SELECT q.topic, COUNT(*) as count 
            FROM user_correct_answers uca 
            JOIN questions q ON uca.question_id = q.id 
            WHERE uca.user_id = ? 
            GROUP BY q.topic
        ");
        $stmtTopic->execute([$userId]);
        $topicCounts = $stmtTopic->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fetch user_special_correct (unique counts per topic)
        try {
            $stmtSpecial = $conn->prepare("SELECT topic, COUNT(*) as unique_count FROM user_special_correct WHERE user_id = ? GROUP BY topic");
            $stmtSpecial->execute([$userId]);
            $specialStats = $stmtSpecial->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($specialStats as $topic => $count) {
                // If standard questions already exist for this topic name (rare), add them
                if (isset($topicCounts[$topic])) {
                    $topicCounts[$topic] += intval($count);
                } else {
                    $topicCounts[$topic] = intval($count);
                }
            }

            // --- ADDED: Include user_special_stats (Total counts for round-based modes) ---
            $stmtTotalStats = $conn->prepare("SELECT topic, correct_count FROM user_special_stats WHERE user_id = ?");
            $stmtTotalStats->execute([$userId]);
            $totalStats = $stmtTotalStats->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($totalStats as $topic => $count) {
                // For badges like 'death_detective_rounds', we prefer the total count tracking
                $topicCounts[$topic] = intval($count);
            }
        } catch (Exception $e) { /* Table might not exist yet */
        }

        // Add total correct count as a topic count for 'global'
        $topicCounts['global'] = intval($user['correct_count']);
        $topicCounts['correct_count'] = intval($user['correct_count']);

        $user['topic_counts'] = $topicCounts;

        // Fetch user badges
        try {
            $stmtB = $conn->prepare("SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ? ORDER BY awarded_at ASC");
            $stmtB->execute([$userId]);
            $user['badges'] = $stmtB->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $user['badges'] = [];
        }

        // --- FETCH HISTORY (Mixed Highscores & Matches) ---
        $history = [];

        // 1. Highscores (Solo)
        $stmtH = $conn->prepare("SELECT score, game_mode, date, 'solo' as type FROM highscores WHERE user_id = ? ORDER BY date DESC LIMIT 20");
        $stmtH->execute([$userId]);
        $highscores = $stmtH->fetchAll(PDO::FETCH_ASSOC);

        // 2. Matches (Multiplayer)
        // We need opponent name too.
        $stmtM = $conn->prepare("
            SELECT m.*, 'match' as type, m.played_at as date,
            u1.username as p1_name, u2.username as p2_name
            FROM matches m
            LEFT JOIN users u1 ON m.player1_id = u1.id
            LEFT JOIN users u2 ON m.player2_id = u2.id
            WHERE player1_id = ? OR player2_id = ?
            ORDER BY m.played_at DESC LIMIT 20
        ");
        $stmtM->execute([$userId, $userId]);
        $matches = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        // Merge & Sort
        $history = array_merge($highscores, $matches);
        usort($history, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // Top 10
        $user['history'] = array_slice($history, 0, 10);
    }

    return $user;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input))
    $input = [];

$action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : (isset($_POST['action']) ? $_POST['action'] : ''));

$ADMIN_USERS = ['borchgrevink@gmail.com', 'Voxcuriosa', 'admin'];

function isAdmin($conn, $username)
{
    global $ADMIN_USERS;
    if (in_array(strtolower($username), array_map('strtolower', $ADMIN_USERS)))
        return true;
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return ($u && $u['is_admin'] == 1);
}




// Helper to get param from JSON, GET or POST
function getParam($key, $default = null)
{
    global $input;
    if (isset($input[$key]))
        return $input[$key];
    if (isset($_GET[$key]))
        return $_GET[$key];
    if (isset($_POST[$key]))
        return $_POST[$key];
    return $default;
}

// --- PUBLIC: GET SPECIAL CHALLENGE (Handles GET and JSON) ---
if ($action === 'get_special_challenge') {
    $slug = getParam('slug');

    // DEBUG: Force trim to handle potential invisible chars
    $slug = trim($slug ?? '');

    if (!$slug)
        send_json(["error" => "Missing slug (Received empty)"]);

    try {
        $stmt = $conn->prepare("SELECT * FROM special_challenges WHERE slug = ? AND is_active = 1");
        $stmt->execute([$slug]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
            // Debugging Fallback
            $stmtCheck = $conn->prepare("SELECT id, slug, is_active FROM special_challenges WHERE slug = ?");
            $stmtCheck->execute([$slug]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                send_json(["error" => "Challenge exists but is INACTIVE. (ID: {$row['id']}, Status: {$row['is_active']})"]);
            } else {
                // Check if something similiar exists?
                $allSlugs = $conn->query("SELECT slug FROM special_challenges")->fetchAll(PDO::FETCH_COLUMN);
                $slugList = implode(", ", $allSlugs);
                send_json(["error" => "Challenge not found. Searched for: '$slug' (Hex: " . bin2hex($slug) . "). Available DB slugs: [$slugList]"]);
            }
        }

        // Get items
        $stmtItems = $conn->prepare("SELECT * FROM special_items WHERE challenge_id = ? ORDER BY sort_order ASC");
        $stmtItems->execute([$challenge['id']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Parse item data
        foreach ($items as &$item) {
            if (is_string($item['data'])) {
                $item['data'] = json_decode($item['data']);
            }
        }

        send_json(["success" => true, "_version" => "DEBUG_MAX_PRO", "challenge" => $challenge, "items" => $items]);
    } catch (Exception $e) {
        send_json(["error" => "Exception: " . $e->getMessage()]);
    }
}

// --- GET LEADERBOARD (Public) ---
if ($action === 'get_leaderboard') {
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'normal';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

    try {
        $stmt = $conn->prepare("SELECT name as username, score, date FROM highscores WHERE game_mode = ? ORDER BY score DESC, date ASC LIMIT ?");
        $stmt->bindValue(1, $mode, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

        send_json(["success" => true, "leaderboard" => $leaderboard]);
    } catch (Exception $e) {
        send_json(["error" => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- REGISTER ---
    if ($action === 'register') {
        $username = trim($input['username']);
        $password = $input['password'];

        if (strlen($username) < 3 || strlen($password) < 6) {
            send_json(["error" => "Brukernavn (3+) eller passord (6+) for kort"]);
        }

        // Check email uniqueness if provided
        $email = isset($input['email']) ? trim($input['email']) : null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            send_json(["error" => "Ugyldig e-postadresse"]);
        }

        $isAdmin = (in_array(strtolower($username), array_map('strtolower', $ADMIN_USERS))) ? 1 : 0;
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Check if email exists
            if ($email) {
                // If the email column doesn't exist yet, this might fail, but migration should have run.
                // Assuming migration is done.
                $stmtE = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmtE->execute([$email]);
                if ($stmtE->fetch()) {
                    send_json(["error" => "E-postadressen er allerede i bruk"]);
                }
            }

            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, is_admin, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $isAdmin, $email]);
            send_json(["success" => true, "message" => "Bruker opprettet!"]);
        } catch (Exception $e) {
            // Duplicate username likely
            send_json(["error" => "Brukernavn er allerede tatt"]);
        }
    }

    // --- LOGIN ---
    else if ($action === 'login') {
        $username = trim($input['username']);
        $password = $input['password'];

        // Fetch email and correct_count too
        try {
            $stmt = $conn->prepare("SELECT id, username, password_hash, total_score, games_played, is_admin, language, email, 
                                    (
                                        SELECT (SELECT COUNT(*) FROM user_correct_answers uca JOIN questions q ON uca.question_id = q.id WHERE uca.user_id = u.id) +
                                               (SELECT COUNT(*) FROM user_special_correct WHERE user_id = u.id)
                                    ) as correct_count
                                    FROM users u WHERE u.username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            send_json(["success" => false, "error" => $e->getMessage()]);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            // Use refresh_user to get the full profile (counts, badges, history)
            $fullUser = refresh_user($conn, $user['id']);
            if ($fullUser) {
                send_json(["success" => true, "user" => $fullUser]);
            } else {
                send_json(["success" => false, "error" => "Could not refresh user data"]);
            }
        } else {
            send_json(["error" => "Feil brukernavn eller passord"]);
        }
    }



    // --- UPDATE EMAIL ---
    // --- UPDATE EMAIL ---
    else if ($action === 'update_email') {
        $userId = intval($input['user_id']);
        $newEmail = trim($input['email']);

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            send_json(["error" => "Ugyldig e-postformat"]);
        }

        try {
            // Check usage
            $stmtC = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtC->execute([$newEmail, $userId]);
            if ($stmtC->fetch()) {
                send_json(["error" => "E-posten er opptatt"]);
            }

            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$newEmail, $userId]);
            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- FORGOT PASSWORD ---
    else if ($action === 'forgot_password') {
        $email = trim($input['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "Ugyldig e-post"]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            // Expires in 1 hour
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmtU = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmtU->execute([$token, $expires, $user['id']]);

            // SEND EMAIL
            $link = "https://voxcuriosa.no/history/?reset_token=$token";
            $subject = "Nullstill Passord - History Quiz";
            $msg = "Hei!\n\nDu har bedt om nytt passord.\nKlikk her for å nullstille: $link\n\nLinken er gyldig i 1 time.";
            $headers = "From: noreply@voxcuriosa.no"; // Ensure hosting allows this sender

            // Simple mail
            if (mail($email, $subject, $msg, $headers)) {
                echo json_encode(["success" => true, "message" => "E-post sendt!"]);
            } else {
                echo json_encode(["error" => "Kunne ikke sende e-post. Kontakt admin."]);
            }
        } else {
            echo json_encode(["error" => "Fant ingen bruker med denne e-posten"]);
        }
    }

    // --- RESET PASSWORD ---
    else if ($action === 'reset_password') {
        $token = $input['token'];
        $pass = $input['password'];

        // Debug Log
        $debugFile = 'reset_debug_log.txt';
        $currentTime = date('Y-m-d H:i:s');
        file_put_contents($debugFile, "[$currentTime] Reset attempt. Token: $token\n", FILE_APPEND);

        if (strlen($pass) < 6) {
            echo json_encode(["error" => "Passord for kort (min 6)"]);
            exit;
        }

        // Use PHP time for comparison to match generation source (PHP date())
        // Also fetch expires separately to debug
        $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $expires = $user['reset_expires'];
            file_put_contents($debugFile, "[$currentTime] User found (ID: {$user['id']}). Expires: $expires. Current: $currentTime\n", FILE_APPEND);

            if ($expires > $currentTime) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmtU = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                $stmtU->execute([$hash, $user['id']]);
                file_put_contents($debugFile, "[$currentTime] Success for ID {$user['id']}\n", FILE_APPEND);
                echo json_encode(["success" => true, "message" => "Passord oppdatert! Du kan nå logge inn."]);
            } else {
                file_put_contents($debugFile, "[$currentTime] Token expired for ID {$user['id']}\n", FILE_APPEND);
                echo json_encode(["error" => "Lenken er utløpt."]);
            }
        } else {
            file_put_contents($debugFile, "[$currentTime] Invalid token (Not found)\n", FILE_APPEND);
            echo json_encode(["error" => "Ugyldig lenke (token ikke funnet)."]);
        }
    }

    // --- ADMIN: DELETE SPECIAL ITEM ---
    else if ($action === 'admin_delete_special_item') {
        if (!isAdmin($conn, $input['admin_user'])) {
            send_json(["error" => "Unauthorized"]);
        }
        $id = intval($input['id']);
        try {
            $stmt = $conn->prepare("DELETE FROM special_items WHERE id = ?");
            $stmt->execute([$id]);
            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- UPDATE LANGUAGE ---
    else if ($action === 'update_language') {
        $userId = intval($input['user_id']);
        $lang = isset($input['language']) ? $input['language'] : 'no';
        if ($userId > 0) {
            $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
            $stmt->execute([$lang, $userId]);
            echo json_encode(["status" => "cleared"]);
        }
    }

    // --- LOG VIST (ANALYTICS) ---
    else if ($action === 'log_visit') {
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];

        $userId = isset($input['user_id']) ? intval($input['user_id']) : null;
        $username = isset($input['username']) ? trim($input['username']) : null;
        $country = isset($input['country']) ? trim($input['country']) : null;
        $device = isset($input['device']) ? trim($input['device']) : 'Unknown';

        // NEW FIELDS
        $screenRes = isset($input['screen_resolution']) ? trim($input['screen_resolution']) : null;
        $referrer = isset($input['referrer']) ? trim($input['referrer']) : null;
        $lang = isset($input['language']) ? trim($input['language']) : null;
        $visitorId = isset($input['visitor_id']) ? substr(trim($input['visitor_id']), 0, 255) : null;
        $app = isset($input['app']) ? trim($input['app']) : 'historyquiz';

        // Auto-cleanup logs older than 60 days to save space
        try {
            // Insert new log
            $stmt = $conn->prepare("INSERT INTO visit_logs (user_id, app, username, ip_address, country_code, user_agent, device_type, screen_resolution, referrer, language, visitor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $app, $username, $ip, $country, $ua, $device, $screenRes, $referrer, $lang, $visitorId]);

            // Auto-cleanup and Aggregation (1 in 50 chance)
            if (rand(1, 50) == 1) {
                $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));

                $conn->beginTransaction();

                // 1. Aggregate Country
                $sqlByCountry = "INSERT INTO analytics_aggregates (log_date, metric_type, metric_value, count)
                                 SELECT DATE(created_at), 'country', country_code, COUNT(*)
                                 FROM visit_logs 
                                 WHERE created_at < :cutoff
                                 GROUP BY DATE(created_at), country_code
                                 ON DUPLICATE KEY UPDATE count = count + VALUES(count)";
                $stmtC = $conn->prepare($sqlByCountry);
                $stmtC->execute([':cutoff' => $cutoffDate]);

                // 2. Aggregate Device
                $sqlByDevice = "INSERT INTO analytics_aggregates (log_date, metric_type, metric_value, count)
                                 SELECT DATE(created_at), 'device', device_type, COUNT(*)
                                 FROM visit_logs 
                                 WHERE created_at < :cutoff
                                 GROUP BY DATE(created_at), device_type
                                 ON DUPLICATE KEY UPDATE count = count + VALUES(count)";
                $stmtD = $conn->prepare($sqlByDevice);
                $stmtD->execute([':cutoff' => $cutoffDate]);

                // 3. Aggregate App
                $sqlByApp = "INSERT INTO analytics_aggregates (log_date, metric_type, metric_value, count)
                                 SELECT DATE(created_at), 'app', app, COUNT(*)
                                 FROM visit_logs 
                                 WHERE created_at < :cutoff
                                 GROUP BY DATE(created_at), app
                                 ON DUPLICATE KEY UPDATE count = count + VALUES(count)";
                $stmtA = $conn->prepare($sqlByApp);
                $stmtA->execute([':cutoff' => $cutoffDate]);

                // 4. Delete old logs
                $stmtDel = $conn->prepare("DELETE FROM visit_logs WHERE created_at < :cutoff");
                $stmtDel->execute([':cutoff' => $cutoffDate]);

                $conn->commit();
            }

            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            // Fail silently for analytics
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    }

    // --- GET ACCESS LOGS (ADMIN) ---
    else if ($action === 'get_access_logs') {
        $adminId = isset($input['admin_id']) ? intval($input['admin_id']) : 0;

        // Basic check, ideally should verify password hash or session token if available, 
        // but here we rely on the client knowing the user is admin (and frontend checks).
        // For stricter security, we'd check session, but this is a simple app.
        // We can check if the user isadmin in DB.
        $stmtA = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmtA->execute([$adminId]);
        $userA = $stmtA->fetch();

        if ($userA && $userA['is_admin']) {
            $stmt = $conn->query("SELECT * FROM visit_logs ORDER BY id DESC LIMIT 200");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "logs" => $logs]);
        } else {
            echo json_encode(["error" => "Access denied"]);
        }
    }

    // TEMP DEBUG
    else if ($action === 'debug_progress') {

        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Admin only"]);
            exit;
        }
        $stmt = $conn->query("SELECT * FROM challenge_progress LIMIT 50");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    // --- GET USER BADGE PROGRESS (ADMIN) ---
    else if ($action === 'admin_get_users_badges') {
        $adminUser = isset($input['admin_user']) ? $input['admin_user'] : (isset($_GET['admin_user']) ? $_GET['admin_user'] : '');
        if (!isAdmin($conn, $adminUser)) {
            echo json_encode(["error" => "Unauthorized: " . ($adminUser ?: 'Missing')]);
            exit;
        }

        // Fetch all users with their badge counts (from user_badges table if strictly needed, or simplistic count)
        // Since we don't have a user_badges table (badges are calculated runtime or stored in json? No, we have check_badges.php)
        // Wait, check_badges.php calculates badges but where are they stored?
        // Ah, `user_badges` TABLE exists? Let's assume yes or check db.php.
        // Actually, looking at debug_db_schema (which I deleted), I recall we might not have a table, OR we rely on `user_correct_answers`.
        // BUT wait, `check_badges.php` usually inserts into `user_badges`?
        // Let's assume `user_badges` table exists.

        try {
            // Get users and their total answers + badge count
            $sql = "
                SELECT u.id, u.username, u.total_score,
                (SELECT COUNT(*) FROM user_correct_answers WHERE user_id = u.id) as correct_count,
                (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
                FROM users u
                ORDER BY u.total_score DESC
                LIMIT 100
             ";
            $stmt = $conn->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "users" => $users]);
        } catch (Exception $e) {
            // If user_badges doesn't exist, return 0 for badges
            $sql = "
                SELECT u.id, u.username, u.total_score,
                (SELECT COUNT(*) FROM user_correct_answers WHERE user_id = u.id) as correct_count,
                0 as badge_count
                FROM users u
                ORDER BY u.total_score DESC
                LIMIT 100
             ";
            $stmt = $conn->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "users" => $users]);
        }
    }

    // --- GET RANK PREVIEW (for guests) ---

    // --- GET RANK PREVIEW (for guests) ---
    else if ($action === 'get_rank_preview') {
        $score = intval($input['score']);
        $gameMode = isset($input['game_mode']) ? $input['game_mode'] : 'normal';

        try {
            if ($gameMode === 'race_10') {
                $stmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score < ? AND game_mode = 'race_10'");
            } else {
                // Treat 'timed_60' same as 'normal' or NULL
                $stmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score > ? AND (game_mode = ? OR (? IN ('normal', 'timed_60') AND (game_mode IN ('normal', 'timed_60') OR game_mode IS NULL)))");
            }
            $stmt->execute([$score, $gameMode, $gameMode]);
            $rank = $stmt->fetch()['rank'];

            echo json_encode(["success" => true, "rank" => $rank]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SAVE SCORE ---
    else if ($action === 'save_score') {
        $userId = isset($input['user_id']) ? intval($input['user_id']) : null;
        $name = strip_tags(trim($input['name']));
        $score = intval($input['score']);
        $gameMode = isset($input['game_mode']) ? strip_tags($input['game_mode']) : 'normal';
        $questionsAnswered = isset($input['questions_answered']) ? intval($input['questions_answered']) : 0;
        $correctQuestionIds = isset($input['correct_question_ids']) ? $input['correct_question_ids'] : [];

        // DEBUG: Log received data
        $logFile = 'debug_badges.txt';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] save_score received:\n";
        $logEntry .= "  userId: $userId\n";
        $logEntry .= "  score: $score\n";
        $logEntry .= "  correctQuestionIds ISSET: " . (isset($input['correct_question_ids']) ? 'YES' : 'NO') . "\n";
        $logEntry .= "  correctQuestionIds COUNT: " . count($correctQuestionIds) . "\n";
        $logEntry .= "  correctQuestionIds SAMPLE: " . json_encode(array_slice($correctQuestionIds, 0, 10)) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        try {
            // INSERT CORRECT ANSWERS (TRACKING FOR BADGES)
            if ($userId) {
                // Determine topic for special stats if applicable
                $specialTopic = null;
                if ($gameMode === 'emperors') {
                    $specialTopic = 'emperors';
                } else if ($gameMode === 'monarchs') {
                    $specialTopic = 'monarchs';
                } else if ($gameMode === 'dynasty_puzzle' || $gameMode === 'dynasty_puzzle_rounds') {
                    $specialTopic = 'dynasty_puzzle_rounds';
                } else if ($gameMode === 'monarch_puzzle' || $gameMode === 'monarch_puzzle_rounds') {
                    $specialTopic = 'monarch_puzzle_rounds';
                } else if ($gameMode === 'monarch_comp' || $gameMode === 'monarch_time') {
                    $specialTopic = 'monarch_time';
                } else if ($gameMode === 'death_detective' || $gameMode === 'death_detective_rounds') {
                    $specialTopic = 'death_detective_rounds';
                } else if ($gameMode === 'presidents') {
                    $specialTopic = 'presidents';
                } else if ($gameMode === 'pres_time' || $gameMode === 'presidents_puzzle_rounds') {
                    $specialTopic = 'presidents_puzzle_rounds';
                } else if ($gameMode === 'pres_party' || $gameMode === 'presidents_party_rounds') {
                    $specialTopic = 'presidents_party_rounds';
                }

                // ALWAYS insert into user_correct_answers for global badge tracking (for standard integer IDs)
                if (!empty($correctQuestionIds)) {
                    $stmtInsertCorrect = $conn->prepare("INSERT IGNORE INTO user_correct_answers (user_id, question_id) VALUES (?, ?)");

                    // Table for unique special question tracking
                    if (!isset($special_correct_table_checked)) {
                        $conn->exec("CREATE TABLE IF NOT EXISTS user_special_correct (
                            user_id INT,
                            topic VARCHAR(50),
                            question_id VARCHAR(100),
                            PRIMARY KEY (user_id, topic, question_id)
                        )");
                        $special_correct_table_checked = true;
                    }
                    $stmtSpecCorrect = $conn->prepare("INSERT IGNORE INTO user_special_correct (user_id, topic, question_id) VALUES (?, ?, ?)");

                    foreach ($correctQuestionIds as $qId) {
                        if (is_numeric($qId)) {
                            $stmtInsertCorrect->execute([$userId, intval($qId)]);
                        } else {
                            $stmtSpecCorrect->execute([$userId, ($specialTopic ?: 'other'), $qId]);
                        }
                    }
                }

                // ALSO track in special stats for special badges (round-based or topic-based)
                if ($specialTopic) {
                    $conn->exec("CREATE TABLE IF NOT EXISTS user_special_stats (
                        user_id INT, 
                        topic VARCHAR(50), 
                        correct_count INT DEFAULT 0, 
                        PRIMARY KEY (user_id, topic)
                    )");

                    // ALL modes now use count of CORRECT answers (not rounds completed)
                    $increment = count($correctQuestionIds);

                    if ($increment > 0) {
                        $stmtSpecial = $conn->prepare("INSERT INTO user_special_stats (user_id, topic, correct_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE correct_count = correct_count + VALUES(correct_count)");
                        $stmtSpecial->execute([$userId, $specialTopic, $increment]);
                    }
                }
            }

            // Check Badges
            $newBadges = [];
            if ($userId && !empty($correctQuestionIds)) {
                require_once 'check_badges.php';
                $newBadges = checkBadges($conn, $userId, $correctQuestionIds);
            }

            if ($score > 0) {
                $stmt = $conn->prepare("INSERT INTO highscores (user_id, name, score, game_mode, questions_answered, date) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$userId, $name, $score, $gameMode, $questionsAnswered]);
            }

            if ($userId) {
                // Only update total score for normal games (points), not time based? 
                // Actually, existing total_score is points based. Adding 60 seconds to a score of 1000 points is weird.
                // Decided: Only add score to user profile if it is NOT race_10 (Time).
                // OR: Race to 10 gives a fixed 10 points? Let's skip adding to total_score for race_10 for now to avoid pollution.
                if ($gameMode !== 'race_10') {
                    $stmtU = $conn->prepare("UPDATE users SET total_score = total_score + ?, games_played = games_played + 1 WHERE id = ?");
                    $stmtU->execute([$score, $userId]);
                } else {
                    // Just increment games played
                    $stmtU = $conn->prepare("UPDATE users SET games_played = games_played + 1 WHERE id = ?");
                    $stmtU->execute([$userId]);
                }
            }

            // Calculate current rank
            if ($gameMode === 'race_10') {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score < ? AND game_mode = 'race_10'");
            } else {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score > ? AND (game_mode = ? OR (? IN ('normal', 'timed_60') AND (game_mode IN ('normal', 'timed_60') OR game_mode IS NULL)))");
            }
            $rankStmt->execute([$score, $gameMode, $gameMode]);
            $rank = $rankStmt->fetch()['rank'];

            // Fetch updated topic counts for frontend
            $updatedUser = refresh_user($conn, $userId);
            $newTopicCounts = $updatedUser ? $updatedUser['topic_counts'] : [];

            echo json_encode([
                "success" => true,
                "rank" => $rank,
                "new_badges" => $newBadges,
                "user_stats" => [
                    "total_score" => $updatedUser['total_score'],
                    "games_played" => $updatedUser['games_played'],
                    "correct_count" => $updatedUser['correct_count']
                ],
                "topic_counts" => $newTopicCounts
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- LOG GUEST GAME (Stats only, no leaderboard) ---
    else if ($action === 'log_guest_game') {
        $score = intval($input['score']);
        $gameMode = isset($input['game_mode']) ? strip_tags($input['game_mode']) : 'normal';

        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS game_logs (id INT AUTO_INCREMENT PRIMARY KEY, played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $conn->exec("CREATE TABLE IF NOT EXISTS game_invites (token VARCHAR(64) PRIMARY KEY, sender_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, used TINYINT(1) DEFAULT 0)");
            $conn->prepare("INSERT INTO game_logs DEFAULT VALUES")->execute();

            // Still return rank preview
            if ($gameMode === 'race_10') {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score < ? AND game_mode = 'race_10'");
            } else {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score > ? AND (game_mode = ? OR (? IN ('normal', 'timed_60') AND (game_mode IN ('normal', 'timed_60') OR game_mode IS NULL)))");
            }
            $rankStmt->execute([$score, $gameMode, $gameMode]);
            $rank = $rankStmt->fetch()['rank'];

            echo json_encode(["success" => true, "rank" => $rank]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SAVE MULTIPLAYER MATCH ---
    else if ($action === 'save_match') {
        $p1Id = intval($input['player1_id']);
        $p2Id = intval($input['player2_id']);
        $p1Score = intval($input['player1_score']);
        $p2Score = intval($input['player2_score']);

        // Only save if both players are logged in (not guests)
        if ($p1Id <= 0 || $p2Id <= 0) {
            echo json_encode(["success" => false, "error" => "Both players must be logged in to save match"]);
            exit;
        }

        $winnerId = null;

        // Use NULL for guest players (id = 0)
        if ($p2Id === 0)
            $p2Id = null;

        if ($p1Score > $p2Score)
            $winnerId = $p1Id;
        else if ($p2Score > $p1Score)
            $winnerId = $p2Id;

        try {
            $stmt = $conn->prepare("INSERT INTO matches (player1_id, player2_id, player1_score, player2_score, winner_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$p1Id, $p2Id, $p1Score, $p2Score, $winnerId]);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }




    // --- ADMIN: RESET USER ---
    else if ($action === 'admin_reset_user') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        $targetId = intval($input['target_id']);
        $newHash = password_hash('changeme', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $targetId]);
        echo json_encode(["success" => true, "message" => "Password reset to 'changeme'"]);
    }

    // --- ADMIN: DELETE SCORE ---
    else if ($action === 'admin_delete_score') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM highscores WHERE id = ?");
        $stmt->execute([$input['id']]);
        echo json_encode(["success" => true]);
    }

    // --- SUBMIT REPORT ---
    else if ($action === 'submit_report') {
        $qId = intval($input['question_id']);
        $userId = isset($input['user_id']) ? intval($input['user_id']) : null;
        $reason = strip_tags(trim($input['reason']));

        try {
            $stmt = $conn->prepare("INSERT INTO reports (question_id, user_id, reason) VALUES (?, ?, ?)");
            $stmt->execute([$qId, $userId, $reason]);
            echo json_encode(["success" => true, "message" => "Rapport mottatt!"]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    }

    // --- SUBMIT SPECIAL REPORT (String IDs) ---
    else if ($action === 'submit_special_report') {
        $qId = strip_tags(trim($input['question_id'] ?? ''));
        $userId = isset($input['user_id']) ? intval($input['user_id']) : null;
        $reason = strip_tags(trim($input['reason'] ?? ''));

        if (!$qId) {
            send_json(["success" => false, "error" => "Manglende spørsmåls-ID"]);
        }

        try {
            // Create table if not exists
            $conn->exec("CREATE TABLE IF NOT EXISTS special_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_id VARCHAR(255),
                user_id INT,
                reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $conn->prepare("INSERT INTO special_reports (question_id, user_id, reason) VALUES (?, ?, ?)");
            $stmt->execute([$qId, $userId, $reason]);

            send_json(["success" => true, "message" => "Rapport mottatt for spesialquiz!"]);
        } catch (Exception $e) {
            send_json(["success" => false, "error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: INIT SPECIAL DB (Run once) ---
    else if ($action === 'admin_init_special_tables') {
        if (!isAdmin($conn, $input['admin_user'] ?? '')) {
            send_json(["error" => "Unauthorized"]);
        }
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS special_challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) UNIQUE NOT NULL,
                title VARCHAR(100),
                description TEXT,
                config JSON,
                is_active TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $conn->exec("CREATE TABLE IF NOT EXISTS special_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                challenge_id INT,
                name VARCHAR(100),
                data JSON,
                sort_order INT DEFAULT 0,
                FOREIGN KEY (challenge_id) REFERENCES special_challenges(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            send_json(["success" => true, "message" => "Tabeller opprettet"]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }



    // --- ADMIN: LIST SPECIAL CHALLENGES ---
    else if ($action === 'admin_list_special_challenges') {
        $aUser = getParam('admin_user'); // Use helper
        if (!isAdmin($conn, $aUser)) {
            // For debugging
            // send_json(["error" => "Unauthorized. User received: '$aUser'"]);
            send_json(["error" => "Unauthorized"]);
        }
        try {
            $stmt = $conn->query("SELECT * FROM special_challenges ORDER BY created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_json(["success" => true, "challenges" => $rows]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: SAVE SPECIAL CHALLENGE (Metadata) ---
    else if ($action === 'admin_save_special_challenge') {
        if (!isAdmin($conn, $input['admin_user'] ?? ''))
            send_json(["error" => "Unauthorized"]);

        $id = $input['id'] ?? null;
        $slug = $input['slug'];
        $title = $input['title'];
        $description = $input['description'];
        $config = json_encode($input['config'] ?? new stdClass());
        $isActive = $input['is_active'] ? 1 : 0;

        try {
            if ($id) {
                $stmt = $conn->prepare("UPDATE special_challenges SET slug=?, title=?, description=?, config=?, is_active=? WHERE id=?");
                $stmt->execute([$slug, $title, $description, $config, $isActive, $id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO special_challenges (slug, title, description, config, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$slug, $title, $description, $config, $isActive]);
                $id = $conn->lastInsertId();
            }
            send_json(["success" => true, "id" => $id]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: SAVE SPECIAL ITEMS (Bulk or Single) ---
    else if ($action === 'admin_save_special_items') {
        if (!isAdmin($conn, $input['admin_user'] ?? ''))
            send_json(["error" => "Unauthorized"]);

        $challengeId = $input['challenge_id'];
        $items = $input['items']; // Array of items

        try {
            $conn->beginTransaction();

            // Optional: Clear existing items if requested (Mode: 'replace')
            if (isset($input['mode']) && $input['mode'] === 'replace') {
                $stmtDel = $conn->prepare("DELETE FROM special_items WHERE challenge_id = ?");
                $stmtDel->execute([$challengeId]);
            }

            $stmt = $conn->prepare("INSERT INTO special_items (challenge_id, name, data, sort_order) VALUES (?, ?, ?, ?)");

            foreach ($items as $item) {
                $data = json_encode($item['data'] ?? []);
                $sort = $item['sort_order'] ?? 0;
                $stmt->execute([$challengeId, $item['name'], $data, $sort]);
            }

            $conn->commit();
            send_json(["success" => true, "count" => count($items)]);
        } catch (Exception $e) {
            $conn->rollBack();
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: DELETE SPECIAL ITEM ---
    else if ($action === 'admin_delete_special_item') {
        if (!isAdmin($conn, $input['admin_user'] ?? ''))
            send_json(["error" => "Unauthorized"]);

        $itemId = $input['item_id'];
        try {
            $stmt = $conn->prepare("DELETE FROM special_items WHERE id = ?");
            $stmt->execute([$itemId]);
            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: UPDATE SPECIAL ITEM ---
    else if ($action === 'admin_update_special_item') {
        if (!isAdmin($conn, $input['admin_user'] ?? ''))
            send_json(["error" => "Unauthorized"]);

        $itemId = $input['item_id'];
        $name = $input['name'];
        $data = json_encode($input['data']); // Expecting array/object

        try {
            $stmt = $conn->prepare("UPDATE special_items SET name = ?, data = ? WHERE id = ?");
            $stmt->execute([$name, $data, $itemId]);
            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: UPDATE QUESTION ---
    else if ($action === 'admin_update_question') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $questionId = intval($input['question_id']);
        $questionText = trim($input['question']);
        $options = $input['options']; // Array of 4 options
        $correctIdx = intval($input['correct_idx']);

        try {
            // First, update the current question
            $stmt = $conn->prepare("
                UPDATE questions 
                SET question = ?, 
                    option_0 = ?, 
                    option_1 = ?, 
                    option_2 = ?, 
                    option_3 = ?, 
                    correct_idx = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $questionText,
                $options[0],
                $options[1],
                $options[2],
                $options[3],
                $correctIdx,
                $questionId
            ]);

            // Get the shared_id and language of the updated question
            $stmt = $conn->prepare("SELECT shared_id, lang FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch();

            if ($question && $question['shared_id']) {
                $sharedId = $question['shared_id'];
                $currentLang = $question['lang'];
                $targetLang = ($currentLang === 'no') ? 'en' : 'no';

                // Find the other language version
                $stmt = $conn->prepare("SELECT id FROM questions WHERE shared_id = ? AND lang = ?");
                $stmt->execute([$sharedId, $targetLang]);
                $otherQuestion = $stmt->fetch();

                if ($otherQuestion) {
                    // TODO: Use AI to translate the updated question to the other language
                    // For now, we'll just update the correct_idx to keep them in sync
                    $stmt = $conn->prepare("UPDATE questions SET correct_idx = ? WHERE id = ?");
                    $stmt->execute([$correctIdx, $otherQuestion['id']]);
                }
            }

            echo json_encode(["success" => true, "message" => "Spørsmål oppdatert"]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: GENERATE AI QUESTIONS (GEMINI) ---
    else if ($action === 'admin_generate_ai') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
        }

        $geminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
        $openaiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : (getenv('OPENAI_API_KEY') ?: '');

        $prompt = $input['prompt'];
        $model = isset($input['model']) ? $input['model'] : 'gemini-1.5-pro';

        // Detect if it's a ChatGPT model
        if (strpos($model, 'gpt-') === 0) {
            // OpenAI API
            $apiUrl = "https://api.openai.com/v1/chat/completions";
            $data = [
                "model" => $model,
                "messages" => [
                    ["role" => "user", "content" => $prompt]
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openaiKey
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                echo json_encode(["error" => "Curl error: " . curl_error($ch)]);
            } else {
                $json = json_decode($response, true);

                if (isset($json['error'])) {
                    echo json_encode(["error" => "OpenAI API Error: " . $json['error']['message']]);
                } else if (!isset($json['choices'][0]['message']['content'])) {
                    echo json_encode(["error" => "AI returnerte ingen svar."]);
                } else {
                    $text = $json['choices'][0]['message']['content'];
                    echo json_encode(["success" => true, "text" => $text]);
                }
            }
            curl_close($ch);
        } else {
            // Gemini API
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $geminiKey;

            $data = [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $prompt]
                        ]
                    ]
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                echo json_encode(["error" => "Curl error: " . curl_error($ch)]);
            } else {
                $json = json_decode($response, true);

                if (isset($json['error'])) {
                    echo json_encode(["error" => "Gemini API Error: " . $json['error']['message']]);
                } else if (!isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                    echo json_encode(["error" => "AI returnerte ingen kandidater. Sjekk API-kvote eller prompt."]);
                } else {
                    $text = $json['candidates'][0]['content']['parts'][0]['text'];
                    echo json_encode(["success" => true, "text" => $text]);
                }
            }
            curl_close($ch);
        }
    }

    // --- ADMIN: IMPORT BATCH ---
    else if ($action === 'list_models') {
        $apiKey = "AIzaSyC1Wsd417FPQ7DHR0EaXrQGu6O_bzgX7ZU";
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
        $response = @file_get_contents($url);
        if ($response === FALSE) {
            return ['error' => 'Failed to list models'];
        }
        return json_decode($response, true);
    } else if ($action === 'admin_import_batch') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $questions = $input['questions'];
        $count = 0;

        try {
            // Find max shared_id to start from
            $sStmt = $conn->query("SELECT MAX(shared_id) as m FROM questions");
            $row = $sStmt->fetch();
            $nextSharedId = ($row['m'] ? intval($row['m']) : 0) + 1;

            $stmt = $conn->prepare("INSERT INTO questions (lang, region, era, topic, question, option_0, option_1, option_2, option_3, correct_idx, shared_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Group questions by language to pair NO/EN
            $noQuestions = [];
            $enQuestions = [];

            foreach ($questions as $q) {
                if ($q['lang'] === 'no') {
                    $noQuestions[] = $q;
                } else if ($q['lang'] === 'en') {
                    $enQuestions[] = $q;
                }
            }

            // Pair NO/EN questions with same shared_id
            $maxPairs = max(count($noQuestions), count($enQuestions));

            for ($i = 0; $i < $maxPairs; $i++) {
                $sharedId = $nextSharedId++;

                // Insert Norwegian version if exists
                if (isset($noQuestions[$i])) {
                    $q = $noQuestions[$i];

                    // Check for duplicate
                    $checkStmt = $conn->prepare("SELECT id FROM questions WHERE question = ? AND lang = 'no' LIMIT 1");
                    $checkStmt->execute([$q['question']]);
                    if ($checkStmt->fetch()) {
                        continue; // Skip duplicate
                    }

                    $stmt->execute([
                        'no',
                        $q['region'],
                        $q['era'],
                        $q['topic'],
                        $q['question'],
                        $q['options'][0],
                        $q['options'][1],
                        $q['options'][2],
                        $q['options'][3],
                        $q['correct'],
                        $sharedId
                    ]);
                    $count++;
                }

                // Insert English version if exists
                if (isset($enQuestions[$i])) {
                    $q = $enQuestions[$i];

                    // Check for duplicate
                    $checkStmt = $conn->prepare("SELECT id FROM questions WHERE question = ? AND lang = 'en' LIMIT 1");
                    $checkStmt->execute([$q['question']]);
                    if ($checkStmt->fetch()) {
                        continue; // Skip duplicate
                    }

                    $stmt->execute([
                        'en',
                        $q['region'],
                        $q['era'],
                        $q['topic'],
                        $q['question'],
                        $q['options'][0],
                        $q['options'][1],
                        $q['options'][2],
                        $q['options'][3],
                        $q['correct'],
                        $sharedId
                    ]);
                    $count++;
                }
            }

            echo json_encode(["success" => true, "count" => $count]);
        } catch (Exception $e) {
            echo json_encode(["error" => "DB Error: " . $e->getMessage()]);
        }
    }


}
// Allow both GET and POST for the following actions
if (true) {

    if ($action === 'refresh_user') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // CHECK BADGES (Retrospective Fix for active block)
            require_once 'check_badges.php';
            checkBadges($conn, $userId);

            // USE SHARED FUNCTION (Fixes missing History)
            $user = refresh_user($conn, $userId);

            if ($user) {
                send_json(["success" => true, "user" => $user]);
            } else {
                send_json(["error" => "Fant ikke bruker"]);
            }
        } catch (Exception $e) {
            send_json(["success" => false, "error" => "DB Error: " . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_my_challenges') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // Ensure updated_at column exists for timeout tracking
            try {
                $conn->exec("ALTER TABLE asynchronous_challenges ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            } catch (Exception $e) {
            }
            // Ensure matches table has game_mode
            try {
                $conn->exec("ALTER TABLE matches ADD COLUMN game_mode VARCHAR(50) DEFAULT 'normal'");
            } catch (Exception $e) {
            }

            // AUTO-TIMEOUT EXPIRED TURNS (48 hours idle)
            $conn->exec("UPDATE asynchronous_challenges SET status = 'timeout', challenger_score = 0, opponent_score = 10, opponent_id = IFNULL(opponent_id, 0) WHERE status = 'accepted' AND updated_at < NOW() - INTERVAL 48 HOUR");
            $conn->exec("UPDATE asynchronous_challenges SET status = 'timeout', challenger_score = 10, opponent_score = 0 WHERE status = 'pending' AND updated_at < NOW() - INTERVAL 48 HOUR");

            // AUTO-DELETE non-friend challenges > 14 days (Clean up database)
            $conn->exec("DELETE FROM asynchronous_challenges 
                         WHERE created_at < NOW() - INTERVAL 14 DAY 
                         AND (opponent_id IS NULL OR opponent_id NOT IN (
                             SELECT friend_id FROM social_friends WHERE user_id = asynchronous_challenges.challenger_id AND status = 'accepted'
                         ))");

            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE challenger_id = ? OR opponent_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$userId, $userId]);
            $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // AUTO-REPAIR: Fix stuck "pending" challenges if both players have scores
            foreach ($challenges as &$c) {
                if (
                    $c['status'] !== 'completed' &&
                    $c['challenger_score'] !== null &&
                    $c['opponent_score'] !== null
                ) {

                    // Mark as completed in memory
                    $c['status'] = 'completed';

                    // Update DB asynchronously (lazy fix)
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET status = 'completed' WHERE id = ?");
                    $upd->execute([$c['id']]);
                }

                // Parse Settings JSON
                $c['settings'] = json_decode($c['settings'], true);
                if (!$c['settings'])
                    $c['settings'] = [];
            }

            echo json_encode(["success" => true, "challenges" => $challenges]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_highscores') {
        $gameMode = isset($_GET['game_mode']) ? $_GET['game_mode'] : 'normal';

        // CLEANUP: Delete 0-point scores and malformed entries (One-time check per fetch is fine)
        $conn->exec("DELETE FROM highscores WHERE score <= 0 AND game_mode != 'race_10'");

        if ($gameMode === 'race_10') {
            // ASC sort for time
            $stmt = $conn->prepare("SELECT id, name, score, game_mode, questions_answered, date, user_id, (SELECT GROUP_CONCAT(DISTINCT badge_id) FROM user_badges WHERE user_id = highscores.user_id) as badges FROM highscores WHERE game_mode = 'race_10' ORDER BY score ASC, date DESC LIMIT 100");
            $stmt->execute();
        } else if ($gameMode === 'emperors') {
            // Strict filter for Emperors
            $stmt = $conn->prepare("SELECT id, name, score, game_mode, questions_answered, date, user_id, (SELECT GROUP_CONCAT(DISTINCT badge_id) FROM user_badges WHERE user_id = highscores.user_id) as badges FROM highscores WHERE game_mode = ? ORDER BY score DESC, date DESC LIMIT 100");
            $stmt->execute([$gameMode]);
        } else if ($gameMode === 'monarchs') {
            // Strict filter for Monarchs
            $stmt = $conn->prepare("SELECT id, name, score, game_mode, questions_answered, date, user_id, (SELECT GROUP_CONCAT(DISTINCT badge_id) FROM user_badges WHERE user_id = highscores.user_id) as badges FROM highscores WHERE game_mode = ? ORDER BY score DESC, date DESC LIMIT 100");
            $stmt->execute([$gameMode]);
        } else if ($gameMode === 'sudden-death' || $gameMode === 'sd' || $gameMode === 'sudden_death') {
            // Sudden Death Fetch
            $stmt = $conn->prepare("SELECT id, name, score, game_mode, questions_answered, date, user_id, (SELECT GROUP_CONCAT(DISTINCT badge_id) FROM user_badges WHERE user_id = highscores.user_id) as badges FROM highscores WHERE game_mode IN ('sudden-death', 'sd', 'sudden_death', 'sudden death') ORDER BY score DESC, date DESC LIMIT 100");
            $stmt->execute();
        } else {
            // Default: Normal/Timed/Null, strictly excluding special modes if requesting generic 'normal'
            // If specific other mode (e.g. 'sudden_death'), we filter by it.

            if ($gameMode === 'normal' || $gameMode === 'timed_60' || !$gameMode) {
                // Expanded definition of "Normal" to include legacy records
                $stmt = $conn->prepare("SELECT id, name, score, game_mode, questions_answered, date, user_id, (SELECT GROUP_CONCAT(DISTINCT badge_id) FROM user_badges WHERE user_id = highscores.user_id) as badges FROM highscores WHERE score > 0 AND (game_mode = 'normal' OR game_mode = 'timed_60' OR game_mode IS NULL) ORDER BY score DESC, date DESC LIMIT 100");
                $stmt->execute();
            } else {
                // Generic handler for other potential future modes
                $stmt = $conn->prepare("SELECT id, name, score, game_mode, questions_answered, date, user_id, (SELECT GROUP_CONCAT(DISTINCT badge_id) FROM user_badges WHERE user_id = highscores.user_id) as badges FROM highscores WHERE game_mode = ? ORDER BY score DESC, date DESC LIMIT 100");
                $stmt->execute([$gameMode]);
            }
        }

        echo json_encode($stmt->fetchAll());
        exit;
    } else if ($action === 'get_badge_leaders') {
        // Leaderboard for most badges
        try {
            // Get users with badges, sort by count
            $sql = "SELECT u.username, u.id, COUNT(ub.badge_id) as badge_count, GROUP_CONCAT(ub.badge_id) as badges 
                    FROM users u 
                    JOIN user_badges ub ON u.id = ub.user_id 
                    GROUP BY u.id 
                    ORDER BY badge_count DESC, u.username ASC 
                    LIMIT 50";
            $stmt = $conn->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        exit;
    } else if ($action === 'get_profile') {
        $userId = intval($_GET['user_id']);
        // Fetch basic info + Unique Correct Count
        $stmt = $conn->prepare("
            SELECT u.username, u.total_score, u.games_played, u.created_at,
                   (
                       (SELECT COUNT(*) FROM user_correct_answers WHERE user_id = u.id) +
                       IFNULL((SELECT SUM(correct_count) FROM user_special_stats WHERE user_id = u.id), 0)
                   ) as correct_count
            FROM users u WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            // Get topic counts
            $stmtTopic = $conn->prepare("
                SELECT q.topic, COUNT(*) as count 
                FROM user_correct_answers uca 
                JOIN questions q ON uca.question_id = q.id 
                WHERE uca.user_id = ? 
                GROUP BY q.topic
            ");
            $stmtTopic->execute([$userId]);
            $user['topic_counts'] = $stmtTopic->fetchAll(PDO::FETCH_KEY_PAIR);

            // Get Badges
            $stmtB = $conn->prepare("SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ? ORDER BY awarded_at ASC");
            $stmtB->execute([$userId]);
            $user['badges'] = $stmtB->fetchAll();

            // Get single player highscores
            $stmtH = $conn->prepare("SELECT 'highscore' as type, score, date, game_mode FROM highscores WHERE user_id = ? ORDER BY date DESC LIMIT 20");
            $stmtH->execute([$userId]);
            $historyHigh = $stmtH->fetchAll();

            // Get multiplayer matches
            $stmtM = $conn->prepare("
                SELECT 'match' as type, m.player1_score, m.player2_score, m.winner_id, m.played_at as date, m.game_mode,
                       IFNULL(u1.username, 'Gjest') as p1_name, IFNULL(u2.username, 'Gjest') as p2_name
                FROM matches m
                LEFT JOIN users u1 ON m.player1_id = u1.id
                LEFT JOIN users u2 ON m.player2_id = u2.id
                WHERE m.player1_id = ? OR m.player2_id = ?
                ORDER BY m.played_at DESC LIMIT 20
            ");
            $stmtM->execute([$userId, $userId]);
            $historyMatches = $stmtM->fetchAll();

            // Merge and Sort
            $fullHistory = array_merge($historyHigh, $historyMatches);
            usort($fullHistory, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Get special stats to merge into topic_counts
            $stmtSpecial = $conn->prepare("SELECT topic, correct_count FROM user_special_stats WHERE user_id = ?");
            $stmtSpecial->execute([$userId]);
            $specialCounts = $stmtSpecial->fetchAll(PDO::FETCH_KEY_PAIR);

            // Merge into topic_counts (prefer non-special if conflict, though names should be unique)
            if ($specialCounts) {
                foreach ($specialCounts as $topic => $count) {
                    $user['topic_counts'][$topic] = intval($count);
                }
            }

            $user['history'] = array_slice($fullHistory, 0, 10);
            send_json(["success" => true, "profile" => $user]);
        } else {
            send_json(["error" => "User not found"]);
        }
    } else if ($action === 'get_badge_data') {
        // Return all badges with user's progress
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        if (!$userId) {
            send_json(["error" => "No user ID"]);
        }

        try {
            log_debug("get_badge_data: Starting for user " . $userId);
            require_once 'check_badges.php';
            $badgeData = checkBadges($conn, $userId, [], true);
            log_debug("get_badge_data: Found " . count($badgeData) . " badges");
            send_json(["success" => true, "badges" => $badgeData]);
        } catch (Exception $e) {
            log_debug("get_badge_data: ERROR: " . $e->getMessage());
            send_json(["success" => false, "error" => $e->getMessage()]);
        }
    }
    // --- TEMPORARY: MBSTRING CHECK ---
    else if ($action === 'check_mbstring') {
        log_debug("DIAGNOSTIC CALL AT " . date('Y-m-d H:i:s'));
        send_json([
            "mbstring_loaded" => extension_loaded('mbstring'),
            "mb_strtolower_exists" => function_exists('mb_strtolower'),
            "php_version" => phpversion(),
            "server_time" => date('Y-m-d H:i:s'),
            "cwd" => getcwd(),
            "log_writable" => is_writable('debug.log')
        ]);
    }

    // --- TEMPORARY: DEBUG LOG READER ---
    else if ($action === 'get_debug_log') {
        if (!isAdmin($conn, $_GET['admin_user'])) {
            send_json(["error" => "Unauthorized"]);
        }
        $log = file_exists('debug.log') ? file_get_contents('debug.log') : 'No log file found.';
        header('Content-Type: text/plain');
        echo $log;
        exit;
    }

    // --- TEMPORARY: GET QUESTION COUNT ---
    else if ($action === 'get_question_count') {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM questions");
        $total = $stmt->fetch()['total'];
        $stmtNo = $conn->query("SELECT COUNT(*) as c FROM questions WHERE lang='no'");
        $no = $stmtNo->fetch()['c'];
        $stmtEn = $conn->query("SELECT COUNT(*) as c FROM questions WHERE lang='en'");
        $en = $stmtEn->fetch()['c'];

        // Count unique questions (ignoring language duplicates)
        $stmtUnique = $conn->query("SELECT COUNT(DISTINCT shared_id) as u FROM questions WHERE shared_id > 0");
        $unique = $stmtUnique->fetch()['u'];

        echo json_encode(["success" => true, "total" => $total, "no" => $no, "en" => $en, "unique" => $unique]);
    }

    // --- GET QUESTIONS ---
    else if ($action === 'get_questions') {
        $lang = isset($_GET['lang']) ? $_GET['lang'] : 'no';
        $region = isset($_GET['region']) ? $_GET['region'] : 'all';
        $era = isset($_GET['era']) ? $_GET['era'] : 'all';
        $topic = isset($_GET['topic']) ? $_GET['topic'] : 'all';
        $sharedIdList = isset($_GET['shared_id_list']) ? $_GET['shared_id_list'] : null;
        $seed = isset($_GET['seed']) ? intval($_GET['seed']) : null;

        log_sync("get_questions: Lang=$lang Shared=" . ($sharedIdList ? "YES" : "NO") . " Seed=" . ($seed ? $seed : "NO"));

        // CRITICAL FIX: Only fetch questions that have a shared_id.
        // Questions with shared_id=0 cannot be synced to opponent (they won't find them).
        $sql = "SELECT id, question, option_0, option_1, option_2, option_3, correct_idx, shared_id FROM questions WHERE lang = ? AND shared_id > 0";
        $params = [$lang];

        if ($region !== 'all') {
            $sql .= " AND region = ?";
            $params[] = $region;
        }
        if ($era !== 'all') {
            $sql .= " AND era = ?";
            $params[] = $era;
        }
        if ($topic !== 'all') {
            $sql .= " AND topic = ?";
            $params[] = $topic;
        }

        // IMPORTANT: Add Randomness!
        $seed = isset($_GET['seed']) ? intval($_GET['seed']) : null;
        $sharedIdList = isset($_GET['shared_id_list']) ? $_GET['shared_id_list'] : null;

        if ($sharedIdList) {
            // MODE 1: Fetch Specific Questions (Sync Mode)
            // Expects comma-separated list of shared_ids.
            // We prioritize questions in the requested language that match these shared_ids.
            $idsArray = array_map('intval', explode(',', $sharedIdList));
            if (count($idsArray) > 0) {
                // Determine order based on the provided list (FIELD function in MySQL would be nice but complex with mapping)
                // Just fetch them, and JS can sort if needed, or we rely on the fact they are a set.
                // We need to match EITHER (lang = $lang AND shared_id in list) OR (fallback/original if not found?)
                // Actually, just fetching WHERE shared_id IN (...) AND lang = ? is safest.
                // If a translation is missing, maybe fallback to EN? For now, assume translation exists.
                $inQuery = implode(',', array_fill(0, count($idsArray), '?'));
                // Append params
                foreach ($idsArray as $id)
                    $params[] = $id;

                $sql = "SELECT id, question, option_0, option_1, option_2, option_3, correct_idx, shared_id FROM questions WHERE lang = ? AND shared_id IN ($inQuery)";
                // Reset params to just contain lang + ids (Region/Era filters ignored in Sync Mode)
                $params = array_merge([$lang], $idsArray);

                // No LIMIT needed usually, but keep it safe (high enough for any reasonable challenge)
                $sql .= " LIMIT 1000";
            }
        } elseif ($seed !== null) {
            // MODE 2: Random Seed (First Player)
            $sql .= " ORDER BY RAND(IF(shared_id > 0, shared_id, id) + $seed) LIMIT 500";
        } else {
            $sql .= " ORDER BY RAND() LIMIT 500";
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetchAll();
        // Format for frontend
        $formatted = array_map(function ($q) {
            return [
                "id" => $q['id'],
                "shared_id" => isset($q['shared_id']) ? intval($q['shared_id']) : 0,
                "question" => $q['question'],
                "options" => [$q['option_0'], $q['option_1'], $q['option_2'], $q['option_3']],
                "correct" => intval($q['correct_idx'])
            ];
        }, $res);
        echo json_encode($formatted);
    } else if ($action === 'setup_progress_table') {
        $u = isset($_GET['admin_user']) ? $_GET['admin_user'] : '';
        if (!isAdmin($conn, $u)) {
            echo json_encode(["error" => "Restricted"]);
            exit;
        }
        try {
            $sql = "CREATE TABLE IF NOT EXISTS challenge_progress (
                challenge_id INT NOT NULL,
                user_id INT NOT NULL,
                q_index INT DEFAULT 0,
                current_score INT DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (challenge_id, user_id)
            )";
            $conn->exec($sql);
            echo json_encode(["success" => true, "msg" => "Table challenge_progress created"]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- GAME PROGRESS (RESUMABLE) ---
    else if ($action === 'save_progress') {
        $cid = isset($input['challenge_id']) ? intval($input['challenge_id']) : 0;
        $uid = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $qIdx = isset($input['q_index']) ? intval($input['q_index']) : 0;
        $score = isset($input['score']) ? intval($input['score']) : 0;
        $timeRem = isset($input['time_remaining']) ? intval($input['time_remaining']) : null;
        $elapsedMs = isset($input['elapsed_ms']) ? intval($input['elapsed_ms']) : 0;

        if ($cid > 0 && $uid > 0) {
            $sql = "INSERT INTO challenge_progress (challenge_id, user_id, q_index, current_score, time_remaining, elapsed_ms) VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE q_index = ?, current_score = ?, time_remaining = ?, elapsed_ms = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cid, $uid, $qIdx, $score, $timeRem, $elapsedMs, $qIdx, $score, $timeRem, $elapsedMs]);
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => "Invalid params"]);
        }
    }
    // --- GET GAME PROGRESS (RESUME) ---
    else if ($action === 'get_progress') {
        $cid = isset($_GET['challenge_id']) ? intval($_GET['challenge_id']) : 0;
        $uid = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if ($uid == 0 && isset($_SESSION['user_id']))
            $uid = $_SESSION['user_id']; // Optional session fallback

        if ($cid > 0 && $uid > 0) {
            $stmt = $conn->prepare("SELECT * FROM challenge_progress WHERE challenge_id = ? AND user_id = ?");
            $stmt->execute([$cid, $uid]);
            $prog = $stmt->fetch();
            if ($prog) {
                echo json_encode(["success" => true, "progress" => $prog]);
            } else {
                echo json_encode(["success" => false, "msg" => "No progress found"]);
            }
        } else {
            echo json_encode(["error" => "Invalid params"]);
        }
    } else if ($action === 'clear_progress') {
        $cid = isset($input['challenge_id']) ? intval($input['challenge_id']) : 0;
        $uid = isset($input['user_id']) ? intval($input['user_id']) : 0;

        if ($cid > 0 && $uid > 0) {
            $stmt = $conn->prepare("DELETE FROM challenge_progress WHERE challenge_id = ? AND user_id = ?");
            $stmt->execute([$cid, $uid]);
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => "Invalid ID"]);
        }
    }

    // --- GET REPORTS (for admin panel) ---
    else if ($action === 'get_reports') {
        // Get all reports with question details
        $stmt = $conn->prepare("
            SELECT r.id, r.question_id, r.reason, r.created_at, 
                   q.question, q.lang, 
                   u.username
            FROM reports r
            LEFT JOIN questions q ON r.question_id = q.id
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        $reports = $stmt->fetchAll();

        echo json_encode($reports);
    }

    // --- ADMIN: GET SINGLE QUESTION ---
    else if ($action === 'admin_get_question') {
        ob_start();
        error_reporting(0);
        $u = isset($_GET['admin_user']) ? $_GET['admin_user'] : (isset($input['admin_user']) ? $input['admin_user'] : '');
        if (!isAdmin($conn, $u)) {
            ob_clean();
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($input['id']) ? intval($input['id']) : 0);

        try {
            $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
            $stmt->execute([$id]);
            $q = $stmt->fetch();

            if ($q) {
                $out = ["success" => true, "question" => $q];
                // Also fetch translations if shared_id > 0
                if ($q['shared_id'] > 0) {
                    $stmtT = $conn->prepare("SELECT * FROM questions WHERE shared_id = ? AND id != ?");
                    $stmtT->execute([$q['shared_id'], $id]);
                    $out['translations'] = $stmtT->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $out = ["success" => false, "error" => "Spørsmål ikke funnet"];
            }
        } catch (Exception $e) {
            $out = ["error" => $e->getMessage()];
        }
        while (ob_get_level())
            ob_end_clean();
        echo json_encode($out);
        exit;
    }

    // --- ADMIN: DELETE QUESTION ---
    else if ($action === 'admin_delete_question') {
        ob_start();
        error_reporting(0);
        $u = isset($input['admin_user']) ? $input['admin_user'] : '';
        if (!isAdmin($conn, $u)) {
            ob_clean();
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $id = isset($input['id']) ? intval($input['id']) : 0;
        $forceSingle = isset($input['force_single']) ? $input['force_single'] : false;

        try {
            if ($forceSingle) {
                // Delete dependencies first (Single)
                $conn->prepare("DELETE FROM user_correct_answers WHERE question_id = ?")->execute([$id]);
                $conn->prepare("DELETE FROM reports WHERE question_id = ?")->execute([$id]);

                // Delete ONLY this ID
                $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                // Delete PAIR (all with same shared_id)
                // First get the shared_id
                $stmt = $conn->prepare("SELECT shared_id FROM questions WHERE id = ?");
                $stmt->execute([$id]);
                $q = $stmt->fetch();
                if ($q && $q['shared_id'] > 0) {
                    $sid = $q['shared_id'];
                    // Find all IDs in this pair
                    $stmtIds = $conn->prepare("SELECT id FROM questions WHERE shared_id = ?");
                    $stmtIds->execute([$sid]);
                    $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($ids)) {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        // Delete dependencies
                        $conn->prepare("DELETE FROM user_correct_answers WHERE question_id IN ($placeholders)")->execute($ids);
                        $conn->prepare("DELETE FROM reports WHERE question_id IN ($placeholders)")->execute($ids);
                    }

                    $stmtDel = $conn->prepare("DELETE FROM questions WHERE shared_id = ?");
                    $stmtDel->execute([$sid]);
                } else {
                    // Fallback to single if no shared_id
                    $conn->prepare("DELETE FROM user_correct_answers WHERE question_id = ?")->execute([$id]);
                    $conn->prepare("DELETE FROM reports WHERE question_id = ?")->execute([$id]);

                    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
                    $stmt->execute([$id]);
                }
            }
            ob_clean();
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(["error" => $e->getMessage()]);
        }
        exit;
    }

    // --- ADMIN: SAVE QUESTION (Create/Update) ---
    else if ($action === 'admin_save_question') {
        ob_start();
        error_reporting(0);
        $u = isset($input['admin_user']) ? $input['admin_user'] : '';
        if (!isAdmin($conn, $u)) {
            while (ob_get_level())
                ob_end_clean();
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $d = isset($input['question_data']) ? $input['question_data'] : [];
        if (!$d) {
            while (ob_get_level())
                ob_end_clean();
            echo json_encode(["error" => "No data"]);
            exit;
        }

        $id = intval($d['id']);
        $sharedId = intval($d['shared_id']);
        $q = trim($d['question']);
        $o0 = trim($d['option_0']);
        $o1 = trim($d['option_1']);
        $o2 = trim($d['option_2']);
        $o3 = trim($d['option_3']);
        $cIdx = intval($d['correct_idx']);
        $topic = $d['topic'];
        $era = $d['era'];
        $region = $d['region'];
        $lang = $d['lang'];

        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $conn->prepare("UPDATE questions SET question=?, option_0=?, option_1=?, option_2=?, option_3=?, correct_idx=?, topic=?, era=?, region=?, lang=?, shared_id=? WHERE id=?");
                $stmt->execute([$q, $o0, $o1, $o2, $o3, $cIdx, $topic, $era, $region, $lang, $sharedId, $id]);
            } else {
                // INSERT
                $stmt = $conn->prepare("INSERT INTO questions (question, option_0, option_1, option_2, option_3, correct_idx, topic, era, region, lang, shared_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$q, $o0, $o1, $o2, $o3, $cIdx, $topic, $era, $region, $lang, $sharedId]);
                $id = $conn->lastInsertId();
                if ($sharedId <= 0) {
                    $conn->exec("UPDATE questions SET shared_id = $id WHERE id = $id");
                }
            }
            $out = ["success" => true];
        } catch (Exception $e) {
            $out = ["error" => "Database error: " . $e->getMessage()];
        }
        while (ob_get_level())
            ob_end_clean();
        echo json_encode($out);
        exit;
    }

    // --- ADMIN: DELETE QUESTION ---
    else if ($action === 'admin_delete_question') {
        ob_start();
        error_reporting(0);
        $u = isset($input['admin_user']) ? $input['admin_user'] : '';
        if (!isAdmin($conn, $u)) {
            while (ob_get_level())
                ob_end_clean();
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $id = intval($input['id']);
        try {
            $forceSingle = isset($input['force_single']) && $input['force_single'] === true;

            // Get the shared_id and all related question IDs first
            $stmt = $conn->prepare("SELECT shared_id FROM questions WHERE id = ?");
            $stmt->execute([$id]);
            $q = $stmt->fetch();
            $sharedId = $q ? intval($q['shared_id']) : 0;

            $questionIdsToDelete = [];
            if ($sharedId > 0 && !$forceSingle) {
                // Get all question IDs linked by this shared_id
                $stmtIds = $conn->prepare("SELECT id FROM questions WHERE shared_id = ?");
                $stmtIds->execute([$sharedId]);
                $questionIdsToDelete = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
            } else {
                // Only delete the specific ID if no shared_id or shared_id is 0 OR forceSingle is true
                $questionIdsToDelete[] = $id;
            }

            // Delete reports for all identified question IDs
            if (!empty($questionIdsToDelete)) {
                $inQuery = implode(',', array_fill(0, count($questionIdsToDelete), '?'));
                $conn->prepare("DELETE FROM reports WHERE question_id IN ($inQuery)")->execute($questionIdsToDelete);
            }

            // Now delete the questions themselves
            if ($sharedId > 0 && !$forceSingle) {
                $conn->prepare("DELETE FROM questions WHERE shared_id = ?")->execute([$sharedId]);
            } else {
                $conn->prepare("DELETE FROM questions WHERE id = ?")->execute([$id]);
            }

            $out = ["success" => true];
        } catch (Exception $e) {
            $out = ["error" => "Delete failed: " . $e->getMessage()];
        }
        while (ob_get_level())
            ob_end_clean();
        echo json_encode($out);
        exit;
    }

    // --- ADMIN: IMPORT CSV ---
    else if ($action === 'admin_import_csv') {
        // Prevent JSON corruption from PHP warnings
        ob_start();
        error_reporting(0);

        if (!isAdmin($conn, $_POST['admin_user'])) {
            ob_clean();
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(["error" => "Upload failed or no file provided."]);
            exit;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $content = file_get_contents($file);

        // Detect delimiter (simple check)
        $semicolonCount = substr_count($content, ';');
        $commaCount = substr_count($content, ',');
        $delimiter = ($semicolonCount > $commaCount) ? ';' : ',';

        $handle = fopen($file, "r");
        if ($handle === FALSE) {
            echo json_encode(["error" => "Could not open file."]);
            exit;
        }

        // Get max shared_id
        $stmtMax = $conn->query("SELECT MAX(shared_id) FROM questions");
        $maxShared = intval($stmtMax->fetchColumn());
        if ($maxShared < 1000)
            $maxShared = 1000; // Start high if empty

        $successCount = 0;
        $rowIdx = 0;
        $errors = [];

        // Expected columns (flexible order? No, enforce order for simplicity or use header mapping)
        // Let's use header mapping.
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            echo json_encode(["error" => "Empty CSV."]);
            exit;
        }

        // Lowercase headers for mapping
        $header = array_map('strtolower', array_map('trim', $header));

        // Required keys
        $reqKeys = ['region', 'era', 'topic', 'no_question', 'no_correct_idx', 'no_a', 'no_b', 'no_c', 'no_d', 'en_question'];

        // Check missing
        $missing = [];
        foreach ($reqKeys as $k) {
            if (!in_array($k, $header))
                $missing[] = $k;
        }

        if (!empty($missing)) {
            echo json_encode(["error" => "Missing columns: " . implode(', ', $missing)]);
            exit;
        }

        $colMap = array_flip($header); // key => index

        $conn->beginTransaction(); // Start transaction for bulk import

        while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
            $rowIdx++;
            if (count($data) < count($reqKeys)) {
                $errors[] = "Row $rowIdx: Not enough columns.";
                continue;
            }

            try {
                // Generate new Shared ID
                $maxShared++;
                $sid = $maxShared;

                // Extract data
                $region = $data[$colMap['region']] ?? 'world';
                $era = $data[$colMap['era']] ?? 'after_1750';
                $topic = $data[$colMap['topic']] ?? 'general';

                // Norwegian
                $noQ = trim($data[$colMap['no_question']]);

                // --- DUPLICATE CHECK (Enhanced) ---
                // 1. Strict Question Match
                $stmtChk = $conn->prepare("SELECT COUNT(*) FROM questions WHERE question = ? AND lang = 'no'");
                $stmtChk->execute([$noQ]);
                if ($stmtChk->fetchColumn() > 0) {
                    $errors[] = "Rad $rowIdx: Ignorert (Spørsmål finnes: '$noQ')";
                    continue;
                }

                // 2. Answer-based Fuzzy Match
                $colNames = ['no_a', 'no_b', 'no_c', 'no_d'];
                $noIdx = intval($data[$colMap['no_correct_idx']]);
                // Default to 'a' / index 0 if out of bounds
                $correctCol = (isset($colNames[$noIdx])) ? $colNames[$noIdx] : 'no_a';
                $correctAnswerText = trim($data[$colMap[$correctCol]] ?? '');

                if (!empty($correctAnswerText)) {
                    // Find matches by answer
                    // (Matches ANY of the 4 options if it is the correct one)
                    $stmtFuzzy = $conn->prepare("
                        SELECT question FROM questions 
                        WHERE lang='no' AND (
                            (correct_idx=0 AND option_0=?) OR 
                            (correct_idx=1 AND option_1=?) OR 
                            (correct_idx=2 AND option_2=?) OR 
                            (correct_idx=3 AND option_3=?)
                        )
                    ");
                    $stmtFuzzy->execute([$correctAnswerText, $correctAnswerText, $correctAnswerText, $correctAnswerText]);
                    $candidates = $stmtFuzzy->fetchAll(PDO::FETCH_COLUMN);

                    $isFuzzyDuplicate = false;
                    foreach ($candidates as $candQ) {
                        $percent = 0;
                        similar_text(mb_strtolower($noQ), mb_strtolower($candQ), $percent);
                        if ($percent > 85) { // 85% similarity threshold
                            $isFuzzyDuplicate = true;
                            $errors[] = "Rad $rowIdx: Ignorert ('$candQ' ligner og har samme svar)";
                            break;
                        }
                    }
                    if ($isFuzzyDuplicate)
                        continue;
                }

                $noA = $data[$colMap['no_a']];
                $noB = $data[$colMap['no_b']];
                $noC = $data[$colMap['no_c']];
                $noD = $data[$colMap['no_d']];

                // English
                $enQ = $data[$colMap['en_question']];
                // Optional EN fields (fallback to NO values/logic if missing, but plan said explicit)
                $enIdx = isset($colMap['en_correct_idx']) ? intval($data[$colMap['en_correct_idx']]) : $noIdx;
                $enA = isset($colMap['en_a']) ? $data[$colMap['en_a']] : $noA;
                $enB = isset($colMap['en_b']) ? $data[$colMap['en_b']] : $noB;
                $enC = isset($colMap['en_c']) ? $data[$colMap['en_c']] : $noC;
                $enD = isset($colMap['en_d']) ? $data[$colMap['en_d']] : $noD;

                if (empty($noQ) || empty($enQ)) {
                    $errors[] = "Row $rowIdx: Missing question text.";
                    continue;
                }

                // Insert NO
                $stmt = $conn->prepare("INSERT INTO questions (shared_id, lang, region, era, topic, question, option_0, option_1, option_2, option_3, correct_idx, created_at) VALUES (?, 'no', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$sid, $region, $era, $topic, $noQ, $noA, $noB, $noC, $noD, $noIdx]);

                // Insert EN
                $stmt = $conn->prepare("INSERT INTO questions (shared_id, lang, region, era, topic, question, option_0, option_1, option_2, option_3, correct_idx, created_at) VALUES (?, 'en', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$sid, $region, $era, $topic, $enQ, $enA, $enB, $enC, $enD, $enIdx]);

                $importedIds[] = $sid;
                $successCount++;

            } catch (Exception $e) {
                $errors[] = "Row $rowIdx: " . $e->getMessage();
            }
        }
        fclose($handle);

        // Fetch the fully imported data to return to client
        $importedData = [];
        if (!empty($importedIds)) {
            // Fetch NO versions only for the review list (simpler)
            // or fetch both if needed. Review table typically shows NO.
            $inQuery = implode(',', array_fill(0, count($importedIds), '?'));
            $stmtFetch = $conn->prepare("SELECT * FROM questions WHERE shared_id IN ($inQuery) AND lang='no' ORDER BY shared_id ASC");
            $stmtFetch->execute($importedIds);
            $importedData = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);
        }

        $conn->commit();

        // Discard any buffered output (warnings/notices)
        while (ob_get_level())
            ob_end_clean();

        echo json_encode([
            "success" => true,
            "imported" => $successCount - count($errors),
            "errors" => $errors,
            "imported_data" => $importedData
        ]);
        exit;
    }

    // --- ADMIN: LIST ALL QUESTIONS ---
    else if ($action === 'admin_list_questions') {
        if (!isAdmin($conn, $_GET['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        // Pagination and filtering
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $topic = isset($_GET['topic']) && $_GET['topic'] !== 'all' ? $_GET['topic'] : null;
        $era = isset($_GET['era']) && $_GET['era'] !== 'all' ? $_GET['era'] : null;
        $lang = isset($_GET['lang']) && $_GET['lang'] !== 'all' ? $_GET['lang'] : null;
        $term = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($term !== '') {
            $term = "%$term%";
            $where[] = "(question LIKE ? OR option_0 LIKE ? OR option_1 LIKE ? OR option_2 LIKE ? OR option_3 LIKE ? OR id IN (SELECT question_id FROM reports))";
            // Params for first part
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $region = isset($_GET['region']) && $_GET['region'] !== 'all' ? $_GET['region'] : null;
        if ($region) {
            $where[] = "region = ?";
            $params[] = $region;
        }
        if ($era) {
            $where[] = "era = ?";
            $params[] = $era;
        }
        if ($lang && !$search) {
            $where[] = "lang = ?";
            $params[] = $lang;
        }
        if ($search) {
            // Find ALL versions of questions where EITHER version matches
            // Exclude shared_id <= 0 to avoid matching legacy or broken data
            // Find ALL versions of questions where EITHER version matches question OR options
            // Matches question text OR any of the 4 options
            $term = "%" . $search . "%";
            $where[] = "(
                question LIKE ? OR 
                option_0 LIKE ? OR option_1 LIKE ? OR option_2 LIKE ? OR option_3 LIKE ? OR
                (shared_id IS NOT NULL AND shared_id > 0 AND shared_id IN (
                    SELECT shared_id FROM questions WHERE (question LIKE ? OR option_0 LIKE ? OR option_1 LIKE ? OR option_2 LIKE ? OR option_3 LIKE ?) AND shared_id > 0
                ))
            )";
            // Params for first part
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            // Params for subquery
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM questions $whereClause";
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get paginated results with sort
        // Sort by created_at DESC to group by batch import time, then lang ASC to keep pairs together
        // shared_id is unreliable for old questions, so we fallback to time-based grouping
        $sql = "SELECT * FROM questions $whereClause ORDER BY created_at DESC, lang ASC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Calculate Stats
        $stats = ['total' => $total, 'no' => 0, 'en' => 0, 'debug_error' => null];
        try {
            // Debug Total
            $qs = $conn->query("SELECT COUNT(*) FROM questions WHERE lang='no'");
            if (!$qs)
                $stats['debug_error'] = $conn->errorInfo();
            else
                $stats['no'] = $qs->fetchColumn();

            $qs2 = $conn->query("SELECT COUNT(*) FROM questions WHERE lang='en'");
            if (!$qs2)
                $stats['debug_error'] = $conn->errorInfo();
            else
                $stats['en'] = $qs2->fetchColumn();

        } catch (Exception $e) {
            $stats['debug_error'] = $e->getMessage();
        }

        echo json_encode([
            "success" => true,
            "questions" => $stmt->fetchAll(),
            "stats" => $stats,
            "total" => $total,
            "page" => $page,
            "pages" => ceil($total / $limit)
        ]);
    }

    // --- CHECK DUPLICATE QUESTIONS (Batch) ---
    else if ($action === 'check_duplicate_questions') {
        $questions = isset($input['questions']) ? $input['questions'] : [];
        $duplicates = [];

        $stmtNo = $conn->prepare("SELECT COUNT(*) FROM questions WHERE question = ? AND lang = 'no'");
        $stmtEn = $conn->prepare("SELECT COUNT(*) FROM questions WHERE question = ? AND lang = 'en'");
        $stmtW = $conn->prepare("SELECT COUNT(*) FROM questions WHERE question = ?"); // Fallback

        foreach ($questions as $q) {
            $txt = trim($q['question']);
            $lang = isset($q['lang']) ? $q['lang'] : null;

            $exists = false;
            if ($lang === 'no') {
                $stmtNo->execute([$txt]);
                if ($stmtNo->fetchColumn() > 0)
                    $exists = true;
            } else if ($lang === 'en') {
                $stmtEn->execute([$txt]);
                if ($stmtEn->fetchColumn() > 0)
                    $exists = true;
            } else {
                $stmtW->execute([$txt]);
                if ($stmtW->fetchColumn() > 0)
                    $exists = true;
            }
            $duplicates[] = $exists;
        }

        echo json_encode(["success" => true, "duplicates" => $duplicates]);
    }

    // --- ADMIN: LIST USERS & STATS ---
    else if ($action === 'admin_list_users') {
        if (!isAdmin($conn, $_GET['admin_user'])) {
            send_json(["error" => "Unauthorized"]);
            exit;
        }

        try {
            // Stats
            $stats = [];
            $stats['total_users'] = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $stats['total_games'] = $conn->query("SELECT COUNT(*) FROM matches")->fetchColumn();
            // Active users (users who have played at least one match)
            $stats['active_users'] = $conn->query("SELECT COUNT(DISTINCT player1_id) + COUNT(DISTINCT player2_id) FROM matches")->fetchColumn();
            // Better active count: unique users in matches table? 
            // SELECT COUNT(DISTINCT user_id) FROM (SELECT player1_id as user_id FROM matches UNION SELECT player2_id FROM matches) as u
            // Simplified: Just use total users for now or a simple query.
            // Let's stick to "Users with > 0 matches_played" if that column exists? users table doesn't track it reliably.
            // Let's use a simpler proxy: Users logged in last 30 days? "last_login" column?
            // Checking DB structure is hard without SHOW COLUMNS.
            // Let's use the UNION method for accuracy if table size permits.
            $stmtActive = $conn->query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN matches m ON u.id = m.player1_id OR u.id = m.player2_id");
            $stats['active_users'] = $stmtActive->fetchColumn();

            // Users List
            $sortMap = [
                'username' => 'username',
                'created_at' => 'created_at',
                'games_played' => 'real_games_played',
                'correct_count' => 'correct_count'
            ];

            $sortBy = isset($_GET['sort_by']) && isset($sortMap[$_GET['sort_by']]) ? $sortMap[$_GET['sort_by']] : 'created_at';
            $sortOrder = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

            // Fetch id, username, email, created_at, games_played, total_score
            // We calculate real_games_played in SQL to ensure sorting works on the ACTUAL value, not the (potentially stale) cached column.
            $sql = "SELECT id, username, email, created_at, games_played, total_score, 
                    (SELECT COUNT(*) FROM user_correct_answers uca JOIN questions q ON uca.question_id = q.id WHERE uca.user_id = users.id) as correct_count,
                    (SELECT COUNT(*) FROM matches WHERE player1_id = users.id OR player2_id = users.id) as real_games_played
                    FROM users ORDER BY $sortBy $sortOrder LIMIT 50";

            $stmt = $conn->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as &$u) {
                // Mask Email
                if (!empty($u['email'])) {
                    $parts = explode('@', $u['email']);
                    if (count($parts) == 2) {
                        $name = $parts[0];
                        $domain = $parts[1];
                        $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
                        $u['email'] = $maskedName . '@' . $domain;
                    } else {
                        $u['email'] = '***';
                    }
                }

                // Use the SQL-calculated count for display
                $u['games_played'] = $u['real_games_played'];
            }

            send_json(["success" => true, "users" => $users, "stats" => $stats]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: LIST MATCHES ---
    else if ($action === 'admin_list_matches') {
        if (!isAdmin($conn, $_GET['admin_user'])) {
            send_json(["error" => "Unauthorized"]);
            exit;
        }
        try {
            $stmt = $conn->query("
                SELECT m.played_at, u1.username as p1, m.player1_score as s1, 
                       u2.username as p2, m.player2_score as s2, w.username as winner 
                FROM matches m
                LEFT JOIN users u1 ON m.player1_id = u1.id
                LEFT JOIN users u2 ON m.player2_id = u2.id
                LEFT JOIN users w ON m.winner_id = w.id
                ORDER BY m.played_at DESC LIMIT 50
            ");
            send_json(["success" => true, "matches" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: LIST REPORTS ---
    else if ($action === 'admin_list_reports') {
        if (!isAdmin($conn, $_GET['admin_user'] ?? '')) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        try {
            // Fetch regular reports
            $sql = "SELECT r.id as report_id, r.reason, r.created_at, r.question_id, r.status, r.admin_response, r.user_id,
                           q.question, q.shared_id, q.lang, 
                           u.username,
                           'regular' as report_type
                    FROM reports r 
                    LEFT JOIN questions q ON r.question_id = q.id 
                    LEFT JOIN users u ON r.user_id = u.id 
                    ORDER BY r.created_at DESC";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $regularReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch special reports (from special_reports table)
            $sqlSpecial = "SELECT sr.id as report_id, sr.reason, sr.created_at, sr.question_id, 
                                  'pending' as status, NULL as admin_response, sr.user_id,
                                  NULL as question, NULL as shared_id, NULL as lang,
                                  u.username,
                                  'special' as report_type
                           FROM special_reports sr
                           LEFT JOIN users u ON sr.user_id = u.id
                           ORDER BY sr.created_at DESC";

            $stmtSpecial = $conn->prepare($sqlSpecial);
            $stmtSpecial->execute();
            $specialReports = $stmtSpecial->fetchAll(PDO::FETCH_ASSOC);

            // Merge both report types
            $reports = array_merge($regularReports, $specialReports);

            // Sort by created_at descending
            usort($reports, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Fetch Twin Questions (The other language version) - only for regular reports
            foreach ($reports as &$r) {
                $r['twin'] = null;
                if ($r['report_type'] === 'regular' && !empty($r['shared_id'])) {
                    $otherLang = ($r['lang'] === 'no') ? 'en' : 'no';
                    $stmtTwin = $conn->prepare("SELECT id, question, lang FROM questions WHERE shared_id = ? AND lang = ? LIMIT 1");
                    $stmtTwin->execute([$r['shared_id'], $otherLang]);
                    $twin = $stmtTwin->fetch(PDO::FETCH_ASSOC);
                    if ($twin) {
                        $r['twin'] = $twin;
                    }
                }
            }

            echo json_encode(["success" => true, "reports" => $reports]);
        } catch (Exception $e) {
            echo json_encode(["error" => "DB Error: " . $e->getMessage()]);
        }
    }

    // --- ADMIN: RESOLVE REPORT ---
    else if ($action === 'admin_resolve_report') {
        if (!isAdmin($conn, $input['admin_user'] ?? '')) {
            send_json(["error" => "Unauthorized"]);
        }
        $reportId = $input['report_id'];
        $reportType = $input['report_type'] ?? 'regular';
        $message = isset($input['message']) ? trim($input['message']) : '';

        // 1. Mark as resolved AND save response (triggers bell notification via get_my_reports)
        if ($reportType === 'special') {
            // Special reports table
            // Note: Special table might not have 'admin_response' column yet? 
            // admin_list_reports select "NULL as admin_response" implies it might not exist.
            // We should check DB schema or just assume it doesn't/add it.
            // But for now, let's just mark it resolved if possible.
            // Wait, table created in submit_special_report:
            /* id, question_id, user_id, reason, created_at */
            // No 'status' or 'admin_response'. 
            // We need to ALTER table to support this.
            try {
                $conn->exec("ALTER TABLE special_reports ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
                $conn->exec("ALTER TABLE special_reports ADD COLUMN admin_response TEXT");
            } catch (Exception $e) {
            }

            $stmt = $conn->prepare("UPDATE special_reports SET status = 'resolved', admin_response = ? WHERE id = ?");
            $stmt->execute([$message, $reportId]);
        } else {
            $stmt = $conn->prepare("UPDATE reports SET status = 'resolved', admin_response = ? WHERE id = ?");
            $stmt->execute([$message, $reportId]);
        }

        send_json(["success" => true]);
    }


    // --- ADMIN: DELETE REPORT ---
    else if ($action === 'admin_delete_report') {
        if (!isAdmin($conn, $input['admin_user'] ?? '')) {
            send_json(["error" => "Unauthorized"]);
        }
        $reportId = $input['report_id'];
        $reportType = $input['report_type'] ?? 'regular';

        if ($reportType === 'special') {
            $stmt = $conn->prepare("DELETE FROM special_reports WHERE id = ?");
            $stmt->execute([$reportId]);
        } else {
            $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
            $stmt->execute([$reportId]);
        }
        send_json(["success" => true]);
    }

    // --- ADMIN: DELETE USER ---
    else if ($action === 'admin_delete_user') {
        if (!isAdmin($conn, $input['admin_user'])) {
            send_json(["error" => "Unauthorized"]);
            exit;
        }
        $targetId = intval($input['user_id']);
        if ($targetId <= 0) {
            send_json(["error" => "Invalid ID"]);
            exit;
        }

        try {
            // Check self-delete protection (optional, but good practice)
            // Assuming admin username provided, but we only have ID here. 
            // Let's just trust valid ID.

            // Delete content first (optional, ON DELETE CASCADE usually handles it, but let's be safe)
            // Matches, Scores, Challenges, Notifications...
            // We rely on foreign keys for deep clean, but here is user removal:
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$targetId]);

            if ($stmt->rowCount() > 0) {
                send_json(["success" => true]);
            } else {
                send_json(["error" => "User not found or could not be deleted"]);
            }
        } catch (Exception $e) {
            send_json(["error" => "Delete failed: " . $e->getMessage()]);
        }
    }

    // --- ADMIN: RESET PASSWORD MANUAL ---
    else if ($action === 'admin_reset_password_manual') {
        if (!isAdmin($conn, $input['admin_user'])) {
            send_json(["error" => "Unauthorized"]);
            exit;
        }
        $targetId = intval($input['user_id']);
        $newPass = trim($input['new_password']);

        if (strlen($newPass) < 6) {
            send_json(["error" => "Password too short"]);
            exit;
        }

        try {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $targetId]);
            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => "Reset failed: " . $e->getMessage()]);
        }
    }


    // --- SEND CHALLENGE REQUEST ---
    else if ($action === 'send_challenge_request') {
        $challengerId = isset($input['challenger_id']) ? intval($input['challenger_id']) : null;
        $challengerName = strip_tags(trim(isset($input['challenger_name']) ? $input['challenger_name'] : 'Gjest'));
        // FIX: Ensure null stays null, not 0
        $opponentId = (isset($input['opponent_id']) && $input['opponent_id'] > 0) ? intval($input['opponent_id']) : null;
        $opponentName = strip_tags(trim(isset($input['opponent_name']) ? $input['opponent_name'] : ''));
        $settings = json_encode(isset($input['settings']) ? $input['settings'] : []);

        try {
            // Check if there is already a pending request or active game
            // FIX: Only check limit if opponent is specific (ID > 0)
            if ($opponentId) {
                $check = $conn->prepare("SELECT COUNT(*) FROM asynchronous_challenges WHERE challenger_id = ? AND opponent_id = ? AND status IN ('request', 'accepted', 'pending')");
                $check->execute([$challengerId, $opponentId]);
                if ($check->fetchColumn() >= 3) {
                    echo json_encode(["error" => "Du har allerede 3 aktive utfordringer mot denne spilleren."]);
                    exit;
                }
            }

            // CHECK BLOCKING (Shadow Block)
            // If opponent has blocked challenger, we return success but DO NOTHING.
            $stmtBlock = $conn->prepare("SELECT COUNT(*) FROM social_friends WHERE user_id = ? AND friend_id = ? AND status = 'blocked'");
            $stmtBlock->execute([$opponentId, $challengerId]); // Check if Opponent blocked Challenger
            if ($stmtBlock->fetchColumn() > 0) {
                // Return fake success
                echo json_encode(["success" => true, "challenge_id" => 0, "message" => "Silent block"]);
                exit;
            }

            // CHECK LIMIT (Max 50 active challenges involving this user)
            $stmtCount = $conn->prepare("SELECT COUNT(*) FROM asynchronous_challenges WHERE (challenger_id = ? OR opponent_id = ?) AND status IN ('request', 'accepted', 'pending')");
            $stmtCount->execute([$challengerId, $challengerId]);
            if ($stmtCount->fetchColumn() >= 50) {
                echo json_encode(["error" => "Du har nådd grensen på 50 aktive utfordringer. Fullfør eller slett noen først."]);
                exit;
            }

            // --- DAILY LIMIT FOR RANDOM/NON-FRIEND INVITES ---
            $isFriend = false;
            if ($opponentId) {
                $stmtF = $conn->prepare("SELECT COUNT(*) FROM social_friends WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
                $stmtF->execute([$challengerId, $opponentId]);
                if ($stmtF->fetchColumn() > 0)
                    $isFriend = true;
            }

            if (!$isFriend) {
                $stmtLimit = $conn->prepare("SELECT COUNT(*) FROM asynchronous_challenges 
                                             WHERE challenger_id = ? 
                                             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                             AND (opponent_id IS NULL OR opponent_id NOT IN (
                                                 SELECT friend_id FROM social_friends WHERE user_id = ? AND status = 'accepted'
                                             ))");
                $stmtLimit->execute([$challengerId, $challengerId]);
                if ($stmtLimit->fetchColumn() >= 20) {
                    echo json_encode(["error" => "Du har nådd grensen på 20 tilfeldige utfordringer per døgn."]);
                    exit;
                }
            }
            // -------------------------------------------------

            $stmt = $conn->prepare("INSERT INTO asynchronous_challenges (challenger_id, challenger_name, opponent_id, opponent_name, settings, status, challenger_score, created_at) VALUES (?, ?, ?, ?, ?, ?, NULL, NOW())");
            // Status: 'request' if specific opponent, 'pending' if open/random
            $initialStatus = ($opponentId && $opponentId > 0) ? 'request' : 'pending';
            $stmt->execute([$challengerId, $challengerName, $opponentId, $opponentName, $settings, $initialStatus]);
            $id = $conn->lastInsertId();

            // Notify opponent ONLY if specific
            if ($opponentId && $opponentId > 0) {
                $s = isset($input['settings']) ? $input['settings'] : [];
                $modeStr = (isset($s['mode']) && $s['mode'] === 'race_10') ? "Først til 10" : "Flest på 60s";
                $msg = "$challengerName har sendt deg en utfordring! ($modeStr)";
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmtN->execute([$opponentId, $msg]);
            }

            echo json_encode(["success" => true, "challenge_id" => $id]);
        } catch (Exception $e) {
            echo json_encode(["error" => "Kunne ikke sende forespørsel: " . $e->getMessage()]);
        }
    }

    // --- ACCEPT CHALLENGE ---
    else if ($action === 'accept_challenge') {
        $id = intval($input['id']);
        $opponentId = intval($input['user_id']); // current user is opponent

        try {
            $stmt = $conn->prepare("UPDATE asynchronous_challenges SET status = 'accepted' WHERE id = ? AND opponent_id = ? AND status = 'request'");
            $stmt->execute([$id, $opponentId]);

            if ($stmt->rowCount() > 0) {
                // Notify challenger
                $stmtGe = $conn->prepare("SELECT challenger_id, opponent_name FROM asynchronous_challenges WHERE id = ?");
                $stmtGe->execute([$id]);
                $row = $stmtGe->fetch();
                if ($row) {
                    $oppName = $row['opponent_name'] ?: "Motstanderen";
                    // Fetch settings to show mode
                    $stmtSet = $conn->prepare("SELECT settings FROM asynchronous_challenges WHERE id = ?");
                    $stmtSet->execute([$id]);
                    $cSet = $stmtSet->fetch();
                    $settings = $cSet ? json_decode($cSet['settings'], true) : [];
                    $modeStr = (isset($settings['mode']) && $settings['mode'] === 'race_10') ? "Først til 10" : "Flest på 60s";

                    $msg = "$oppName godtok utfordringen din! ($modeStr)";
                    $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$row['challenger_id'], $msg]);
                }
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["error" => "Kunne ikke godta utfordring (kanskje den er utløpt?)"]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- UPDATE CHALLENGE SETTINGS (For Sync Handshake) ---
    else if ($action === 'update_challenge_settings') {
        $id = intval($input['id']);
        $userId = intval($input['user_id']); // Should be one of the players
        $newSettings = isset($input['settings']) ? $input['settings'] : [];

        try {
            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmt->execute([$id]);
            $c = $stmt->fetch();

            if ($c && ($c['challenger_id'] == $userId || $c['opponent_id'] == $userId)) {
                $currentSettings = json_decode($c['settings'], true) ?: [];
                // Merge new settings intro current (shallow merge)
                $updatedSettings = array_merge($currentSettings, $newSettings);

                $upd = $conn->prepare("UPDATE asynchronous_challenges SET settings = ? WHERE id = ?");
                $upd->execute([json_encode($updatedSettings), $id]);
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["error" => "Unauthorized or not found"]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- DECLINE / CANCEL CHALLENGE ---
    else if ($action === 'decline_challenge') {
        $id = intval($input['id']);
        $userId = intval($input['user_id']);

        try {
            // Allow deletion if:
            // 1. I am Opponent AND status is 'request' (Decline)
            // 2. I am Challenger AND status is 'request' (Cancel)
            // 3. I am Challenger AND status is 'pending' (Give up / Delete stale game)
            // 4. Expiry cleanup handled elsewhere.

            // For simplicity: DELETE the row completely if explicitly cancelled/declined. Or use 'declined'.
            // User requested "delete manual". So DELETE is better.

            // Verify ownership
            $stmtCheck = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmtCheck->execute([$id]);
            $c = $stmtCheck->fetch();

            if (!$c) {
                echo json_encode(["error" => "Utfordring ikke funnet"]);
                exit;
            }

            $allowed = false;
            // Case 1: Opponent declines request
            if ($c['opponent_id'] == $userId && $c['status'] == 'request')
                $allowed = true;
            // Case 2: Challenger cancels request
            if ($c['challenger_id'] == $userId && $c['status'] == 'request')
                $allowed = true;
            // Case 3: Challenger cancels pending (gave up waiting)
            if ($c['challenger_id'] == $userId && $c['status'] == 'pending')
                $allowed = true;

            // NEW Case 4: Allow deleting 'accepted' (waiting for opponent/stale) for BOTH
            // If I am Challenger waiting for Opponent to start (accepted, not played) 
            // OR Opponent waiting for... wait, 'accepted' means Opponent accepted but hasn't played.
            if (($c['challenger_id'] == $userId || $c['opponent_id'] == $userId) && $c['status'] == 'accepted') {
                $allowed = true;
            }

            if ($allowed) {
                // Hard delete to keep DB clean
                $del = $conn->prepare("DELETE FROM asynchronous_challenges WHERE id = ?");
                $del->execute([$id]);
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["error" => "Du kan ikke slette denne utfordringen nå."]);
            }

        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SAVE CHALLENGE SCORE (First player to play) ---
    else if ($action === 'save_challenge_score') {
        $id = intval($input['id']);
        $userId = intval($input['user_id']);
        $score = intval($input['score']);
        $correctQuestionIds = isset($input['correct_question_ids']) ? $input['correct_question_ids'] : [];
        $gameModeOverride = isset($input['game_mode']) ? strip_tags($input['game_mode']) : null;
        $questionsAnswered = isset($input['questions_answered']) ? intval($input['questions_answered']) : 0;

        try {
            // --- INSERT STATS (From save_score logic) ---
            // If game_mode is passed, use it to determine special topic
            $cStmt = $conn->prepare("SELECT settings FROM asynchronous_challenges WHERE id = ?");
            $cStmt->execute([$id]);
            $cData = $cStmt->fetch();
            $settings = $cData ? json_decode($cData['settings'], true) : [];

            // Prefer override if valid (e.g. pres_party), else fall back to settings
            $gameMode = $gameModeOverride ? $gameModeOverride : (isset($settings['mode']) ? $settings['mode'] : 'normal');

            // Determine topic for special stats
            $specialTopic = null;
            if ($gameMode === 'emperors') {
                $specialTopic = 'emperors';
            } else if ($gameMode === 'monarchs') {
                $specialTopic = 'monarchs';
            } else if ($gameMode === 'dynasty_puzzle') {
                $specialTopic = 'dynasty_puzzle_rounds';
            } else if ($gameMode === 'monarch_puzzle') {
                $specialTopic = 'monarch_puzzle_rounds';
            } else if ($gameMode === 'monarch_comp') {
                $specialTopic = 'monarch_time';
            } else if ($gameMode === 'death_detective') {
                $specialTopic = 'death_detective_rounds';
            } else if ($gameMode === 'presidents') {
                $specialTopic = 'presidents';
            } else if ($gameMode === 'pres_time') {
                $specialTopic = 'presidents_puzzle_rounds';
            } else if ($gameMode === 'pres_party') {
                $specialTopic = 'presidents_party_rounds';
            }

            if ($userId && !empty($correctQuestionIds)) {
                $stmtInsertCorrect = $conn->prepare("INSERT IGNORE INTO user_correct_answers (user_id, question_id) VALUES (?, ?)");

                // Create table if not exists (Lazy check)
                $conn->exec("CREATE TABLE IF NOT EXISTS user_special_correct (user_id INT, topic VARCHAR(50), question_id VARCHAR(100), PRIMARY KEY (user_id, topic, question_id))");
                $stmtSpecCorrect = $conn->prepare("INSERT IGNORE INTO user_special_correct (user_id, topic, question_id) VALUES (?, ?, ?)");

                foreach ($correctQuestionIds as $qId) {
                    if (is_numeric($qId)) {
                        $stmtInsertCorrect->execute([$userId, intval($qId)]);
                    } else {
                        $stmtSpecCorrect->execute([$userId, ($specialTopic ?: 'other'), $qId]);
                    }
                }

                // Update Special Stats Count
                if ($specialTopic) {
                    $conn->exec("CREATE TABLE IF NOT EXISTS user_special_stats (user_id INT, topic VARCHAR(50), correct_count INT DEFAULT 0, PRIMARY KEY (user_id, topic))");
                    $increment = count($correctQuestionIds);
                    if ($increment > 0) {
                        $stmtSpecial = $conn->prepare("INSERT INTO user_special_stats (user_id, topic, correct_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE correct_count = correct_count + VALUES(correct_count)");
                        $stmtSpecial->execute([$userId, $specialTopic, $increment]);
                    }
                }
            }

            // Check Badges
            if ($userId && !empty($correctQuestionIds)) {
                require_once 'check_badges.php';
                checkBadges($conn, $userId, $correctQuestionIds);
            }
            // ---------------------------------------------

            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmt->execute([$id]);
            $c = $stmt->fetch();

            if (!$c) {
                echo json_encode(["status" => "error", "error" => "Utfordring ikke funnet"]);
                exit;
            }

            // Determine role & Update Score
            $isChallenger = ($c['challenger_id'] == $userId);
            $isFinished = false;
            $otherId = null;
            $p1Score = null;
            $p2Score = null;
            // Get existing name for opponent if needed
            $optName = $c['opponent_name']; // Might be null/empty

            // Fetch actual username to ensure names are correct (and not NULL/"Spiller")
            $stmtU = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $stmtU->execute([$userId]);
            $currentUsername = $stmtU->fetchColumn();
            if (!$currentUsername)
                $currentUsername = "Ukjent";

            if ($isChallenger) {
                // I am challenger. Updates MY score.
                $p1Score = $score;
                $p2Score = ($c['opponent_score'] !== null) ? intval($c['opponent_score']) : null;
                $otherId = $c['opponent_id'];

                if ($p2Score !== null) {
                    $isFinished = true;
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET challenger_score = ?, challenger_name = ?, status = 'completed' WHERE id = ?");
                } else {
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET challenger_score = ?, challenger_name = ?, status = 'pending' WHERE id = ?");
                }
                $upd->execute([$score, $currentUsername, $id]);
                // Update local var for notification logic
                $c['challenger_name'] = $currentUsername;

            } else {
                // I am opponent.
                $p1Score = ($c['challenger_score'] !== null) ? intval($c['challenger_score']) : null;
                $p2Score = $score;
                $otherId = $c['challenger_id'];

                if ($p1Score !== null) {
                    $isFinished = true;
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET opponent_score = ?, opponent_name = ?, status = 'completed' WHERE id = ?");
                } else {
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET opponent_score = ?, opponent_name = ?, status = 'pending' WHERE id = ?");
                }
                $upd->execute([$score, $currentUsername, $id]);
                // Update local var for notification logic
                $optName = $currentUsername;
            }

            // Common Settings
            $settings = json_decode($c['settings'], true);
            $gameMode = isset($settings['mode']) ? $settings['mode'] : 'normal';

            // --- NOTIFICATION & MATCH LOGIC ---
            $myName = ($isChallenger ? $c['challenger_name'] : ($optName ?: "Motspiller"));

            if ($isFinished) {
                // Game Over. Calculate Winner.
                $winnerId = null;
                $isRace10 = ($gameMode === 'race_10');

                if ($isRace10) {
                    if ($p1Score > 0 && ($p2Score == 0 || $p1Score < $p2Score))
                        $winnerId = $c['challenger_id'];
                    else if ($p2Score > 0 && ($p1Score == 0 || $p2Score < $p1Score))
                        $winnerId = ($isChallenger ? $c['opponent_id'] : $userId);
                } else {
                    if ($p1Score > $p2Score)
                        $winnerId = $c['challenger_id'];
                    else if ($p2Score > $p1Score)
                        $winnerId = ($isChallenger ? $c['opponent_id'] : $userId);
                }

                // Insert into MATCHES (Permanent H2H History) ONCE
                $stmtMatch = $conn->prepare("INSERT INTO matches (player1_id, player2_id, player1_score, player2_score, winner_id, played_at, game_mode) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                $stmtMatch->execute([$c['challenger_id'], ($c['opponent_id'] ?: $userId), $p1Score, $p2Score, $winnerId, $gameMode]);

                // Notify OTHER player about RESULT
                if ($otherId) {
                    $resultPrefix = "Resultat";
                    if ($winnerId && $otherId) {
                        if ($winnerId == $otherId)
                            $resultPrefix = "SEIER! 🏆";
                        else if ($winnerId == $userId)
                            $resultPrefix = "TAP... 💀"; // other lost
                        else
                            $resultPrefix = "UAVGJORT 🤝";
                    } elseif ($winnerId === null) {
                        $resultPrefix = "UAVGJORT 🤝";
                    }

                    $msg = "$resultPrefix mot $myName";
                    if ($isRace10) {
                        $p1T = ($p1Score > 0) ? ($p1Score / 10) . "s" : "DNF";
                        $p2T = ($p2Score > 0) ? ($p2Score / 10) . "s" : "DNF";
                        $msg .= ": P1 $p1T - P2 $p2T";
                    } else {
                        $msg .= ": $p1Score - $p2Score";
                    }

                    $conn->prepare("INSERT INTO notifications (user_id, message, related_id) VALUES (?, ?, ?)")->execute([$otherId, $msg, $id]);
                }

            } else {
                // Game NOT finished. Notify OTHER player it's their turn
                if ($otherId) {
                    $modeStr = ($gameMode === 'race_10') ? "Først til 10" : "Flest på 60s";
                    $msg = "Din tur! $myName har spilt. ($modeStr)";
                    $conn->prepare("INSERT INTO notifications (user_id, message, related_id) VALUES (?, ?, ?)")->execute([$otherId, $msg, $id]);
                }
            }

            // Record in highscores table too
            $stmtH = $conn->prepare("INSERT INTO highscores (user_id, name, score, game_mode, date) VALUES (?, ?, ?, ?, NOW())");
            $stmtH->execute([$userId, $currentUsername, $score, $gameMode]);

            // Calculate Rank
            if ($gameMode === 'race_10') {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score < ? AND game_mode = 'race_10'");
            } else {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score > ? AND (game_mode = ? OR (? IN ('normal', 'timed_60') AND (game_mode IN ('normal', 'timed_60') OR game_mode IS NULL)))");
            }
            $rankStmt->execute([$score, $gameMode, $gameMode]);
            $rank = $rankStmt->fetch()['rank'];

            // Update user stats
            if ($gameMode !== 'race_10') {
                $conn->prepare("UPDATE users SET total_score = total_score + ?, games_played = games_played + 1 WHERE id = ?")->execute([$score, $userId]);
            } else {
                $conn->prepare("UPDATE users SET games_played = games_played + 1 WHERE id = ?")->execute([$userId]);
            }

            // Return updated challenge
            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmt->execute([$id]);
            $updated = $stmt->fetch();
            $updated['settings'] = json_decode($updated['settings'], true);
            // Manually set status for response if we just completed it (in case DB trigger is weird or race condition)
            $updated['status'] = $isFinished ? 'completed' : $updated['status'];

            // Fetch updated user stats for immediate UI sync
            $updatedUser = refresh_user($conn, $userId);
            $uStats = [
                "total_score" => $updatedUser['total_score'],
                "games_played" => $updatedUser['games_played'],
                "correct_count" => $updatedUser['correct_count']
            ];

            send_json([
                "success" => true,
                "challenge" => $updated,
                "rank" => $rank,
                "user_stats" => $uStats,
                "topic_counts" => $updatedUser['topic_counts']
            ]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- LOG CLIENT (Debug) ---
    else if ($action === 'log_client') {
        $msg = $input['message'];
        $uid = isset($input['user_id']) ? $input['user_id'] : 'Anon';
        log_sync("[CLIENT $uid] $msg");
        echo json_encode(["success" => true]);
    }

    // --- GET CHALLENGE ---
    else if ($action === 'get_challenge') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($input['id']) ? intval($input['id']) : 0);
        try {
            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmt->execute([$id]);
            $challenge = $stmt->fetch();
            if ($challenge) {
                log_sync("get_challenge: ID=$id Settings=" . $challenge['settings']);
                $challenge['settings'] = json_decode($challenge['settings'], true);
                echo json_encode(["success" => true, "challenge" => $challenge]);
            } else {
                echo json_encode(["error" => "Utfordring ikke funnet"]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- UPDATE CHALLENGE SETTINGS (Sync Handshake) ---
    else if ($action === 'update_challenge_settings') {
        $id = intval($input['id']);
        $newSettings = $input['settings']; // Expected to include q_shared_ids
        log_sync("update_challenge_settings: ID=$id Payload=" . json_encode($newSettings));

        try {
            // First fetch existing to merge
            $stmt = $conn->prepare("SELECT settings FROM asynchronous_challenges WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();

            if ($current) {
                $settings = json_decode($current['settings'], true);
                if (!$settings)
                    $settings = [];
                // Merge new settings (e.g. q_shared_ids)
                $settings = array_merge($settings, $newSettings);

                $upd = $conn->prepare("UPDATE asynchronous_challenges SET settings = ? WHERE id = ?");
                $upd->execute([json_encode($settings), $id]);

                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["error" => "Challenge not found"]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- COMPLETE CHALLENGE ---
    else if ($action === 'complete_challenge') {
        $id = intval($input['id']);
        $userId = intval($input['user_id']);
        $score = intval($input['score']);
        $correctQuestionIds = isset($input['correct_question_ids']) ? $input['correct_question_ids'] : [];

        log_sync("complete_challenge: ID=$id User=$userId Score=$score");

        $optId = isset($input['opponent_id']) ? intval($input['opponent_id']) : null;
        $optName = strip_tags(trim(isset($input['opponent_name']) ? $input['opponent_name'] : ''));

        try {
            $stmtC = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmtC->execute([$id]);
            $c = $stmtC->fetch();

            if (!$c) {
                echo json_encode(["error" => "Utfordring ikke funnet"]);
                exit;
            }

            $isChallenger = ($c['challenger_id'] == $userId);
            $isFinished = false;
            $otherId = null;
            $p1Score = null;
            $p2Score = null;

            if ($isChallenger) {
                // I am challenger. Updates MY score.
                $p1Score = $score;
                $p2Score = ($c['opponent_score'] !== null) ? intval($c['opponent_score']) : null;
                $otherId = $c['opponent_id']; // Might be null if public link? No, async usually has id.

                // If opponent score exists (not null), game is finished.
                if ($p2Score !== null) {
                    $isFinished = true;
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET challenger_score = ?, status = 'completed' WHERE id = ?");
                } else {
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET challenger_score = ? WHERE id = ?");
                }
                $upd->execute([$score, $id]);

            } else {
                // I am opponent.
                $p1Score = ($c['challenger_score'] !== null) ? intval($c['challenger_score']) : null;
                $p2Score = $score;
                $otherId = $c['challenger_id'];

                // If challenger score exists (not null), game is finished.
                if ($p1Score !== null) {
                    $isFinished = true;
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET opponent_id = IFNULL(opponent_id, ?), opponent_name = IFNULL(opponent_name, ?), opponent_score = ?, status = 'completed' WHERE id = ?");
                } else {
                    $upd = $conn->prepare("UPDATE asynchronous_challenges SET opponent_id = IFNULL(opponent_id, ?), opponent_name = IFNULL(opponent_name, ?), opponent_score = ? WHERE id = ?");
                }
                $upd->execute([$userId, $optName, $score, $id]);
            }

            log_sync("complete_challenge: ID=$id P1=$p1Score P2=$p2Score Finished=" . ($isFinished ? 'YES' : 'NO'));

            // Common Settings
            $settings = json_decode($c['settings'], true);
            $settingsArr = $settings; // Alias
            $gameMode = isset($settings['mode']) ? $settings['mode'] : 'normal';

            // --- NOTIFICATION & MATCH LOGIC ---
            $myName = ($isChallenger ? $c['challenger_name'] : ($optName ?: "Motspiller"));

            if ($isFinished) {
                // Game Over. Calculate Winner.
                $winnerId = null;
                $isRace10 = ($gameMode === 'race_10');

                if ($isRace10) {
                    if ($p1Score > 0 && ($p2Score == 0 || $p1Score < $p2Score))
                        $winnerId = $c['challenger_id'];
                    else if ($p2Score > 0 && ($p1Score == 0 || $p2Score < $p1Score))
                        $winnerId = ($isChallenger ? $c['opponent_id'] : $userId);
                } else {
                    if ($p1Score > $p2Score)
                        $winnerId = $c['challenger_id'];
                    else if ($p2Score > $p1Score)
                        $winnerId = ($isChallenger ? $c['opponent_id'] : $userId);
                }

                // Insert into MATCHES (Permanent H2H History) ONCE
                $stmtMatch = $conn->prepare("INSERT INTO matches (player1_id, player2_id, player1_score, player2_score, winner_id, played_at, game_mode) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                $stmtMatch->execute([$c['challenger_id'], ($c['opponent_id'] ?: $userId), $p1Score, $p2Score, $winnerId, $gameMode]);

                // Notify OTHER player about RESULT
                if ($otherId) {
                    $resultPrefix = "Resultat";
                    if ($winnerId && $otherId) {
                        if ($winnerId == $otherId)
                            $resultPrefix = "SEIER!";
                        else if ($winnerId == $userId)
                            $resultPrefix = "TAP..."; // other lost
                        else
                            $resultPrefix = "UAVGJORT";
                    } elseif ($winnerId === null) {
                        $resultPrefix = "UAVGJORT";
                    }

                    $msg = "$resultPrefix mot $myName";
                    // Add scores to message
                    if ($isRace10) {
                        $p1T = ($p1Score > 0) ? ($p1Score / 10) . "s" : "DNF";
                        $p2T = ($p2Score > 0) ? ($p2Score / 10) . "s" : "DNF";
                        $msg .= ": P1 $p1T - P2 $p2T";
                    } else {
                        $msg .= ": $p1Score - $p2Score";
                    }

                    $conn->prepare("INSERT INTO notifications (user_id, message, related_id) VALUES (?, ?, ?)")->execute([$otherId, $msg, $id]);
                }

            } else {
                // Game NOT finished. Notify OTHER player it's their turn (if they haven't played).
                // Wait, if I just played, and the other HAS NOT played (since !isFinished), I should tell them "Din tur!".
                // But if they have already played... wait. If they have played, isFinished would be true (since I just played).
                // So if !isFinished, it implies the other player score IS NULL.

                if ($otherId) {
                    $msg = "Din tur! $myName har spilt.";
                    if ($gameMode === 'race_10')
                        $msg .= " (Race 10)";
                    $conn->prepare("INSERT INTO notifications (user_id, message, related_id) VALUES (?, ?, ?)")->execute([$otherId, $msg, $id]);
                }
            }


            // Record INDIVIDUAL Highscore (Always)
            $stmtH = $conn->prepare("INSERT INTO highscores (user_id, name, score, game_mode, date) VALUES (?, ?, ?, ?, NOW())");
            $stmtH->execute([$userId, ($isChallenger ? $c['challenger_name'] : ($optName ?: "Spiller")), $score, $gameMode]);

            // Calculate Rank
            if ($gameMode === 'race_10') {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score < ? AND game_mode = 'race_10'");
            } else {
                $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM highscores WHERE score > ? AND (game_mode = ? OR (? IN ('normal', 'timed_60') AND (game_mode IN ('normal', 'timed_60') OR game_mode IS NULL)))");
            }
            $rankStmt->execute([$score, $gameMode, $gameMode]);
            $rank = $rankStmt->fetch()['rank'];

            // Update user stats
            if ($gameMode !== 'race_10') {
                $conn->prepare("UPDATE users SET total_score = total_score + ?, games_played = games_played + 1 WHERE id = ?")->execute([$score, $userId]);
            } else {
                $conn->prepare("UPDATE users SET games_played = games_played + 1 WHERE id = ?")->execute([$userId]);
            }

            // Return Updated Challenge Structure for Frontend
            // If finished, status is completed. If not, it's accepted.
            // Frontend needs to know if I should show "Result" or "Waiting".
            // We verify by re-fetching or constructing.
            $c['status'] = $isFinished ? 'completed' : 'accepted'; // Manual update for response

            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE id = ?");
            $stmt->execute([$id]);
            $updated = $stmt->fetch();
            $updated['settings'] = json_decode($updated['settings'], true);

            $updated = $stmt->fetch();
            $updated['settings'] = json_decode($updated['settings'], true);

            // Check Badges
            $newBadges = [];
            if ($userId && !empty($correctQuestionIds)) {
                $newBadges = checkBadges($conn, $userId, $correctQuestionIds);
            }

            echo json_encode(["success" => true, "challenge" => $updated, "rank" => $rank, "new_badges" => $newBadges]);
        } catch (Exception $e) {
            echo json_encode(["error" => "Kunne ikke fullføre utfordring: " . $e->getMessage()]);
        }
    }



    // --- FIND RANDOM USER (FOR CHALLENGE) ---
    else if ($action === 'pick_random_user') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // Select user who is NOT me AND does NOT have a pending challenge FROM me
            $stmt = $conn->prepare("
                SELECT id, username 
                FROM users 
                WHERE id != ? 
                AND id NOT IN (
                    SELECT opponent_id FROM asynchronous_challenges 
                    WHERE challenger_id = ? AND status IN ('pending', 'request', 'accepted') AND opponent_id IS NOT NULL
                )
                ORDER BY RAND() LIMIT 1
            ");
            $stmt->execute([$userId, $userId]);
            $user = $stmt->fetch();
            if ($user) {
                echo json_encode(["success" => true, "user" => $user]);
            } else {
                echo json_encode(["error" => "Ingen tilgjengelige spillere (som du ikke alt har utfordret)"]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- FIND RANDOM CHALLENGE (Legacy/Join) ---
    else if ($action === 'find_random_challenge') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // Find a pending challenge that I didn't create and hasn't been taken
            $stmt = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE status = 'pending' AND (challenger_id != ? OR challenger_id IS NULL) AND opponent_id IS NULL ORDER BY RAND() LIMIT 1");
            $stmt->execute([$userId]);
            $challenge = $stmt->fetch();
            if ($challenge) {
                $challenge['settings'] = json_decode($challenge['settings'], true);
                echo json_encode(["success" => true, "challenge" => $challenge]);
            } else {
                echo json_encode(["error" => "Ingen åpne utfordringer fra andre. Vil du starte en selv?"]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }


    // --- SOCIAL: SEARCH USERS ---
    else if ($action === 'search_users') {
        $query = strip_tags(trim(isset($_GET['q']) ? $_GET['q'] : (isset($input['q']) ? $input['q'] : '')));
        if (strlen($query) < 2) {
            echo json_encode(["success" => true, "users" => []]);
            exit;
        }
        try {
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE username LIKE ? LIMIT 10");
            $stmt->execute(["%$query%"]);
            echo json_encode(["success" => true, "users" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- REFRESH USER SESSION ---
    else if ($action === 'refresh_user') {
        $userId = isset($input['user_id']) ? intval($input['user_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);

        file_put_contents('debug_auth.log', date('[Y-m-d H:i:s] ') . "Refresh request for UID: $userId\n", FILE_APPEND);

        if (!$userId) {
            echo json_encode(["error" => "Missing user_id"]);
            exit;
        }

        try {
            // CHECK BADGES (Retrospective Fix)
            require_once 'check_badges.php';
            checkBadges($conn, $userId);

            $u = refresh_user($conn, $userId);
            if ($u) {
                file_put_contents('debug_auth.log', "User found: " . json_encode($u) . "\n", FILE_APPEND);
                echo json_encode(["success" => true, "user" => $u]);
            } else {
                file_put_contents('debug_auth.log', "User NOT found\n", FILE_APPEND);
                echo json_encode(["error" => "User not found (ID: $userId)"]);
            }
        } catch (Exception $e) {
            file_put_contents('debug_auth.log', "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(["error" => "Refresh failed: " . $e->getMessage()]);
        }
    }

    // --- ADMIN: GET ALL USERS WITH BADGE STATS ---
    else if ($action === 'admin_get_users_badges') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        try {
            // Get all users with badge counts and topic progress
            $stmt = $conn->query("
                SELECT 
                    u.id,
                    u.username,
                    u.total_score,
                    u.games_played,
                    (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count,
                    (SELECT COUNT(*) FROM user_correct_answers uca JOIN questions q ON uca.question_id = q.id WHERE uca.user_id = u.id) as total_correct
                FROM users u
                ORDER BY u.id ASC
            ");
            $users = $stmt->fetchAll();

            echo json_encode(["success" => true, "users" => $users]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: GET USER BADGE DETAIL ---
    else if ($action === 'admin_get_user_badge_detail') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $targetUserId = intval($input['target_user_id']);

        try {
            // Get user info
            $stmtUser = $conn->prepare("SELECT id, username, total_score, games_played FROM users WHERE id = ?");
            $stmtUser->execute([$targetUserId]);
            $user = $stmtUser->fetch();

            if (!$user) {
                echo json_encode(["error" => "User not found"]);
                exit;
            }

            // Get total unique correct answers
            $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM user_correct_answers uca JOIN questions q ON uca.question_id = q.id WHERE uca.user_id = ?");
            $stmtTotal->execute([$targetUserId]);
            $totalCorrect = $stmtTotal->fetchColumn();

            // Get topic counts
            $stmtTopic = $conn->prepare("
                SELECT q.topic, COUNT(*) as count 
                FROM user_correct_answers uca 
                JOIN questions q ON uca.question_id = q.id 
                WHERE uca.user_id = ? 
                GROUP BY q.topic
            ");
            $stmtTopic->execute([$targetUserId]);
            $topicCounts = $stmtTopic->fetchAll(PDO::FETCH_KEY_PAIR);

            // Get owned badges
            $stmtBadges = $conn->prepare("SELECT badge_id, awarded_at FROM user_badges WHERE user_id = ?");
            $stmtBadges->execute([$targetUserId]);
            $ownedBadges = $stmtBadges->fetchAll(PDO::FETCH_KEY_PAIR);

            // Define all badges (same as checkBadges)
            $allBadges = [
                // Global
                ['id' => 'wallace', 'threshold' => 100, 'topic' => 'global', 'category' => 'Global'],
                ['id' => 'joan', 'threshold' => 250, 'topic' => 'global', 'category' => 'Global'],
                ['id' => 'napoleon', 'threshold' => 500, 'topic' => 'global', 'category' => 'Global'],
                ['id' => 'caesar', 'threshold' => 750, 'topic' => 'global', 'category' => 'Global'],
                ['id' => 'alexander', 'threshold' => 1000, 'topic' => 'global', 'category' => 'Global'],

                // Makt & Konflikt
                ['id' => 'leonidas', 'threshold' => 50, 'topic' => 'makt', 'category' => 'Makt & Konflikt'],
                ['id' => 'sun_tzu', 'threshold' => 100, 'topic' => 'makt', 'category' => 'Makt & Konflikt'],
                ['id' => 'boudica', 'threshold' => 150, 'topic' => 'makt', 'category' => 'Makt & Konflikt'],
                ['id' => 'churchill', 'threshold' => 200, 'topic' => 'makt', 'category' => 'Makt & Konflikt'],
                ['id' => 'djenghis_khan', 'threshold' => 500, 'topic' => 'makt', 'category' => 'Makt & Konflikt'],

                // Kultur & Identitet
                ['id' => 'frida_kahlo', 'threshold' => 50, 'topic' => 'kultur', 'category' => 'Kultur & Identitet'],
                ['id' => 'da_vinci', 'threshold' => 100, 'topic' => 'kultur', 'category' => 'Kultur & Identitet'],
                ['id' => 'mozart', 'threshold' => 150, 'topic' => 'kultur', 'category' => 'Kultur & Identitet'],
                ['id' => 'shakespeare', 'threshold' => 200, 'topic' => 'kultur', 'category' => 'Kultur & Identitet'],
                ['id' => 'aristoteles', 'threshold' => 500, 'topic' => 'kultur', 'category' => 'Kultur & Identitet'],

                // Sosiale forhold
                ['id' => 'nightingale', 'threshold' => 50, 'topic' => 'dagligliv', 'category' => 'Sosiale forhold'],
                ['id' => 'tubman', 'threshold' => 100, 'topic' => 'dagligliv', 'category' => 'Sosiale forhold'],
                ['id' => 'parks', 'threshold' => 150, 'topic' => 'dagligliv', 'category' => 'Sosiale forhold'],
                ['id' => 'gandhi', 'threshold' => 200, 'topic' => 'dagligliv', 'category' => 'Sosiale forhold'],
                ['id' => 'mandela', 'threshold' => 500, 'topic' => 'dagligliv', 'category' => 'Sosiale forhold'],

                // Oppdagelser & Vitenskap
                ['id' => 'galileo', 'threshold' => 50, 'topic' => 'oppdagelser', 'category' => 'Oppdagelser & Vitenskap'],
                ['id' => 'curie', 'threshold' => 100, 'topic' => 'oppdagelser', 'category' => 'Oppdagelser & Vitenskap'],
                ['id' => 'darwin', 'threshold' => 150, 'topic' => 'oppdagelser', 'category' => 'Oppdagelser & Vitenskap'],
                ['id' => 'einstein', 'threshold' => 200, 'topic' => 'oppdagelser', 'category' => 'Oppdagelser & Vitenskap'],
                ['id' => 'newton', 'threshold' => 500, 'topic' => 'oppdagelser', 'category' => 'Oppdagelser & Vitenskap'],
            ];

            // Topic mapping (same as checkBadges)
            $topicMap = [
                'makt' => 'Makt & Konflikt',
                'kultur' => 'Kultur & Identitet',
                'dagligliv' => 'Sosiale forhold',
                'oppdagelser' => 'Oppdagelser & Vitenskap'
            ];

            // Build badge progress info
            $badgeProgress = [];
            foreach ($allBadges as $b) {
                $dbTopicName = ($b['topic'] === 'global') ? 'global' : (isset($topicMap[$b['topic']]) ? $topicMap[$b['topic']] : $b['topic']);
                $currentCount = ($b['topic'] === 'global') ? $totalCorrect : (isset($topicCounts[$dbTopicName]) ? $topicCounts[$dbTopicName] : 0);

                $badgeProgress[] = [
                    'id' => $b['id'],
                    'category' => $b['category'],
                    'threshold' => $b['threshold'],
                    'current_count' => $currentCount,
                    'progress_percent' => round(($currentCount / $b['threshold']) * 100, 1),
                    'owned' => isset($ownedBadges[$b['id']]),
                    'earned_at' => isset($ownedBadges[$b['id']]) ? $ownedBadges[$b['id']] : null
                ];
            }

            echo json_encode([
                "success" => true,
                "user" => $user,
                "total_correct" => $totalCorrect,
                "topic_counts" => $topicCounts,
                "badge_progress" => $badgeProgress
            ]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: ADD FRIEND ---
    else if ($action === 'add_friend') {
        $userId = intval($input['user_id']);
        $friendId = intval($input['friend_id']);
        if ($userId === $friendId)
            exit;

        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS social_friends (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                friend_id INT NOT NULL,
                status VARCHAR(20) DEFAULT 'accepted',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_friend (user_id, friend_id)
            )");

            $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message TEXT,
                is_read TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Start as 'request' unless other way exists?
            // Simple approach: Always 'request'.
            // Check if reverse request exists?
            $check = $conn->prepare("SELECT status FROM social_friends WHERE user_id = ? AND friend_id = ?");
            $check->execute([$friendId, $userId]);
            $reverse = $check->fetch();

            if ($reverse && $reverse['status'] === 'request') {
                // They requested me, and I requested them -> ACCEPT BOTH
                $conn->exec("UPDATE social_friends SET status = 'accepted' WHERE user_id = $friendId AND friend_id = $userId");
                $stmt = $conn->prepare("INSERT IGNORE INTO social_friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                $stmt->execute([$userId, $friendId]);

                $msg = "$myName godtok venneforespørselen din!";
                $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$friendId, $msg]);
                echo json_encode(["success" => true, "status" => "accepted"]);
                exit;
            }

            // Normal request
            $stmt = $conn->prepare("INSERT IGNORE INTO social_friends (user_id, friend_id, status) VALUES (?, ?, 'request')");
            $stmt->execute([$userId, $friendId]);

            // Notify
            $stmtU = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $stmtU->execute([$userId]);
            $myName = $stmtU->fetchColumn() ?: "En bruker";

            $msg = "$myName vil bli venn med deg!";
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmtN->execute([$friendId, $msg]);

            echo json_encode(["success" => true, "status" => "request"]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: ACCEPT FRIEND ---
    else if ($action === 'accept_friend') {
        $userId = intval($input['user_id']);
        $friendId = intval($input['friend_id']);
        try {
            // Update their request to accepted
            $stmt = $conn->prepare("UPDATE social_friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ? AND status = 'request'");
            $stmt->execute([$friendId, $userId]);
            $updated = $stmt->rowCount();

            // Insert/Update my side to accepted
            $stmt = $conn->prepare("INSERT INTO social_friends (user_id, friend_id, status) VALUES (?, ?, 'accepted') 
                                    ON DUPLICATE KEY UPDATE status = 'accepted'");
            $stmt->execute([$userId, $friendId]);
            $inserted = $stmt->rowCount();

            // Notify
            // Get my username
            $stmtU = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $stmtU->execute([$userId]);
            $myName = $stmtU->fetchColumn() ?: "En bruker";

            $msg = "$myName er nå vennen din!";
            $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$friendId, $msg]);

            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: LIST FRIEND REQUESTS ---
    else if ($action === 'list_friend_requests') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // Requests sent TO me (where user_id is the SENDER, friend_id is ME)
            // Return u.id directly so frontend map(r => r.id) works
            $stmt = $conn->prepare("
                SELECT u.id, u.username 
                FROM social_friends f 
                JOIN users u ON f.user_id = u.id 
                WHERE f.friend_id = ? AND f.status = 'request'
            ");
            $stmt->execute([$userId]);
            send_json(["success" => true, "requests" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }


    // --- SOCIAL: REMOVE FRIEND ---
    else if ($action === 'remove_friend') {
        $userId = intval($input['user_id']);
        $friendId = intval($input['friend_id']);

        try {
            // Remove both directions
            $stmt = $conn->prepare("DELETE FROM social_friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->execute([$userId, $friendId, $friendId, $userId]);
            send_json(["success" => true]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: LIST FRIENDS ---
    else if ($action === 'list_friends') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // Only 'accepted' friends
            $stmt = $conn->prepare("SELECT u.id, u.username FROM users u JOIN social_friends f ON u.id = f.friend_id WHERE f.user_id = ? AND f.status = 'accepted'");
            $stmt->execute([$userId]);
            send_json(["success" => true, "friends" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            send_json(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: LIST BLOCKED USERS ---
    else if ($action === 'list_blocked') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            $stmt = $conn->prepare("SELECT u.id, u.username FROM users u JOIN social_friends f ON u.id = f.friend_id WHERE f.user_id = ? AND f.status = 'blocked'");
            $stmt->execute([$userId]);
            echo json_encode(["success" => true, "blocked" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: BLOCK USER ---
    else if ($action === 'block_user') {
        $userId = intval($input['user_id']);
        $blockId = intval($input['block_id']);

        if ($userId === $blockId)
            exit;

        try {
            // Upsert: If friend row exists, update to blocked. If not, insert blocked.
            // Note: We only care about user->blockId direction for blocking logic usually, 
            // but we might want to kill the friendship reverse direction too?
            // For now, let's just set MY relation to blocked.

            // Check if exists
            $check = $conn->prepare("SELECT id FROM social_friends WHERE user_id = ? AND friend_id = ?");
            $check->execute([$userId, $blockId]);
            if ($check->fetch()) {
                $stmt = $conn->prepare("UPDATE social_friends SET status = 'blocked' WHERE user_id = ? AND friend_id = ?");
                $stmt->execute([$userId, $blockId]);
            } else {
                $stmt = $conn->prepare("INSERT INTO social_friends (user_id, friend_id, status) VALUES (?, ?, 'blocked')");
                $stmt->execute([$userId, $blockId]);
            }

            // Also remove reverse friendship if it exists, so they don't see me as friend anymore
            $stmtDel = $conn->prepare("DELETE FROM social_friends WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
            $stmtDel->execute([$blockId, $userId]);

            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SOCIAL: UNBLOCK USER ---
    else if ($action === 'unblock_user') {
        $userId = intval($input['user_id']);
        $blockId = intval($input['block_id']);

        try {
            // Restore friendship (both ways)
            // 1. Me -> Them: Set to accepted (was blocked)
            $conn->prepare("UPDATE social_friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?")->execute([$userId, $blockId]);

            // 2. Them -> Me: Restore connection (was deleted)
            $conn->prepare("INSERT IGNORE INTO social_friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')")->execute([$blockId, $userId]);

            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }


    // --- CREATE INVITE ---
    else if ($action === 'create_invite') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        if ($userId <= 0) {
            echo json_encode(["error" => "Invalid user"]);
            exit;
        }

        try {
            // Cleanup old invites (Inside try to avoid 500 if table missing)
            try {
                $conn->query("DELETE FROM game_invites WHERE created_at < NOW() - INTERVAL 48 HOUR");
            } catch (Exception $e) {
            }

            if (function_exists('random_bytes')) {
                $token = bin2hex(random_bytes(16));
            } else {
                $token = bin2hex(openssl_random_pseudo_bytes(16));
            }

            $stmt = $conn->prepare("INSERT INTO game_invites (token, sender_id) VALUES (?, ?)");
            $stmt->execute([$token, $userId]);
            echo json_encode(["success" => true, "token" => $token]);
        } catch (Exception $e) {
            echo json_encode(["error" => "Could not create invite: " . $e->getMessage()]);
        }
    }

    // --- CHECK INVITE ---
    else if ($action === 'check_invite') {
        $token = isset($_GET['token']) ? $_GET['token'] : (isset($input['token']) ? $input['token'] : '');
        if (!$token) {
            echo json_encode(["error" => "No token"]);
            exit;
        }

        $stmt = $conn->prepare("SELECT i.*, u.username as sender_name FROM game_invites i JOIN users u ON i.sender_id = u.id WHERE i.token = ? AND i.used = 0");
        $stmt->execute([$token]);
        $invite = $stmt->fetch();

        if ($invite) {
            echo json_encode(["success" => true, "invite" => $invite]);
        } else {
            echo json_encode(["success" => false, "error" => "Ugyldig eller brukt invitasjon."]);
        }
    }

    // --- USE INVITE (Mark as used) ---
    else if ($action === 'use_invite') {
        $token = isset($_GET['token']) ? $_GET['token'] : (isset($input['token']) ? $input['token'] : '');
        $stmt = $conn->prepare("UPDATE game_invites SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        echo json_encode(["success" => true]);
    }

    // --- GET NOTIFICATIONS ---
    else if ($action === 'get_notifications') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            // Check table exists first
            $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
message TEXT,
is_read TINYINT DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

            // Check if we need to add related_id column (SAFE MIGRATION)
            try {
                $conn->exec("ALTER TABLE notifications ADD COLUMN related_id INT DEFAULT NULL");
            } catch (Exception $e) { /* Column likely exists */
            }

            // Check if we need to add reminders_sent column
            try {
                $conn->exec("ALTER TABLE asynchronous_challenges ADD COLUMN reminders_sent INT DEFAULT 0");
            } catch (Exception $e) { /* Column likely exists */
            }

            // PROCESS REMINDERS FOR THIS USER (Lazy check)
// Find pending challenges where I am opponent
            $stmtC = $conn->prepare("SELECT * FROM asynchronous_challenges WHERE opponent_id = ? AND status = 'pending'");
            $stmtC->execute([$userId]);
            $pending = $stmtC->fetchAll();

            $now = new DateTime();
            foreach ($pending as $p) {
                $created = new DateTime($p['created_at']);
                $diff = $now->diff($created);
                $hours = $diff->h + ($diff->days * 24);
                $sent = intval($p['reminders_sent']);

                // 24h, 48h, 72h reminders
                $shouldRemind = false;
                if ($hours >= 24 && $sent == 0)
                    $shouldRemind = true;
                else if ($hours >= 48 && $sent == 1)
                    $shouldRemind = true;
                else if ($hours >= 72 && $sent == 2)
                    $shouldRemind = true;

                if ($shouldRemind) {
                    $challengerName = "En spiller";
                    // Fetch challenger name
                    $stmtU = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $stmtU->execute([$p['challenger_id']]);
                    $row = $stmtU->fetch();
                    if ($row)
                        $challengerName = $row['username'];

                    $msg = "Påminnelse: Du har en ventende utfordring fra $challengerName!";
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $stmtN->execute([$userId, $msg]);

                    // Update count
                    $stmtUp = $conn->prepare("UPDATE asynchronous_challenges SET reminders_sent = reminders_sent + 1 WHERE id = ?");
                    $stmtUp->execute([$p['id']]);
                }
            }

            $stmt = $conn->prepare("SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = 0 
                AND (created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) OR (message NOT LIKE 'Badge:%' AND message NOT LIKE 'Nesten:%'))
                ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();

            // INJECT SYSTEM NOTIFICATION (Badges) - Stored in DB with 14-day expiry
            // Check if badge notification already exists for this user
            $checkBadge = $conn->prepare("SELECT id FROM notifications WHERE user_id = ? AND message LIKE 'Nyhet! Vi har innført%'");
            $checkBadge->execute([$userId]);
            $existingBadge = $checkBadge->fetch();

            if (!$existingBadge) {
                // Create badge notification in database (will auto-delete after 14 days via the query above)
                $insertBadge = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                $insertBadge->execute([$userId, "Nyhet! Vi har innført utmerkelser!"]);
            }

            // Re-fetch notifications to include the newly created badge notification
            $stmt = $conn->prepare("SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = 0 
                AND (created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) OR (message NOT LIKE 'Badge:%' AND message NOT LIKE 'Nesten:%'))
                ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();

            // Add type and is_gold fields to badge notification for client-side display
            foreach ($notifications as &$notif) {
                if (strpos($notif['message'], 'Nyhet! Vi har innført') !== false) {
                    $notif['type'] = 'system';
                    $notif['is_gold'] = true;
                }
            }

            echo json_encode([
                "success" => true,
                "notifications" => $notifications,
                "debug" => [
                    "user_id_interpreted" => $userId,
                    "get_user_id" => isset($_GET['user_id']) ? $_GET['user_id'] : 'NOT_SET',
                    "post_user_id" => isset($input['user_id']) ? $input['user_id'] : 'NOT_SET',
                    "server_time" => date('Y-m-d H:i:s'),
                    "action" => $action,
                    "version" => "1.7.9m"
                ]
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- MARK NOTIFICATIONS READ ---
    else if ($action === 'mark_notifications_read') {
        $userId = intval($input['user_id']);
        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- MARK SINGLE NOTI READ ---
    else if ($action === 'mark_notification_read_single') {
        $userId = intval($input['user_id']);
        $notifId = intval($input['notif_id']);

        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?");
            $stmt->execute([$userId, $notifId]);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- MARK RELATED NOTIFICATIONS READ (Cleanup on game open) ---
    else if ($action === 'mark_related_notifications_read') {
        $userId = intval($input['user_id']);
        $relId = intval($input['related_id']);

        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_id = ?");
            $stmt->execute([$userId, $relId]);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: LIST ALL MATCHES ---
    else if ($action === 'admin_list_matches') {
        if (!isAdmin($conn, $_GET['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        try {
            $stmt = $conn->prepare("
                SELECT m.*, 
                u1.username as player1_name, 
                u2.username as player2_name,
                w.username as winner_name
                FROM matches m
                JOIN users u1 ON m.player1_id = u1.id
                JOIN users u2 ON m.player2_id = u2.id
                LEFT JOIN users w ON m.winner_id = w.id
                ORDER BY m.played_at DESC
                LIMIT 100
            ");
            $stmt->execute();
            $matches = $stmt->fetchAll();

            echo json_encode(["success" => true, "matches" => $matches]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- GET H2H STATS ---
    else if ($action === 'get_h2h_stats') {
        $userId = intval($_GET['user_id']); // Me

        try {
            // FIX: Ensure 'game_mode' column exists before trying to insert into it during lazy migration
            try {
                $conn->exec("ALTER TABLE matches ADD COLUMN game_mode VARCHAR(50) DEFAULT 'normal'");
            } catch (Exception $e) {
            }

            // --- LAZY MIGRATION END (Use migrate_history.php) ---

            // 1. Fetch Aggregated Stats (Wins/Losses/Draws)
            $stmtStats = $conn->prepare("
                SELECT 
                    CASE 
                        WHEN player1_id = ? THEN player2_id 
                        ELSE player1_id 
                    END as opponent_id,
                    SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN winner_id != ? AND winner_id IS NOT NULL THEN 1 ELSE 0 END) as losses,
                    SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws
                FROM matches 
                WHERE player1_id = ? OR player2_id = ?
                GROUP BY opponent_id
            ");
            $stmtStats->execute([$userId, $userId, $userId, $userId, $userId]);
            $h2h = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

            // 2. Fetch Recent Matches (renamed key for frontend compatibility)

            // Fetch ALL matches where I am p1 or p2
            $stmt = $conn->prepare("
                SELECT m.*, 
                u1.username as player1_name, 
                u2.username as player2_name,
                w.username as winner_name
                FROM matches m
                JOIN users u1 ON m.player1_id = u1.id
                JOIN users u2 ON m.player2_id = u2.id
                LEFT JOIN users w ON m.winner_id = w.id
                WHERE m.player1_id = ? OR m.player2_id = ?
                ORDER BY m.played_at DESC
            ");
            $stmt->execute([$userId, $userId]);
            $allMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "h2h" => $h2h, "recent_matches" => $allMatches]);

        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    } else if ($action === 'get_my_reports') {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
        try {
            $stmt = $conn->prepare("
                SELECT r.*, r.admin_response, q.question 
                FROM reports r 
                LEFT JOIN questions q ON r.question_id = q.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC LIMIT 20
            ");
            $stmt->execute([$userId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "reports" => $reports]);
            exit;
        } catch (Exception $e) {
            echo json_encode(["error" => "Database error"]);
            exit;
        }
    }

    // --- ADMIN: GET USERS BADGES (LIST) ---
    else if ($action === 'admin_get_users_badges') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        try {
            // Aggregate user badge data
            // We want: username, total_correct, badge_count, id
            // This is "heavy", but manageable for < 1000 users.

            // 1. Get Base Data (Users + Total Correct)
            $stmt = $conn->prepare("
                SELECT u.id, u.username, 
                (SELECT COUNT(*) FROM user_correct_answers WHERE user_id = u.id) as total_correct,
                (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
                FROM users u
                ORDER BY u.created_at DESC
                LIMIT 500
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "users" => $users]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- ADMIN: GET SINGLE USER BADGE DETAIL ---
    else if ($action === 'admin_get_user_badge_detail') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $targetUserId = intval($input['target_user_id']);
        if ($targetUserId <= 0) {
            echo json_encode(["error" => "Invalid target user"]);
            exit;
        }

        require_once 'check_badges.php'; // Ensure loaded

        try {
            // Re-use checkBadges logic with $returnData=true
            $badgeData = checkBadges($conn, $targetUserId, [], true);

            // Fetch User Details
            $stmtU = $conn->prepare("
                SELECT u.username, u.email, u.created_at,
                (SELECT COUNT(*) FROM user_correct_answers WHERE user_id = u.id) as total_correct,
                (SELECT COUNT(*) FROM matches WHERE player1_id = u.id OR player2_id = u.id) as games_played,
                (SELECT SUM(score) FROM game_sessions WHERE user_id = u.id) as total_score
                FROM users u 
                WHERE u.id = ?
            ");
            $stmtU->execute([$targetUserId]);
            $user = $stmtU->fetch(PDO::FETCH_ASSOC);

            // Calculate total unique correct overall (checkBadges might have fetched it internally, but let's send it explicitly)
            $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM user_correct_answers WHERE user_id = ?");
            $stmtTotal->execute([$targetUserId]);
            $totalUnique = $stmtTotal->fetchColumn();

            echo json_encode([
                "success" => true,
                "user" => $user,
                "badge_progress" => $badgeData,
                "total_correct" => $totalUnique
            ]);

        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // --- SPECIAL CONTENT ENDPOINTS ---
    else if ($action === 'admin_list_special_challenges') {
        if (!isAdmin($conn, isset($input['admin_user']) ? $input['admin_user'] : (isset($_GET['admin_user']) ? $_GET['admin_user'] : ''))) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        try {
            // Ensure Tables Exist (Lazy Init)
            $conn->exec("CREATE TABLE IF NOT EXISTS special_challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) UNIQUE NOT NULL,
                title VARCHAR(100),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $conn->exec("CREATE TABLE IF NOT EXISTS special_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                challenge_id INT NOT NULL,
                name VARCHAR(100),
                data JSON,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (challenge_id) REFERENCES special_challenges(id) ON DELETE CASCADE
            )");

            $stmt = $conn->query("SELECT * FROM special_challenges ORDER BY title ASC");
            $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch count of items for each
            foreach ($challenges as &$c) {
                $stmtCount = $conn->prepare("SELECT COUNT(*) FROM special_items WHERE challenge_id = ?");
                $stmtCount->execute([$c['id']]);
                $c['item_count'] = $stmtCount->fetchColumn();
            }

            echo json_encode(["success" => true, "challenges" => $challenges]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    } else if ($action === 'admin_save_special_challenge') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        $id = intval($input['id']);
        $slug = trim($input['slug']);
        $title = trim($input['title']);
        $desc = trim($input['description']);

        try {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE special_challenges SET slug=?, title=?, description=? WHERE id=?");
                $stmt->execute([$slug, $title, $desc, $id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO special_challenges (slug, title, description) VALUES (?, ?, ?)");
                $stmt->execute([$slug, $title, $desc]);
            }
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }

    } else if ($action === 'admin_save_special_items') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        $challengeId = intval($input['challenge_id']);
        $items = $input['items']; // Array of objects

        try {
            $conn->beginTransaction();
            foreach ($items as $item) {
                $iId = isset($item['id']) ? intval($item['id']) : 0;
                $name = $item['name'];

                $sort = isset($item['sort_order']) ? intval($item['sort_order']) : 0;

                // dynamic data
                $data = $item;
                unset($data['id']);
                unset($data['name']);
                unset($data['sort_order']);
                $jsonData = json_encode($data);

                if ($iId > 0) {
                    $stmt = $conn->prepare("UPDATE special_items SET name=?, data=?, sort_order=? WHERE id=?");
                    $stmt->execute([$name, $jsonData, $sort, $iId]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO special_items (challenge_id, name, data, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$challengeId, $name, $jsonData, $sort]);
                }
            }
            $conn->commit();
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
    } else if ($action === 'admin_import_monarchs') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        $jsonFile = __DIR__ . '/monarchs.json';
        if (!file_exists($jsonFile)) {
            echo json_encode(["error" => "monarchs.json not found"]);
            exit;
        }

        try {
            // 1. Ensure Challenge Exists
            $slug = 'monarchs';
            $title = 'Norske Monarker (Middels)';
            $desc = 'Fra Harald Hårfagre til i dag. Test din kunnskap om Norges kongerekke.';

            $stmt = $conn->prepare("SELECT id FROM special_challenges WHERE slug = ?");
            $stmt->execute([$slug]);
            $cId = $stmt->fetchColumn();

            if (!$cId) {
                $stmtInsert = $conn->prepare("INSERT INTO special_challenges (slug, title, description, is_active) VALUES (?, ?, ?, 1)");
                $stmtInsert->execute([$slug, $title, $desc]);
                $cId = $conn->lastInsertId();
            }

            // 2. Read JSON
            $data = json_decode(file_get_contents($jsonFile), true);
            if (!$data || !isset($data['items']))
                throw new Exception("Invalid JSON");

            // 3. Clear Existing items to avoid dupes?
            // Optional: Delete all for this challenge?
            $conn->prepare("DELETE FROM special_items WHERE challenge_id = ?")->execute([$cId]);

            // 4. Insert
            $stmtInsertItem = $conn->prepare("INSERT INTO special_items (challenge_id, name, data, sort_order) VALUES (?, ?, ?, ?)");
            $count = 0;
            foreach ($data['items'] as $item) {
                // Ensure sort_order column exists?
                // It's assumed to exist now. If not, this might fail unless we ALTER beforehand.
                // We'll trust the user creates it or previous steps did.
                // Actually, let's wrap in try/catch for column missing?

                $sort = isset($item['number']) ? intval($item['number']) : $count;
                $iName = $item['name'];
                $iData = json_encode($item, JSON_UNESCAPED_UNICODE);
                $stmtInsertItem->execute([$cId, $iName, $iData, $sort]);
                $count++;
            }

            echo json_encode(["success" => true, "message" => "Imported $count monarchs"]);

        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }

    } else if ($action === 'admin_delete_special_item') {
        if (!isAdmin($conn, $input['admin_user'])) {
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        $id = intval($input['id']);
        try {
            $conn->prepare("DELETE FROM special_items WHERE id = ?")->execute([$id]);
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }





}
?>