<?php
session_start();

const ADMIN_PASSWORD = 'roni888';
// Telegram bot token (must match index.php)
const BOT_TOKEN = '9087217791:AAEjOhKH0uOml0VcW8Vsw2whkm0ZX6cOSvY*';
define('BASE_DIR', __DIR__);
define('CONFIG_FILE', BASE_DIR . '/config.json');

function sanitizeButtonLabel($label) {
    $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$label));
    if ($s === '') return '';
    if (strlen($s) > 64) return mb_strcut($s, 0, 64, 'UTF-8');
    return $s;
}

function normalizeButtonUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    $url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url);
    // @Username or https://@Username -> https://t.me/Username
    if (preg_match('#^(?:https?://)?@([a-zA-Z0-9_]+)$#', $url, $m)) {
        return 'https://t.me/' . $m[1];
    }
    if (preg_match('#^https?://@#i', $url)) {
        $username = preg_replace('#^https?://@#i', '', $url);
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        if ($username !== '') return 'https://t.me/' . $username;
    }
    // https://W8SOJIB or http://Username (no dot, no path) -> https://t.me/Username
    if (preg_match('#^https?://([a-zA-Z0-9_]+)$#i', $url, $m) && strpos($m[1], '.') === false) {
        return 'https://t.me/' . $m[1];
    }
    if (preg_match('#^t\.me/#i', $url)) {
        $url = 'https://' . $url;
    } elseif (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function getFullConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return [
            'trackers' => [],
            'channel' => ['label' => 'Join Channel', 'url' => ''],
            'banned' => [],
        ];
    }
    $data = @json_decode(file_get_contents(CONFIG_FILE), true);
    if (!is_array($data)) return ['trackers' => [], 'channel' => ['label' => '', 'url' => ''], 'banned' => []];
    if (!isset($data['trackers']) || !is_array($data['trackers'])) $data['trackers'] = [];
    if (!isset($data['channel']) || !is_array($data['channel'])) $data['channel'] = ['label' => '', 'url' => ''];
    if (!isset($data['banned']) || !is_array($data['banned'])) $data['banned'] = [];
    return $data;
}

