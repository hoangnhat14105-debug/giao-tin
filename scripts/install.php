<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
$options=getopt('', ['email:','password:','demo']);
$email=$options['email']??''; $password=$options['password']??'';
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10) exit("Cách dùng: php scripts/install.php --email=admin@example.com --password=<ít nhất 10 ký tự> [--demo]\n");
$name=getenv('DB_NAME')?:'giao_tin';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$name)) exit("Tên database không hợp lệ.\n");
try {
 $server=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306'),getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $server->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 db()->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
 if ((int)query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) exit("Ứng dụng đã được cài đặt. Không thay đổi tài khoản hiện có.\n");
 db()->beginTransaction();
 query('INSERT INTO users(name,email,password) VALUES(?,?,?)',['Quản trị viên',$email,password_hash($password,PASSWORD_DEFAULT)]);
 if (isset($options['demo'])) {
  foreach ([['Linh Boutique','0901234567','24 Nguyễn Trãi, Quận 1, TP.HCM'],['Mộc Home','0912345678','128 Võ Văn Tần, Quận 3, TP.HCM'],['The Coffee Corner','0923456789','35 Phan Xích Long, Phú Nhuận, TP.HCM'],['An Nhiên Store','0934567890','82 Lê Văn Sỹ, Quận 3, TP.HCM']] as $c) query('INSERT INTO customers(name,phone,address) VALUES(?,?,?)',$c);
  foreach ([['Nguyễn Minh Tuấn','0909876543','59-A1 234.56'],['Trần Quốc Bảo','0918765432','59-B2 567.89'],['Lê Hoàng Nam','0927654321','59-C3 891.23']] as $d) query('INSERT INTO drivers(name,phone,vehicle) VALUES(?,?,?)',$d);
  $recipients=['Nguyễn Thảo Vy','Trần Minh Anh','Lê Đức Huy','Phạm Ngọc Hà','Võ Thanh Tùng','Đặng Bảo Ngọc'];
  $addresses=['18 Nguyễn Huệ, Quận 1, TP.HCM','72 Điện Biên Phủ, Bình Thạnh, TP.HCM','206 Nguyễn Văn Cừ, Quận 5, TP.HCM','45 Hoàng Hoa Thám, Tân Bình, TP.HCM','91 Đặng Văn Bi, Thủ Đức, TP.HCM','38 Nguyễn Thị Thập, Quận 7, TP.HCM'];
  $states=['pending','shipping','delivered','delivered','picked','failed','pending','shipping','delivered','cancelled'];
  for($i=0;$i<30;$i++) {
   $status=$states[$i%10]; $days=(int)floor($i/6); $date=date('Y-m-d H:i:s',strtotime('-'.$days.' days -'.($i%6).' hours')); $customer=($i%4)+1;
   query('INSERT INTO orders(code,customer_id,driver_id,recipient_name,recipient_phone,pickup_address,delivery_address,package_name,weight,cod,fee,status,created_at,delivered_at,cod_settled) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',['GT'.date('ymd').sprintf('%04d',$i+1),$customer,$status==='pending'?null:($i%3)+1,$recipients[$i%6],'098'.sprintf('%07d',1234567+$i),query('SELECT address FROM customers WHERE id=?',[$customer])->fetchColumn(),$addresses[$i%6],['Quần áo thời trang','Đồ trang trí nhà','Cà phê hạt','Phụ kiện handmade'][$i%4],0.5+($i%4)*0.5,($i%5+1)*120000,25000+($i%4)*5000,$status,$date,$status==='delivered'?$date:null,$status==='delivered'&&$i>10?1:0]);
   query('INSERT INTO order_events(order_id,status,note,created_at) VALUES(?,?,?,?)',[db()->lastInsertId(),$status,'Dữ liệu minh họa',$date]);
  }
 }
 db()->commit(); echo "Đã cài đặt Giao Tín".(isset($options['demo'])?' với dữ liệu minh họa':'').".\n";
} catch(Throwable $ex) { try { if(db()->inTransaction()) db()->rollBack(); } catch(Throwable $ignored) {} fwrite(STDERR,"Cài đặt thất bại: ".$ex->getMessage()."\n"); exit(1); }
