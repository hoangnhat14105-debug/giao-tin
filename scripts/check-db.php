<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
 $pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 echo 'Kết nối MySQL thành công: '.$pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,'Không thể kết nối MySQL: '.$e->getMessage().PHP_EOL); exit(1); }
