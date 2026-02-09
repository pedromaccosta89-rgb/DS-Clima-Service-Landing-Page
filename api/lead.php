<?php
declare(strict_types=1);

$makeWebhook = 'https://hook.eu2.make.com/rqwaw3g4ldz8vpxp3rl669q9fklv5534';

function clean_field($key) {
  return trim((string)($_POST[$key] ?? ''));
}

function thank_you_url($lang, $status = '', $reason = '') {
  $base = ($lang === 'en') ? '/en/thankyou.php' : '/obrigado.php';
  $params = [];
  if ($status !== '') $params['status'] = $status;
  if ($reason !== '') $params['reason'] = $reason;

  return $base . (empty($params) ? '' : ('?' . http_build_query($params)));
}

function redirect_to($url) {
  header('Location: ' . $url, true, 303);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo 'Method Not Allowed';
  exit;
}

$lang = (strtolower(clean_field('lang')) === 'en') ? 'en' : 'pt';

// Honeypot anti-spam
if (clean_field('website') !== '') {
  redirect_to(thank_you_url($lang, 'error', 'spam'));
}

$ipRaw = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
$ipParts = explode(',', $ipRaw);
$ip = trim(isset($ipParts[0]) ? $ipParts[0] : '');
$ipKey = preg_replace('/[^a-zA-Z0-9:.\-_]/', '_', ($ip !== '' ? $ip : 'unknown'));

// Basic file-based IP rate limit: 3 requests / 10 min
$windowSeconds = 600;
$maxRequests = 3;
$now = time();

$tmp = sys_get_temp_dir();
$rateDir = rtrim($tmp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dsclima_rate_limit';

if (!is_dir($rateDir)) {
  @mkdir($rateDir, 0775, true);
}

$rateFile = $rateDir . DIRECTORY_SEPARATOR . 'lead_' . $ipKey . '.json';

$history = [];
if (is_file($rateFile)) {
  $decoded = json_decode((string)@file_get_contents($rateFile), true);
  if (is_array($decoded)) {
    $history = [];
    foreach ($decoded as $ts) {
      $history[] = (int)$ts;
    }
  }
}

// filtra timestamps fora da janela (PHP 7.1: sem arrow functions)
$filtered = [];
foreach ($history as $ts) {
  if (($now - (int)$ts) < $windowSeconds) {
    $filtered[] = (int)$ts;
  }
}
$history = array_values($filtered);

if (count($history) >= $maxRequests) {
  @file_put_contents($rateFile, json_encode($history));
  redirect_to(thank_you_url($lang, 'error', 'rate_limited'));
}

$history[] = $now;
@file_put_contents($rateFile, json_encode($history));

$payload = [
  'lang' => $lang,
  'firstName' => clean_field('firstName'),
  'lastName' => clean_field('lastName'),
  'email' => clean_field('email'),
  'phone' => clean_field('phone'),
  'setor' => clean_field('setor'),
  'service' => clean_field('service'),
  'message' => clean_field('message'),
  'sourceUrl' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
  'submittedAt' => gmdate('c'),
  'ip' => $ip,
  'userAgent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($jsonPayload === false) {
  redirect_to(thank_you_url($lang, 'error', 'json_encode'));
}

$httpCode = 0;

if (function_exists('curl_init')) {
  $ch = curl_init($makeWebhook);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_TIMEOUT => 12,
  ]);
  curl_exec($ch);

  $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlErr = curl_error($ch);
  curl_close($ch);

  if (!empty($curlErr)) {
    redirect_to(thank_you_url($lang, 'error', 'curl'));
  }
} else {
  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/json\r\n",
      'content' => $jsonPayload,
      'timeout' => 12,
    ],
  ]);

  @file_get_contents($makeWebhook, false, $context);

  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $httpCode = (int)$m[1];
  }
}

$status = ($httpCode >= 200 && $httpCode < 300) ? 'ok' : 'error';
redirect_to(thank_you_url($lang, $status));
