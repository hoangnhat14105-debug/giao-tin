<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
session_start();
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
$_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(32));
$page=$_GET['page']??'dashboard';
$error='';
try { db(); query('SELECT id FROM users LIMIT 1'); } catch(Throwable $ex) {
 http_response_code(503); require __DIR__.'/../app/setup-view.php'; exit;
}
require __DIR__.'/../app/portal.php';
if($page==='track') tracking();
if(!empty($_SESSION['user'])) {
 $current=query('SELECT id,name,email,role,driver_id,active FROM users WHERE id=?',[$_SESSION['user']['id']])->fetch();
 if(!$current||!$current['active']||($current['role']==='driver'&&!query('SELECT id FROM drivers WHERE id=? AND active=1',[$current['driver_id']])->fetch())) {unset($_SESSION['user']);redirect('/?page=login');}
 $_SESSION['user']=$current;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) throw new InvalidArgumentException('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.');
  $action=$_POST['action']??'';
  if ($action==='login') {
   $now=time(); $attempts=$_SESSION['login_attempts']??[]; $attempts=array_filter($attempts,fn($t)=>$t>$now-900);
   if(count($attempts)>=10) throw new InvalidArgumentException('Bạn đã thử quá nhiều lần. Vui lòng thử lại sau 15 phút.');
   $user=query('SELECT * FROM users WHERE email=?',[trim($_POST['email']??'')])->fetch();
   if (!$user || !$user['active'] || ($user['role']==='driver'&&!query('SELECT id FROM drivers WHERE id=? AND active=1',[$user['driver_id']])->fetch()) || !password_verify($_POST['password']??'',$user['password'])) { $attempts[]=$now; $_SESSION['login_attempts']=$attempts; throw new InvalidArgumentException('Email hoặc mật khẩu không chính xác.'); }
   session_regenerate_id(true); $_SESSION['user']=['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role'],'driver_id'=>$user['driver_id']]; unset($_SESSION['login_attempts']); $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect('/');
  }
  if (empty($_SESSION['user'])) redirect('/?page=login');
  if ($action==='logout') { $_SESSION=[]; session_destroy(); redirect('/?page=login'); }
  $role=$_SESSION['user']['role'];
  if($role==='driver'&&$action!=='status') deny();
  if(in_array($action,['user','user_toggle'])&&$role!=='admin') deny();
  if($action==='user') {
   $name=field('name',100);$email=field('email',190);$password=field('password',255);$newRole=field('role',20);$driver=(int)($_POST['driver_id']??0);
   if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10||!in_array($newRole,['admin','dispatcher','driver'])) throw new InvalidArgumentException('Email, vai trò hoặc mật khẩu không hợp lệ (mật khẩu tối thiểu 10 ký tự).');
   if(query('SELECT id FROM users WHERE email=?',[$email])->fetch()) throw new InvalidArgumentException('Email đã có tài khoản.');
   if($newRole==='driver'&&(!query('SELECT id FROM drivers WHERE id=? AND active=1',[$driver])->fetch()||query("SELECT id FROM users WHERE driver_id=? AND role='driver'",[$driver])->fetch())) throw new InvalidArgumentException('Chọn tài xế đang hoạt động và chưa có tài khoản.');
   query('INSERT INTO users(name,email,password,role,driver_id) VALUES(?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$newRole,$newRole==='driver'?$driver:null]);
   flash('Đã tạo tài khoản.');redirect('/?page=users');
  }
  if($action==='user_toggle') {
   $id=(int)($_POST['id']??0);if($id==$_SESSION['user']['id'])throw new InvalidArgumentException('Không thể khóa tài khoản đang đăng nhập.');
   query('UPDATE users SET active=1-active WHERE id=?',[$id]);flash('Đã cập nhật tài khoản.');redirect('/?page=users');
  }
  if ($action==='customer') {
   $values=[field('name',100),phone('phone'),field('address')]; $id=(int)($_POST['id']??0);
   if($id) { $values[]=$id; query('UPDATE customers SET name=?,phone=?,address=? WHERE id=?',$values); } else query('INSERT INTO customers(name,phone,address) VALUES(?,?,?)',$values);
   flash('Đã lưu khách hàng.'); redirect('/?page=customers');
  }
  if ($action==='driver') {
   $values=[field('name',100),phone('phone'),field('vehicle',30),isset($_POST['active'])?1:0]; $id=(int)($_POST['id']??0);
   if($id) { $values[]=$id; query('UPDATE drivers SET name=?,phone=?,vehicle=?,active=? WHERE id=?',$values); } else query('INSERT INTO drivers(name,phone,vehicle,active) VALUES(?,?,?,?)',$values);
   flash('Đã lưu nhân viên giao hàng.'); redirect('/?page=drivers');
  }
  if ($action==='order') {
   $customer=(int)($_POST['customer_id']??0); $driver=(int)($_POST['driver_id']??0);
   if (!query('SELECT id FROM customers WHERE id=?',[$customer])->fetch()) throw new InvalidArgumentException('Vui lòng chọn khách hàng.');
   if ($driver && !query('SELECT id FROM drivers WHERE id=? AND active=1',[$driver])->fetch()) throw new InvalidArgumentException('Nhân viên không khả dụng.');
   $weight=$_POST['weight']??0; if(!is_numeric($weight)||$weight<0||$weight>999999) throw new InvalidArgumentException('Khối lượng không hợp lệ.');
   $values=[$customer,$driver?:null,field('recipient_name',100),phone('recipient_phone'),field('pickup_address'),field('delivery_address'),field('package_name',150),(float)$weight,amount('cod'),amount('fee'),field('note',2000,false)];
   db()->beginTransaction();
   $code='GT'.date('ymd').strtoupper(bin2hex(random_bytes(3)));
   query('INSERT INTO orders(customer_id,driver_id,recipient_name,recipient_phone,pickup_address,delivery_address,package_name,weight,cod,fee,note,code) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',array_merge($values,[$code]));
   $id=(int)db()->lastInsertId(); query('INSERT INTO order_events(order_id,status,note) VALUES(?,?,?)',[$id,'pending','Đơn hàng được tạo bởi '.$_SESSION['user']['name']]); db()->commit();
   flash('Đã tạo đơn '.$code.'.'); redirect('/?page=detail&id='.$id);
  }
  if (in_array($action,['status','assign','settle'],true)) {
   $id=(int)($_POST['id']??0); db()->beginTransaction(); $order=query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])->fetch();
   if($role==='driver'&&(!$order||(int)$order['driver_id']!==(int)$_SESSION['user']['driver_id'])) deny();
   if(!$order) throw new InvalidArgumentException('Không tìm thấy đơn hàng.');
   if($action==='status') {
    $status=field('status',20);
    if($role==='driver'&&$status==='cancelled') deny();
    if($role==='driver'&&$status==='failed'&&trim((string)($_POST['note']??''))==='') throw new InvalidArgumentException('Vui lòng nhập lý do giao thất bại.');
    if(!in_array($status,NEXT_STATUS[$order['status']],true)) throw new InvalidArgumentException('Không thể chuyển sang trạng thái này.');
    if(in_array($status,['picked','shipping','delivered'])&&!$order['driver_id']) throw new InvalidArgumentException('Hãy phân công nhân viên trước khi cập nhật trạng thái.');
    query('UPDATE orders SET status=?,delivered_at=? WHERE id=?',[$status,$status==='delivered'?date('Y-m-d H:i:s'):null,$id]);
    query('INSERT INTO order_events(order_id,status,note) VALUES(?,?,?)',[$id,$status,field('note',500,false)?:'Cập nhật bởi '.$_SESSION['user']['name']]);
   } elseif($action==='assign') {
    if(in_array($order['status'],['delivered','cancelled'])) throw new InvalidArgumentException('Đơn hàng đã kết thúc.');
    $driver=(int)($_POST['driver_id']??0); $d=query('SELECT * FROM drivers WHERE id=? AND active=1',[$driver])->fetch();
    if(!$d) throw new InvalidArgumentException('Vui lòng chọn nhân viên đang hoạt động.');
    query('UPDATE orders SET driver_id=? WHERE id=?',[$driver,$id]); query('INSERT INTO order_events(order_id,status,note) VALUES(?,?,?)',[$id,$order['status'],'Phân công cho '.$d['name']]);
   } else {
    if($order['status']!=='delivered'||$order['cod_settled']) throw new InvalidArgumentException('Chỉ đối soát đơn đã giao và chưa đối soát.');
    query('UPDATE orders SET cod_settled=1 WHERE id=?',[$id]); query('INSERT INTO order_events(order_id,status,note) VALUES(?,?,?)',[$id,'delivered','Đã xác nhận đối soát COD: '.money($order['cod'])]);
   }
   db()->commit(); flash('Đã cập nhật đơn hàng.'); redirect('/?page=detail&id='.$id);
  }
 } catch(InvalidArgumentException $ex) { if(db()->inTransaction()) db()->rollBack(); $error=$ex->getMessage(); }
 catch(Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); error_log($ex->getMessage()); $error='Không thể lưu dữ liệu. Vui lòng kiểm tra thông tin và thử lại.'; }
}
if (empty($_SESSION['user'])) { require __DIR__.'/../app/login.php'; exit; }
if($_SESSION['user']['role']==='driver') {
 if(!in_array($page,['dashboard','detail','orders'])) deny();
 driverPortal($error);
}
if($page==='users') {if($_SESSION['user']['role']!=='admin')deny();require __DIR__.'/../app/users.php';exit;}
if(!in_array($page,['dashboard','orders','new','detail','customers','drivers','reports','export'])) $page='dashboard';
$search=trim((string)($_GET['q']??'')); $status=$_GET['status']??''; $where=[]; $params=[];
if($search!=='') { $where[]='(o.code LIKE ? OR o.recipient_name LIKE ? OR o.recipient_phone LIKE ? OR c.name LIKE ?)'; $params=array_fill(0,4,'%'.$search.'%'); }
if(isset(STATUSES[$status])) { $where[]='o.status=?'; $params[]=$status; }
$filter=$where?' WHERE '.implode(' AND ',$where):'';
$base=' FROM orders o JOIN customers c ON c.id=o.customer_id LEFT JOIN drivers d ON d.id=o.driver_id';
if($page==='export') {
 header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="giao-tin-'.date('Y-m-d').'.csv"');
 $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['Mã đơn','Khách hàng','Người nhận','Điện thoại','Địa chỉ giao','Nhân viên','Trạng thái','COD','Phí giao','Đối soát','Ngày tạo']);
 $rows=query('SELECT o.*,c.name customer_name,d.name driver_name'.$base.$filter.' ORDER BY o.created_at DESC,o.id DESC',$params);
 while($r=$rows->fetch()) { $row=[$r['code'],$r['customer_name'],$r['recipient_name'],$r['recipient_phone'],$r['delivery_address'],$r['driver_name'],STATUSES[$r['status']],$r['cod'],$r['fee'],$r['cod_settled']?'Đã đối soát':'Chưa đối soát',$r['created_at']]; $row=array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/',(string)$v)?"'".$v:$v,$row); fputcsv($out,$row); } fclose($out); exit;
}
$stats=query("SELECT COUNT(*) total,COALESCE(SUM(status='pending'),0) pending,COALESCE(SUM(status='shipping'),0) shipping,COALESCE(SUM(status='delivered'),0) delivered,COALESCE(SUM(status='failed'),0) failed,COALESCE(SUM(CASE WHEN status='delivered' THEN fee ELSE 0 END),0) revenue,COALESCE(SUM(CASE WHEN status='delivered' AND cod_settled=0 THEN cod ELSE 0 END),0) unsettled FROM orders")->fetch();
$customers=query('SELECT * FROM customers ORDER BY name')->fetchAll(); $drivers=query('SELECT * FROM drivers ORDER BY name')->fetchAll();
$titles=['dashboard'=>'Tổng quan','orders'=>'Đơn hàng','new'=>'Tạo đơn hàng','detail'=>'Chi tiết đơn hàng','customers'=>'Khách hàng','drivers'=>'Nhân viên giao hàng','reports'=>'Báo cáo & đối soát'];
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
require __DIR__.'/../app/layout.php';
