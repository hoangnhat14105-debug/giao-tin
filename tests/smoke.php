<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$email=getenv('GT_TEST_EMAIL');$password=getenv('GT_TEST_PASSWORD');
if(!$email||!$password) exit("Đặt GT_TEST_EMAIL và GT_TEST_PASSWORD trước khi kiểm thử.\n");
$base=getenv('GT_TEST_URL')?:'http://127.0.0.1:8080';
$cookie=tempnam(__DIR__.'/../storage','test-cookie-');$token='';$customer=0;$driver=0;$order=0;$passed=0;$tag='TEST_'.bin2hex(random_bytes(6));
function request(string $path,array $data=[]): string {
 global $base,$cookie,$token;
 $c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>15]);
 if($data) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));}
 $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$err=curl_error($c);curl_close($c);
 if($body===false||$status>=500) throw new RuntimeException('HTTP '.$status.' '.$err);
 if(preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))$token=$m[1];
 return $body;
}
function check(bool $ok,string $name): void { global $passed; if(!$ok) throw new RuntimeException($name);$passed++;echo "PASS: $name\n"; }
function post(string $path,array $data): string {global $token;return request($path,['csrf'=>$token]+$data);}
try {
 check(strpos(request('/?page=orders'),'Đăng nhập Giao Tín')!==false,'Yêu cầu đăng nhập');
 check(strpos(post('/', ['action'=>'login','email'=>$email,'password'=>'invalid-password']),'không chính xác')!==false,'Từ chối mật khẩu sai');
 check(strpos(post('/', ['action'=>'login','email'=>$email,'password'=>$password]),'TRUNG TÂM ĐIỀU HÀNH')!==false,'Đăng nhập thành công');
 check(strpos(request('/?page=customers',['csrf'=>'invalid','action'=>'customer','name'=>$tag]),'hết hạn')!==false,'Chặn CSRF');
 $html=post('/?page=customers',['action'=>'customer','name'=>$tag,'phone'=>'0901234567','address'=>'Địa chỉ TEST']);
 $customer=(int)query('SELECT id FROM customers WHERE name=?',[$tag])->fetchColumn();check($customer>0,'Tạo khách hàng');
 post('/?page=customers&edit='.$customer,['action'=>'customer','id'=>$customer,'name'=>$tag,'phone'=>'0901234568','address'=>'Địa chỉ TEST đã sửa']);
 check(query('SELECT phone FROM customers WHERE id=?',[$customer])->fetchColumn()==='0901234568','Sửa khách hàng');
 post('/?page=drivers',['action'=>'driver','name'=>$tag,'phone'=>'0901234567','vehicle'=>'TEST-001','active'=>'1']);
 $driver=(int)query('SELECT id FROM drivers WHERE name=?',[$tag])->fetchColumn();check($driver>0,'Tạo nhân viên');
 $values=['action'=>'order','customer_id'=>$customer,'recipient_name'=>$tag,'recipient_phone'=>'0981234567','pickup_address'=>'Điểm lấy TEST','delivery_address'=>'Điểm giao TEST','package_name'=>'Kiện TEST','weight'=>'1.5','cod'=>'120000','fee'=>'25000','note'=>'<script>alert(1)</script>'];
 $invalid=$values;$invalid['cod']='-1';check(strpos(post('/?page=new',$invalid),'Số tiền phải')!==false,'Chặn số tiền âm');
 $html=post('/?page=new',$values);$order=(int)query('SELECT id FROM orders WHERE recipient_name=?',[$tag])->fetchColumn();check($order>0,'Tạo đơn hàng');
 check(strpos($html,'&lt;script&gt;alert(1)&lt;/script&gt;')!==false,'Mã hóa nội dung chống XSS');
 check(strpos(post('/?page=detail&id='.$order,['action'=>'status','id'=>$order,'status'=>'picked']),'phân công nhân viên')!==false,'Không lấy hàng khi chưa phân công');
 post('/?page=detail&id='.$order,['action'=>'assign','id'=>$order,'driver_id'=>$driver]);check((int)query('SELECT driver_id FROM orders WHERE id=?',[$order])->fetchColumn()===$driver,'Phân công nhân viên');
 check(strpos(post('/?page=detail&id='.$order,['action'=>'status','id'=>$order,'status'=>'delivered']),'Không thể chuyển')!==false,'Chặn nhảy trạng thái');
 foreach(['picked','shipping','failed','shipping','delivered'] as $status) {post('/?page=detail&id='.$order,['action'=>'status','id'=>$order,'status'=>$status]);check(query('SELECT status FROM orders WHERE id=?',[$order])->fetchColumn()===$status,'Chuyển trạng thái '.$status);}
 post('/?page=detail&id='.$order,['action'=>'settle','id'=>$order]);check((int)query('SELECT cod_settled FROM orders WHERE id=?',[$order])->fetchColumn()===1,'Đối soát COD');
 check(strpos(post('/?page=detail&id='.$order,['action'=>'settle','id'=>$order]),'Chỉ đối soát')!==false,'Chặn đối soát lặp');
 check(strpos(post('/?page=detail&id='.$order,['action'=>'status','id'=>$order,'status'=>'cancelled']),'Không thể chuyển')!==false,'Khóa trạng thái đơn đã giao');
 $html=request('/?page=orders&q='.urlencode($tag));check(strpos($html,$tag)!==false&&strpos($html,'1 đơn hàng')!==false,'Tìm kiếm đơn');
 $csv=request('/?page=export&q='.urlencode($tag));check(strpos($csv,$tag)!==false&&strpos($csv,'120000')!==false,'Xuất CSV theo bộ lọc');
 foreach(['dashboard','orders','new','customers','drivers','reports'] as $page) { $html=request('/?page='.$page);check(strpos($html,'Fatal error')===false&&strpos($html,'main-footer')!==false,'Trang '.$page); }
 check(strpos(post('/',['action'=>'logout']),'Đăng nhập Giao Tín')!==false,'Đăng xuất');
 echo "\n$passed kiểm tra thành công.\n";
} catch(Throwable $e) {fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");$failed=true;}
finally {
 if($order){query('DELETE FROM order_events WHERE order_id=?',[$order]);query('DELETE FROM orders WHERE id=? AND recipient_name=?',[$order,$tag]);}
 if($driver)query('DELETE FROM drivers WHERE id=? AND name=?',[$driver,$tag]);
 if($customer)query('DELETE FROM customers WHERE id=? AND name=?',[$customer,$tag]);
 if(is_file($cookie))unlink($cookie);
}
exit(isset($failed)?1:0);
