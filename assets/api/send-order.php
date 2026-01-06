<?php
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $error = '') {
  echo json_encode(['ok' => $ok, 'error' => $error], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(false, 'Method not allowed');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !is_array($data)) {
  respond(false, 'Invalid payload');
}

/** ========= CONFIG ========= **/
$TO_EMAIL   = 'orders@meniptv.com';       // ide kapod emailben
$FROM_EMAIL = 'no-reply@meniptv.com';     // legyen létező mailbox vagy alias Titanban
$SUBJECT    = 'MENIPTV Order';

// Telegram Bot API:
$TELEGRAM_BOT_TOKEN = 'PASTE_YOUR_BOT_TOKEN_HERE';
$TELEGRAM_CHAT_ID   = 'PASTE_YOUR_CHAT_ID_HERE'; // pl. 123456789 vagy -100xxxxxxxxxx (csoport)
/** ========================== **/

// Honeypot
if (!empty($data['company'])) {
  respond(false, 'Spam blocked');
}

// sanitize / validate
$plan    = trim((string)($data['plan'] ?? ''));
$devices = trim((string)($data['devices'] ?? ''));
$type    = trim((string)($data['type'] ?? ''));
$contact = trim((string)($data['contact'] ?? ''));
$app     = trim((string)($data['app'] ?? ''));
$renew_u = trim((string)($data['renew_username'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($plan === '' || $devices === '' || $type === '' || $contact === '') {
  respond(false, 'Missing required fields');
}
if ($type === 'Renewal' && $renew_u === '') {
  respond(false, 'Missing renewal username');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$emailBody =
"NEW MENIPTV ORDER\n\n" .
"Type: $type\n" .
"Plan: $plan\n" .
"Devices: $devices\n" .
($app !== '' ? "App/Device: $app\n" : "") .
($renew_u !== '' ? "Renew Username: $renew_u\n" : "") .
"Contact: $contact\n\n" .
"IP: $ip\n" .
"UA: $ua\n" .
"Time: " . date('c') . "\n";

// 1) Send EMAIL
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/plain; charset=UTF-8';
$headers[] = 'From: MENIPTV <' . $FROM_EMAIL . '>';
$headersStr = implode("\r\n", $headers);

$mailOk = @mail($TO_EMAIL, $SUBJECT, $emailBody, $headersStr);

// 2) Send TELEGRAM
$tgText = $message !== '' ? $message : $emailBody;
$tgOk = false;

if ($TELEGRAM_BOT_TOKEN !== '' && $TELEGRAM_CHAT_ID !== '' &&
    $TELEGRAM_BOT_TOKEN !== 'PASTE_YOUR_BOT_TOKEN_HERE' &&
    $TELEGRAM_CHAT_ID !== 'PASTE_YOUR_CHAT_ID_HERE') {

  $url = "https://api.telegram.org/bot" . $TELEGRAM_BOT_TOKEN . "/sendMessage";
  $payload = [
    'chat_id' => $TELEGRAM_CHAT_ID,
    'text' => $tgText,
    'disable_web_page_preview' => true
  ];

  $options = [
    'http' => [
      'method'  => 'POST',
      'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => http_build_query($payload),
      'timeout' => 8
    ]
  ];

  $context  = stream_context_create($options);
  $result = @file_get_contents($url, false, $context);

  if ($result !== false) {
    $json = json_decode($result, true);
    $tgOk = is_array($json) && !empty($json['ok']);
  }
}

// Success if at least one channel worked:
if ($mailOk || $tgOk) {
  respond(true, '');
}

respond(false, 'Could not send. Please open Telegram (prefill) and send the message.');
