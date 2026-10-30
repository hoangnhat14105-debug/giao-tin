<?php
function portalHeader(string $title): void { ?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Giao Tín</title><link rel="stylesheet" href="/assets/app.css"></head><body><main style="max-width:1000px;margin:auto;padding:24px"><div class="page-heading"><a class="brand" href="/"><span class="brand-mark">G</span>Giao Tín</a><a class="text-link" href="/?page=track">Tra cứu vận đơn</a></div><?php }
function portalEnd(): void {echo '</main></body></html>';}
function deny(): void {http_response_code(403);portalHeader('Không có quyền truy cập');echo '<section class="panel form-panel"><h1>Không có quyền truy cập</h1><p>Tài khoản của bạn không được phép thực hiện thao tác này.</p><a class="btn" href="/">Về trang chính</a></section>';portalEnd();exit;}
function tracking(): void {
 $result=null;$searched=false;
 if($_SERVER['REQUEST_METHOD']==='POST') {
  $searched=true;
  if(hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) {
   $code=strtoupper(trim((string)($_POST['code']??'')));$phone=preg_replace('/\D/','',(string)($_POST['phone']??''));
   if(strlen($code)<=30&&strlen($phone)>=8&&strlen($phone)<=15) {
    $candidate=query('SELECT id,code,status,created_at,updated_at,recipient_phone FROM orders WHERE code=?',[$code])->fetch();
    if($candidate&&hash_equals(preg_replace('/\D/','',$candidate['recipient_phone']),$phone))$result=$candidate;
   }
  }
 }
 header('Cache-Control: no-store');portalHeader('Tra cứu vận đơn'); ?>
 <section class="panel form-panel"><span class="eyebrow">THEO DÕI HÀNH TRÌNH</span><h1>Đơn hàng của bạn đến đâu rồi?</h1><p class="muted">Nhập mã vận đơn và số điện thoại người nhận để xem tiến độ.</p><form method="post" style="margin-top:25px"><?=csrf()?><div class="form-grid"><label>Mã vận đơn<input name="code" required maxlength="30" placeholder="GT..." value="<?=e($_POST['code']??'')?>"></label><label>Số điện thoại người nhận<input type="tel" name="phone" required maxlength="25" autocomplete="off" value="<?=e($_POST['phone']??'')?>"></label></div><button class="btn primary">Tra cứu vận đơn</button> <a class="btn" href="/?page=login">Đăng nhập nhân viên</a></form></section>
 <?php if($searched&&!$result): ?><div class="alert error">Không tìm thấy vận đơn phù hợp. Vui lòng kiểm tra mã đơn và số điện thoại người nhận.</div><?php endif ?>
 <?php if($result): ?><section class="panel form-panel"><h2><?=e($result['code'])?> · <?=e(STATUSES[$result['status']])?></h2><p class="muted">Cập nhật: <?=date('H:i d/m/Y',strtotime($result['updated_at']))?></p><div class="timeline" style="margin-top:25px"><?php foreach(query('SELECT status,MIN(created_at) created_at FROM order_events WHERE order_id=? GROUP BY status ORDER BY created_at DESC',[$result['id']])->fetchAll() as $event): ?><article><span class="timeline-dot"></span><div><strong><?=e(STATUSES[$event['status']]??'Cập nhật đơn hàng')?></strong><p><?=date('H:i d/m/Y',strtotime($event['created_at']))?></p></div></article><?php endforeach ?></div></section><?php endif;
 portalEnd();exit;
}
function driverPortal(string $error): void {
 $driver=(int)$_SESSION['user']['driver_id'];$id=(int)($_GET['id']??0);
 if($id&&!query('SELECT id FROM orders WHERE id=? AND driver_id=?',[$id,$driver])->fetch())deny();
 portalHeader('Đơn giao của tôi');$flash=$_SESSION['flash']??null;unset($_SESSION['flash']); ?>
 <div class="page-heading"><div><span class="eyebrow">KHÔNG GIAN TÀI XẾ</span><h1>Xin chào, <?=e($_SESSION['user']['name'])?></h1><p>Chỉ các đơn được phân công cho bạn xuất hiện ở đây.</p></div><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button class="btn">Đăng xuất</button></form></div>
 <?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif ?><?php if($flash): ?><div class="alert"><?=e($flash['text'])?></div><?php endif ?>
 <?php $summary=query("SELECT COUNT(*) total,COALESCE(SUM(status IN ('pending','picked','shipping','failed')),0) working,COALESCE(SUM(CASE WHEN status='delivered' AND cod_settled=0 THEN cod ELSE 0 END),0) cod FROM orders WHERE driver_id=?",[$driver])->fetch(); ?><div class="stats-grid"><div class="stat-card">Đơn được giao<strong class="stat-value"><?=$summary['total']?></strong></div><div class="stat-card">Cần xử lý<strong class="stat-value"><?=$summary['working']?></strong></div><div class="stat-card">COD chưa đối soát<strong class="stat-value"><?=money($summary['cod'])?></strong></div></div>
 <?php $filter=$_GET['status']??'working'; ?><div class="actions" style="margin-bottom:20px"><a class="btn" href="/?status=working">Cần xử lý</a><a class="btn" href="/?status=delivered">Đã giao</a><a class="btn" href="/?status=all">Tất cả</a></div>
 <?php $sql='SELECT * FROM orders WHERE driver_id=?';$args=[$driver];if($id){$sql.=' AND id=?';$args[]=$id;}elseif($filter==='working')$sql.=" AND status IN ('pending','picked','shipping','failed')";elseif($filter==='delivered')$sql.=" AND status='delivered'";
 $rows=query($sql.' ORDER BY created_at DESC,id DESC',$args)->fetchAll();foreach($rows as $o): ?>
 <section class="panel form-panel"><div class="page-heading"><h2><?=e($o['code'])?></h2><span class="chip"><?=e(STATUSES[$o['status']])?></span></div><h3><?=e($o['recipient_name'])?></h3><p><?=e($o['delivery_address'])?></p><a class="btn" href="tel:<?=e(preg_replace('/[^+0-9]/','',$o['recipient_phone']))?>">Gọi <?=e($o['recipient_phone'])?></a><div class="form-note">Lấy hàng: <?=e($o['pickup_address'])?></div><p><?=e($o['package_name'])?> · <?=e($o['weight'])?> kg · COD: <strong><?=money($o['cod'])?></strong></p><?php if($o['note']): ?><p>Ghi chú: <?=e($o['note'])?></p><?php endif ?>
 <?php $next=array_values(array_diff(NEXT_STATUS[$o['status']],['cancelled']));if($next): ?><form method="post" style="margin-top:18px"><?=csrf()?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$o['id']?>"><div class="form-grid"><label>Trạng thái tiếp theo<select name="status"><?php foreach($next as $s): ?><option value="<?=$s?>"><?=STATUSES[$s]?></option><?php endforeach ?></select></label><label>Ghi chú / lý do thất bại<input name="note" maxlength="500" placeholder="Bắt buộc khi giao thất bại"></label></div><button class="btn primary">Cập nhật đơn hàng</button></form><?php endif ?></section>
 <?php endforeach;if(!$rows): ?><section class="panel empty">Chưa có đơn hàng trong danh sách này.</section><?php endif;portalEnd();exit;
}