function saveFullConfig($config) {
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function getUsersList() {
    $file = BASE_DIR . '/user.txt';
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $lines = array_filter(array_map('trim', explode("\n", $content)));
    $users = [];
    foreach ($lines as $line) {
        if (preg_match('/Chat ID:\s*(\d+)\s*,\s*Name:\s*(.+)$/', $line, $m)) {
            $users[] = ['chat_id' => $m[1], 'name' => trim($m[2])];
        }
    }
    return $users;
}

function removeUserByChatId($chatId) {
    $file = BASE_DIR . '/user.txt';
    if (!file_exists($file)) return;
    $content = file_get_contents($file);
    $lines = array_filter(array_map('trim', explode("\n", $content)));
    $newLines = [];
    foreach ($lines as $line) {
        if (strpos($line, 'Chat ID: ' . $chatId . ',') !== 0) {
            $newLines[] = $line;
        }
    }
    file_put_contents($file, implode("\n", $newLines) . "\n");
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['save_urls']) && !isset($_POST['add_tracker']) && !isset($_POST['update_tracker']) && !isset($_POST['save_channel']) && !isset($_POST['remove_user']) && !isset($_POST['ban_user']) && !isset($_POST['unban_user']) && !isset($_POST['delete_tracker'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Wrong password.';
}

$actionMessage = '';
if (!empty($_SESSION['admin_logged_in']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = getFullConfig();

    if (isset($_POST['add_tracker']) && isset($_POST['tracker_label'], $_POST['tracker_url'])) {
        $label = sanitizeButtonLabel($_POST['tracker_label']);
        $url = normalizeButtonUrl($_POST['tracker_url']);
        if ($label !== '' && $url !== '') {
            $config['trackers'][] = [
                'label' => $label,
                'url' => $url,
                'append_id' => !empty($_POST['tracker_append_id']),
            ];
            saveFullConfig($config);
            $actionMessage = 'Tracker button added. It will appear in the bot.';
        } else {
            $actionMessage = 'Button label and URL are required. Label max 64 characters. URL must be valid (e.g. https://example.com or t.me/channel).';
        }
    }

    if (isset($_POST['update_tracker']) && isset($_POST['tracker_index'], $_POST['tracker_label'], $_POST['tracker_url'])) {
        $idx = (int) $_POST['tracker_index'];
        $label = sanitizeButtonLabel($_POST['tracker_label']);
        $url = normalizeButtonUrl($_POST['tracker_url']);
        if (isset($config['trackers'][$idx]) && $label !== '' && $url !== '') {
            $config['trackers'][$idx] = [
                'label' => $label,
                'url' => $url,
                'append_id' => !empty($_POST['tracker_append_id']),
            ];
            saveFullConfig($config);
            $actionMessage = 'Tracker button updated.';
        } elseif (isset($config['trackers'][$idx])) {
            $actionMessage = 'Label and URL are required. Label max 64 characters.';
        }
    }

    if (isset($_POST['delete_tracker']) && isset($_POST['tracker_index'])) {
        $idx = (int) $_POST['tracker_index'];
        if (isset($config['trackers'][$idx])) {
            array_splice($config['trackers'], $idx, 1);
            saveFullConfig($config);
            $actionMessage = 'Tracker button removed.';
        }
    }

    if (isset($_POST['save_channel']) && isset($_POST['channel_label'], $_POST['channel_url'])) {
        $config['channel'] = [
            'label' => sanitizeButtonLabel($_POST['channel_label']),
            'url' => normalizeButtonUrl($_POST['channel_url']),
        ];
        saveFullConfig($config);
        $actionMessage = 'Channel button saved.';
    }

    if (isset($_POST['remove_user']) && isset($_POST['chat_id'])) {
        $cid = trim($_POST['chat_id']);
        if ($cid !== '') {
            removeUserByChatId($cid);
            $actionMessage = 'User with Chat ID ' . htmlspecialchars($cid) . ' removed.';
        }
    }

    if (isset($_POST['ban_user']) && isset($_POST['chat_id'])) {
        $cid = trim($_POST['chat_id']);
        if ($cid !== '' && !in_array($cid, $config['banned'], true)) {
            $config['banned'][] = $cid;
            saveFullConfig($config);
            $actionMessage = 'User with Chat ID ' . htmlspecialchars($cid) . ' banned.';
        }
    }

    if (isset($_POST['unban_user']) && isset($_POST['chat_id'])) {
        $cid = trim($_POST['chat_id']);
        if ($cid !== '') {
            $config['banned'] = array_values(array_filter($config['banned'], function ($id) use ($cid) { return $id !== $cid; }));
            saveFullConfig($config);
            $actionMessage = 'User with Chat ID ' . htmlspecialchars($cid) . ' unbanned.';
        }
    }

    if (isset($_POST['set_webhook']) && isset($_POST['webhook_url'])) {
        $webhookUrl = trim($_POST['webhook_url']);
        if ($webhookUrl === '') {
            $actionMessage = 'Webhook URL is required.';
        } else {
            $apiUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/setWebhook';
            $payload = http_build_query(['url' => $webhookUrl]);
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                ]
            ]);
            $response = @file_get_contents($apiUrl, false, $context);
            if ($response === false) {
                $actionMessage = 'Failed to call setWebhook. Check server connectivity.';
            } else {
                $json = @json_decode($response, true);
                if (isset($json['ok']) && $json['ok']) {
                    $actionMessage = 'Webhook set successfully: ' . htmlspecialchars($webhookUrl);
                } else {
                    $desc = isset($json['description']) ? $json['description'] : 'Unknown error';
                    $actionMessage = 'setWebhook error: ' . htmlspecialchars($desc);
                }
            }
        }
    }
}

