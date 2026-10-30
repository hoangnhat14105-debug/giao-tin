<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
$columns=query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
foreach(['role'=>"ENUM('admin','dispatcher','driver') NOT NULL DEFAULT 'admin'",'driver_id'=>'INT UNSIGNED NULL','active'=>'TINYINT NOT NULL DEFAULT 1'] as $name=>$type) {
 if(!in_array($name,$columns)) db()->exec("ALTER TABLE users ADD COLUMN $name $type");
}
echo "Đã cập nhật phân quyền.\n";
