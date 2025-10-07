<?php
// game/persona/utils.php
function cfg() {
  static $c = null;
  if ($c === null) $c = require __DIR__ . '/config.php';
  return $c;
}

function cors() {
  $conf = cfg();
  $origins = $conf['allowed_origins'];
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  foreach ($origins as $allowed) {
    $pattern = '#^'.str_replace(['*','.'], ['[^.]+','\.'], $allowed).'$#i';
    if ($origin && preg_match($pattern, parse_url($origin, PHP_URL_HOST) ? $origin : $origin)) {
      header('Access-Control-Allow-Origin: '.$origin);
      header('Vary: Origin');
      break;
    }
  }
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

function json_out($data, $code=200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function safe_id($s) {
  return preg_replace('/[^a-zA-Z0-9_\-\.@]/','_', $s ?? '');
}

function path_store($user_id) {
  $p = cfg()['paths']['store'];
  if (!is_dir($p)) mkdir($p, 0755, true);
  return $p.'/'.safe_id($user_id).'.json';
}

function path_cache($user_id, $lesson, $profile_hash) {
  $p = cfg()['paths']['cache'];
  if (!is_dir($p)) mkdir($p, 0755, true);
  $key = safe_id($user_id).'__'.safe_id($lesson).'__'.$profile_hash.'.html';
  return $p.'/'.$key;
}
