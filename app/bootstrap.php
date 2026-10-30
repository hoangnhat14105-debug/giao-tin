<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Ho_Chi_Minh');
foreach (is_file(__DIR__.'/../.env') ? file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) putenv(trim($key).'='.trim($value));
}
function db(): PDO {
    static $pdo;
    if (!$pdo) $pdo = new PDO('mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.(getenv('DB_NAME') ?: 'giao_tin').';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function query(string $sql, array $args = []): PDOStatement { $q=db()->prepare($sql); $q->execute($args); return $q; }
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function money($v): string { return number_format((float)$v,0,',','.').' ₫'; }
function redirect(string $url): void { header('Location: '.$url); exit; }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function flash(string $text, string $kind='success'): void { $_SESSION['flash']=['text'=>$text,'kind'=>$kind]; }
const STATUSES=['pending'=>'Chờ lấy hàng','picked'=>'Đã lấy hàng','shipping'=>'Đang giao','delivered'=>'Đã giao','failed'=>'Giao thất bại','cancelled'=>'Đã hủy'];
const NEXT_STATUS=['pending'=>['picked','cancelled'],'picked'=>['shipping','cancelled'],'shipping'=>['delivered','failed'],'failed'=>['shipping','cancelled'],'delivered'=>[],'cancelled'=>[]];
function field(string $key, int $max=255, bool $required=true): string {
    $value=trim((string)($_POST[$key] ?? ''));
    if (($required && $value==='') || mb_strlen($value)>$max) throw new InvalidArgumentException('Vui lòng nhập đầy đủ thông tin hợp lệ ('.$key.').');
    return $value;
}
function amount(string $key): float { $v=$_POST[$key]??0; if (!is_numeric($v) || (float)$v<0 || (float)$v>9999999999) throw new InvalidArgumentException('Số tiền phải từ 0 đến 9.999.999.999 ₫.'); return round((float)$v); }
function phone(string $key): string { $v=field($key,25); if (!preg_match('/^[+0-9][0-9 .()-]{7,24}$/',$v)) throw new InvalidArgumentException('Số điện thoại không hợp lệ.'); return $v; }