$loggedIn = !empty($_SESSION['admin_logged_in']);
$config = $loggedIn ? getFullConfig() : ['trackers' => [], 'channel' => ['label' => '', 'url' => ''], 'banned' => []];
$users = $loggedIn ? getUsersList() : [];
$bannedIds = array_flip($config['banned']);
$editingTrackerIndex = isset($_GET['edit_tracker']) ? (int) $_GET['edit_tracker'] : null;
if ($editingTrackerIndex !== null && !isset($config['trackers'][$editingTrackerIndex])) {
    $editingTrackerIndex = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>W8TrackerProBot – Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%); min-height: 100vh; color: #e2e8f0; }
        .wrap { max-width: 960px; margin: 0 auto; padding: clamp(12px, 3vw, 24px); }
        h1 { color: #00d4aa; margin: 0 0 1rem; font-size: clamp(1.5rem, 4vw, 1.75rem); }
        h2 { color: #7fdbff; font-size: clamp(1rem, 2.5vw, 1.15rem); margin: 1.5rem 0 0.75rem; font-weight: 600; }
        .card { background: rgba(22, 33, 62, 0.85); border-radius: 12px; padding: clamp(14px, 3vw, 20px); margin-bottom: 1.25rem; border: 1px solid rgba(127, 219, 255, 0.15); }
        .card p { margin: 0 0 0.75rem; color: #94a3b8; font-size: 0.9rem; }
        form { margin: 0.5rem 0; }
        label { display: block; margin-bottom: 4px; font-size: 0.875rem; color: #cbd5e1; }
        input[type="text"], input[type="password"], input[type="number"] { width: 100%; max-width: 100%; padding: 10px 12px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #334155; background: #1e293b; color: #f1f5f9; font-size: 1rem; }
        input:focus { outline: none; border-color: #00d4aa; box-shadow: 0 0 0 2px rgba(0,212,170,0.2); }
        button, .btn { display: inline-block; padding: 8px 14px; margin: 4px 4px 4px 0; border-radius: 8px; border: none; font-size: 0.875rem; cursor: pointer; text-decoration: none; background: #0f3460; color: #fff; }
        button:hover, .btn:hover { background: #00d4aa; color: #0f172a; }
        .btn-danger { background: #b91c1c; }
        .btn-danger:hover { background: #dc2626; color: #fff; }
        .btn-success { background: #059669; }
        .btn-success:hover { background: #10b981; color: #fff; }
        .msg { background: rgba(15, 52, 96, 0.9); padding: 12px 14px; border-radius: 8px; margin-bottom: 1rem; color: #7fdbff; border-left: 4px solid #00d4aa; }
        .error { color: #f87171; margin-top: 8px; }
        .login-box { max-width: 340px; margin: 2rem auto; }
        .login-box input[type="password"] { margin-bottom: 12px; }
        .flex { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .flex label { margin: 0; }
        .flex input[type="checkbox"] { width: auto; margin: 0 6px 0 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: clamp(0.8rem, 2vw, 0.9rem); }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: rgba(15, 52, 96, 0.8); color: #00d4aa; }
        .action-cell { white-space: nowrap; }
        .action-cell form { display: inline; }
        .action-cell button { margin: 2px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
        .badge-banned { background: #b91c1c; color: #fff; }
        .logout { margin-top: 1.5rem; }
        .logout a { color: #7fdbff; }
        .tracker-list { list-style: none; padding: 0; margin: 0; }
        .tracker-list li { padding: 10px 12px; margin-bottom: 6px; background: #1e293b; border-radius: 8px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
        .tracker-list li span { word-break: break-all; flex: 1 1 200px; }
        .tracker-list li form { margin: 0; }
        .edit-tracker-form { width: 100%; }
        .edit-tracker-form input[type="text"] { max-width: 100%; margin-bottom: 8px; }
        .action-btns { display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        @media (max-width: 600px) {
            .tracker-list li { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="wrap">

<?php if (!$loggedIn): ?>
    <h1>Admin Panel</h1>
    <div class="card login-box">
        <form method="post">
            <label for="pw">Password</label>
            <input type="password" id="pw" name="password" required autofocus>
            <button type="submit">Log in</button>
        </form>
        <?php if (isset($loginError)): ?>
            <p class="error"><?php echo htmlspecialchars($loginError); ?></p>
        <?php endif; ?>
    </div>
    <?php exit; ?>
<?php endif; ?>

<h1>W8TrackerProBot – Admin</h1>

<?php if ($actionMessage): ?>
    <div class="msg"><?php echo htmlspecialchars($actionMessage); ?></div>
<?php endif; ?>

<section class="card">
    <h2>Tracker buttons (bot inline buttons)</h2>
    <p>Add, edit, or remove tracker links. Each item becomes a Telegram inline button. Enable "Append Chat ID" to add <code>?id=CHAT_ID</code> to the URL.</p>
    <ul class="tracker-list">
        <?php foreach ($config['trackers'] as $i => $t): ?>
            <li>
                <?php if ($editingTrackerIndex === $i): ?>
                    <form method="post" class="edit-tracker-form">
                        <input type="hidden" name="tracker_index" value="<?php echo $i; ?>">
                        <label>Button label</label>
                        <input type="text" name="tracker_label" value="<?php echo htmlspecialchars($t['label']); ?>" required>
                        <label>URL</label>
                        <input type="text" name="tracker_url" value="<?php echo htmlspecialchars($t['url']); ?>" required>
                        <div class="flex" style="margin:8px 0;">
                            <label><input type="checkbox" name="tracker_append_id" value="1" <?php echo !empty($t['append_id']) ? 'checked' : ''; ?>> Append Chat ID to URL</label>
                        </div>
                        <button type="submit" name="update_tracker" value="1">Save</button>
                        <a href="admin.php" class="btn">Cancel</a>
                    </form>
                <?php else: ?>
                    <span><strong><?php echo htmlspecialchars($t['label']); ?></strong> — <?php echo htmlspecialchars($t['url']); ?> <?php echo !empty($t['append_id']) ? '(+id)' : ''; ?></span>
                    <span class="action-btns">
                        <a href="admin.php?edit_tracker=<?php echo $i; ?>" class="btn">Edit</a>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="tracker_index" value="<?php echo $i; ?>">
                            <button type="submit" name="delete_tracker" value="1" class="btn-danger" onclick="return confirm('Remove this tracker button?');">Delete</button>
                        </form>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($config['trackers'])): ?>
            <li style="color:#94a3b8;">No tracker buttons yet. Add one below.</li>
        <?php endif; ?>
    </ul>
    <form method="post">
        <label>Button label</label>
        <input type="text" name="tracker_label" placeholder="e.g. Font Camera Hack Video" required>
        <label>URL</label>
        <input type="text" name="tracker_url" placeholder="https://example.com/FontCM.html" required>
        <div class="flex" style="margin:10px 0;">
            <label><input type="checkbox" name="tracker_append_id" value="1" checked> Append Chat ID to URL</label>
        </div>
        <button type="submit" name="add_tracker" value="1">Add tracker button</button>
    </form>
</section>

<section class="card">
    <h2>Channel button</h2>
    <p>One "Join Channel" style button (e.g. link to your Telegram channel). No Chat ID is appended.</p>
    <form method="post">
        <label>Button label</label>
        <input type="text" name="channel_label" value="<?php echo htmlspecialchars($config['channel']['label'] ?? ''); ?>" placeholder="Join Channel Free Hacks">
        <label>URL</label>
        <input type="text" name="channel_url" value="<?php echo htmlspecialchars($config['channel']['url'] ?? ''); ?>" placeholder="https://t.me/W8TeamSB">
        <button type="submit" name="save_channel" value="1">Save channel button</button>
    </form>
</section>

<section class="card">
    <h2>Bot webhook</h2>
    <p>Set the Telegram webhook URL for this bot (must be HTTPS and point to your <code>index.php</code> on the server).</p>
    <form method="post">
        <label>Webhook URL</label>
        <input
            type="text"
            name="webhook_url"
            placeholder="https://your-domain.com/path/to/index.php"
            required
        >
        <button type="submit" name="set_webhook" value="1">Set webhook</button>
    </form>
</section>

<section class="card">
    <h2>Manage users</h2>
    <p>Ban or unban users (banned users see "You are banned" in the bot). Remove deletes them from the user list only.</p>
    <div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>Chat ID</th>
                <th>Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="4">No users.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <?php $isBanned = isset($bannedIds[$u['chat_id']]); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['chat_id']); ?></td>
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td>
                            <?php if ($isBanned): ?>
                                <span class="badge badge-banned">Banned</span>
                            <?php else: ?>
                                <span style="color:#10b981;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-cell">
                            <?php if ($isBanned): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="chat_id" value="<?php echo htmlspecialchars($u['chat_id']); ?>">
                                    <button type="submit" name="unban_user" value="1" class="btn-success">Unban</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="chat_id" value="<?php echo htmlspecialchars($u['chat_id']); ?>">
                                    <button type="submit" name="ban_user" value="1" class="btn-danger">Ban</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this user from the list?');">
                                <input type="hidden" name="chat_id" value="<?php echo htmlspecialchars($u['chat_id']); ?>">
                                <button type="submit" name="remove_user" value="1">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<div class="logout">
    <a href="admin.php?logout=1">Log out</a>
</div>

</div>
</body>
</html>
