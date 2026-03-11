<?php
declare(strict_types=1);

$makeWebhook = getenv('MAKE_WEBHOOK_URL');
if (empty($makeWebhook)) {
  http_response_code(500);
  error_log('MAKE_WEBHOOK_URL não configurado');
  exit;
}

$makeSecret = getenv('MAKE_WEBHOOK_SECRET');
if (empty($makeSecret)) {
  http_response_code(500);
  error_log('MAKE_WEBHOOK_SECRET não configurado');
  exit;
}

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

// Validação do código postal (distrito de Faro)
$codigoPostal = clean_field('codigoPostal');
if (!preg_match('/^\d{4}-\d{3}$/', $codigoPostal)) {
  redirect_to(thank_you_url($lang, 'error', 'invalid_postal_code'));
}
$cp4 = substr($codigoPostal, 0, 4);
$cpFaroFile = __DIR__ . '/codigos-postais-faro.json';
$cpFaroValid = is_file($cpFaroFile)
  ? json_decode((string)@file_get_contents($cpFaroFile), true)
  : [];
if (is_array($cpFaroValid) && !empty($cpFaroValid) && !in_array($cp4, $cpFaroValid, true)) {
  redirect_to(thank_you_url($lang, 'error', 'outside_service_area'));
}

// Honeypot anti-spam
if (clean_field('website') !== '') {
  redirect_to(thank_you_url($lang, 'error', 'spam'));
}

// Usar REMOTE_ADDR como IP de confiança; X-Forwarded-For só se vier de proxy local (127.x ou 10.x)
$remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ipRaw = $remoteAddr;
$trustedProxyPattern = '/^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.)/';
if (preg_match($trustedProxyPattern, $remoteAddr) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
  $fwdParts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
  $candidate = trim($fwdParts[0]);
  if (filter_var($candidate, FILTER_VALIDATE_IP)) {
    $ipRaw = $candidate;
  }
}
$ip = $ipRaw;
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

// Validação server-side de email e telefone
$email = clean_field('email');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to(thank_you_url($lang, 'error', 'invalid_email'));
}

$phone = clean_field('phone');
$phoneNorm = preg_replace('/[()\s.\-]/', '', $phone);
if (!preg_match('/^\+?\d{9,15}$/', $phoneNorm) || preg_match('/^(\d)\1+$/', $phoneNorm)) {
  redirect_to(thank_you_url($lang, 'error', 'invalid_phone'));
}

$payload = [
  'lang' => $lang,
  'firstName' => clean_field('firstName'),
  'lastName' => clean_field('lastName'),
  'email' => $email,
  'phone' => $phoneNorm,
  'morada' => clean_field('morada'),
  'codigoPostal' => clean_field('codigoPostal'),
  'localidade' => clean_field('localidade'),
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
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-make-apikey: ' . $makeSecret],
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
      'header' => "Content-Type: application/json\r\nx-make-apikey: " . $makeSecret . "\r\n",
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
