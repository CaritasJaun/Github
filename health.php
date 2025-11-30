<?php
// minimal .env loader
$env = __DIR__.'/.env';
if (is_file($env)) {
  foreach (file($env, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0]==='#' || strpos($line,'=')===false) continue;
    list($k,$v)=array_map('trim', explode('=',$line,2));
    $v=trim($v,"\"'");
    $_ENV[$k]=$_SERVER[$k]=$v;
    if (function_exists('putenv')) putenv("$k=$v");
  }
}
header('Content-Type: text/plain');

// 1) PHP + extensions
echo "PHP: ".PHP_VERSION."\n";
echo "mysqli: ".(extension_loaded('mysqli')?'ON':'MISSING')."\n";
echo "mbstring: ".(extension_loaded('mbstring')?'ON':'MISSING')."\n";
echo "intl: ".(extension_loaded('intl')?'ON':'MISSING')."\n";

// 2) DB connect
$ok = false;
try {
  $mysqli = @new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
  if ($mysqli && !$mysqli->connect_errno) {
    $ok = true;
    echo "DB: CONNECTED to ".getenv('DB_NAME')."\n";
    $res = $mysqli->query("SHOW TABLES LIKE 'ci_sessions'");
    echo "ci_sessions table: ".($res && $res->num_rows ? "FOUND" : "MISSING")."\n";
  } else {
    echo "DB ERROR: ".$mysqli->connect_error."\n";
  }
} catch (Throwable $e) {
  echo "DB EXCEPTION: ".$e->getMessage()."\n";
}

// 3) Storage folder
$storage = getenv('EDUASSIST_STORAGE') ?: '/home/eduassis/eduassist_storage';
$path = $storage.'/healthcheck.tmp';
$w = @file_put_contents($path, date('c'));
echo "Storage ($storage) write: ".($w!==false?'OK':'FAILED')."\n";
if ($w!==false) @unlink($path);

echo "\nIf all say OK/FOUND and the app is still blank, the error is in CI boot. Check application/logs and set APP_ENV=development to see it.\n";
