<?php
require __DIR__.'/../app/bootstrap.php';
$tag='TEST_ROLE_'.bin2hex(random_bytes(5));$ids=[];$cookie=tempnam(__DIR__.'/../storage','role-cookie-');$token='';$code=0;$count=0;
function req(string $path,array $data=[]): string {global $cookie,$token,$code;$c=curl_init('http://127.0.0.1:8080'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>15]);if($data){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query(['csrf'=>$token]+$data));}$html=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);if($html===false||$code>=500)throw new Exception('HTTP '.$code);if(preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m))$token=$m[1];return $html;}
function ok(bool $v,string $label): void {global $count;if(!$v)throw new Exception($label);$count++;echo "PASS: $label\n";}
try {
 query('INSERT INTO drivers(name,phone,vehicle) VALUES(?,?,?)',[$tag,'0901234567','TEST']);$driver=(int)db()->lastInsertId();
 query('INSERT INTO customers(name,phone,address) VALUES(?,?,?)',[$tag,'0901234567','TEST']);$customer=(int)db()->lastInsertId();
 foreach(['driver','dispatcher','admin'] as $role){query('INSERT INTO users(name,email,password,role,driver_id) VALUES(?,?,?,?,?)',[$tag,$tag.$role.'@test.local',password_hash('Testing@2026',PASSWORD_DEFAULT),$role,$role==='driver'?$driver:null]);$ids[$role]=(int)db()->lastInsertId();}
 foreach([true,false] as $own){query('INSERT INTO orders(code,customer_id,driver_id,recipient_name,recipient_phone,pickup_address,delivery_address,package_name,note) VALUES(?,?,?,?,?,?,?,?,?)',[$tag.($own?'A':'B'),$customer,$own?$driver:null,$tag.($own?'OWN':'OTHER'),'0981234567','TEST','PRIVATE_ADDRESS','TEST','SECRET_NOTE']);if($own)$order=(int)db()->lastInsertId();else $other=(int)db()->lastInsertId();}
 query('INSERT INTO order_events(order_id,status,note) VALUES(?,?,?)',[$order,'pending','SECRET_INTERNAL']);
 req('/');req('/',['action'=>'login','email'=>$tag.'driver@test.local','password'=>'Testing@2026']);
 $html=req('/');ok(strpos($html,$tag.'OWN')!==false&&strpos($html,$tag.'OTHER')===false,'Tài xế chỉ thấy đơn của mình');
 foreach(['reports','customers','drivers','export','users','new'] as $page){req('/?page='.$page);ok($code===403,'Chặn tài xế: '.$page);}
 req('/?page=detail&id='.$other);ok($code===403,'Chặn xem đơn tài xế khác');
 req('/',['action'=>'status','id'=>$other,'status'=>'picked']);ok($code===403,'Chặn cập nhật đơn khác');
 req('/',['action'=>'assign','id'=>$order,'driver_id'=>$driver]);ok($code===403,'Chặn tự phân công');
 req('/',['action'=>'settle','id'=>$order]);ok($code===403,'Chặn tài xế đối soát');
 req('/',['action'=>'status','id'=>$order,'status'=>'cancelled']);ok($code===403,'Chặn tài xế hủy đơn');
 foreach(['picked','shipping'] as $s){req('/',['action'=>'status','id'=>$order,'status'=>$s]);ok(query('SELECT status FROM orders WHERE id=?',[$order])->fetchColumn()===$s,'Tài xế cập nhật '.$s);}
 $html=req('/',['action'=>'status','id'=>$order,'status'=>'failed']);ok(strpos($html,'nhập lý do')!==false,'Bắt buộc lý do thất bại');
 req('/',['action'=>'logout']);req('/',['action'=>'login','email'=>$tag.'dispatcher@test.local','password'=>'Testing@2026']);req('/?page=users');ok($code===403,'Điều phối không quản lý tài khoản');ok(strpos(req('/?page=orders'),$tag.'OWN')!==false,'Điều phối xem đơn');
 req('/',['action'=>'user_toggle','id'=>$ids['admin']]);ok($code===403,'Điều phối không khóa quản trị');
 req('/',['action'=>'logout']);req('/',['action'=>'login','email'=>$tag.'admin@test.local','password'=>'Testing@2026']);ok(strpos(req('/?page=users'),'Tạo tài khoản')!==false,'Quản trị mở trang tài khoản');
 req('/?page=users',['action'=>'user','name'=>$tag,'email'=>$tag.'new@test.local','password'=>'Testing@2026','role'=>'dispatcher']);$new=query('SELECT id FROM users WHERE email=?',[$tag.'new@test.local'])->fetchColumn();if($new)$ids['new']=(int)$new;ok((bool)$new,'Quản trị tạo điều phối');
 req('/?page=users',['action'=>'user_toggle','id'=>$ids['driver']]);req('/',['action'=>'logout']);$html=req('/',['action'=>'login','email'=>$tag.'driver@test.local','password'=>'Testing@2026']);ok(strpos($html,'không chính xác')!==false,'Tài khoản khóa không đăng nhập');
 $html=req('/?page=track',['code'=>$tag.'A','phone'=>'0981234567']);ok(strpos($html,$tag.'A')!==false&&strpos($html,'PRIVATE_ADDRESS')===false&&strpos($html,'SECRET_INTERNAL')===false,'Tra cứu đúng và ẩn thông tin nội bộ');
 $html=req('/?page=track',['code'=>$tag.'A','phone'=>'0981111111']);ok(strpos($html,'Không tìm thấy vận đơn')!==false,'Tra cứu sai điện thoại không lộ đơn');
 echo "$count kiểm tra phân quyền và tra cứu thành công.\n";
}catch(Throwable $e){echo 'FAIL: '.$e->getMessage()."\n";$failed=true;}
finally{foreach([$order??0,$other??0] as $id){query('DELETE FROM order_events WHERE order_id=?',[$id]);query('DELETE FROM orders WHERE id=? AND customer_id=?',[$id,$customer??0]);}foreach($ids as $id)query('DELETE FROM users WHERE id=? AND name=?',[$id,$tag]);if(isset($driver))query('DELETE FROM drivers WHERE id=?',[$driver]);if(isset($customer))query('DELETE FROM customers WHERE id=?',[$customer]);unlink($cookie);}
exit(isset($failed)?1:0);
