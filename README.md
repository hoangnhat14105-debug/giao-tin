# Giao Tín

Ứng dụng quản lý giao hàng bằng **PHP 8 + MySQL/MariaDB**, giao diện tiếng Việt, tương thích điện thoại. Không cần Composer hoặc Node.js.

## Chức năng

- Đăng nhập quản trị, đăng xuất, mật khẩu được băm bằng PHP `password_hash`.
- Tổng quan đơn hàng, biểu đồ 7 ngày, thống kê trạng thái.
- Tạo đơn, lưu người gửi/người nhận, COD, phí giao, kiện hàng và ghi chú.
- Phân công nhân viên đang hoạt động, lịch sử phân công và trạng thái.
- Luồng: Chờ lấy → Đã lấy → Đang giao → Đã giao / Thất bại. Đơn thất bại được giao lại hoặc hủy. Đơn đã giao/hủy kết thúc luồng.
- Quản lý thông tin khách hàng, nhân viên và trạng thái hoạt động.
- Báo cáo phí giao từ đơn thành công; xác nhận đối soát COD từng đơn.
- Tìm kiếm, lọc trạng thái, phân trang, xuất CSV tương thích Excel và in chi tiết đơn.

## Cài đặt

Yêu cầu PHP 8.0+ có `pdo_mysql`, `mbstring`; MySQL 8+ hoặc MariaDB 10.4+.

1. Khởi động MySQL trong XAMPP hoặc dùng máy chủ MySQL hiện có.
2. Sao chép `.env.example` thành `.env`, điền host, cổng, tên database và tài khoản MySQL. `.env` không được đưa vào Git.
3. Khởi tạo database và tài khoản (thay email/mật khẩu):

```powershell
C:/xampp/php/php.exe scripts/install.php --email=admin@giaotin.local --password=MatKhauCuaBan123 --demo
```

Tùy chọn `--demo` thêm 30 đơn minh họa, 4 khách hàng và 3 nhân viên. Bỏ tùy chọn này để bắt đầu với dữ liệu trống. Lệnh cài đặt không thay đổi tài khoản nếu ứng dụng đã được cài đặt.

4. Chạy ứng dụng từ thư mục dự án:

```powershell
C:/xampp/php/php.exe -S 127.0.0.1:8080 -t public
```

5. Mở <http://127.0.0.1:8080> và đăng nhập bằng tài khoản đã tạo.

## Cấu hình hiện tại trên máy này

Ứng dụng kết nối MySQL server của bạn tại **127.0.0.1:3306**, database `giao_tin`. Điền đúng tài khoản và mật khẩu MySQL trong `.env` trước khi chạy lệnh cài đặt.

## Kiểm thử

```powershell
$env:GT_TEST_EMAIL='admin@giaotin.local'
$env:GT_TEST_PASSWORD='mật khẩu quản trị đã tạo'
C:/xampp/php/php.exe tests/smoke.php
```

Ứng dụng và MySQL phải đang chạy. Bộ kiểm thử đọc tài khoản kiểm thử từ `GT_TEST_EMAIL`, `GT_TEST_PASSWORD`; tạo dữ liệu có tiền tố `TEST`, kiểm tra luồng đầy đủ rồi xóa dữ liệu của chính lượt chạy đó. Không sử dụng trên dữ liệu thật.

## Triển khai

- Cấu hình Apache/Nginx với document root trỏ vào **`public/`**. Không phục vụ thư mục gốc của dự án, `.env`, `app`, `database`, `scripts` hoặc `storage`.
- Dùng HTTPS và tài khoản MySQL riêng chỉ có quyền thao tác trên database ứng dụng. Chỉ dùng quyền tạo database trong bước cài đặt.
- Máy chủ tích hợp `php -S` chỉ phục vụ phát triển cục bộ. Không sử dụng làm máy chủ công khai.
- Tắt `display_errors` trên production và cấu hình PHP lưu error log bên ngoài thư mục công khai.
- Sao lưu MySQL định kỳ trước khi nâng cấp; dữ liệu giao hàng được lưu bền vững trong MySQL.

### Deploy lên Render

Repo đã có `Dockerfile` cho Render. Cần một MySQL/MariaDB bên ngoài; Render Web Service không tự cung cấp database MySQL cho ứng dụng này.

1. Tạo database MySQL 8+ hoặc MariaDB 10.4+ ở nhà cung cấp có thể kết nối từ Render. Cho phép kết nối mạng từ Render theo hướng dẫn của nhà cung cấp và ghi lại host, port, database, username, password.
2. Push repo lên GitHub, vào Render chọn **New → Web Service**, kết nối repo và chọn runtime **Docker**. Để Render dùng `Dockerfile` ở thư mục gốc; ứng dụng lắng nghe port `10000` (giá trị `PORT` mặc định của Render).
3. Trong **Environment** của Web Service, thêm `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` theo thông tin MySQL và `APP_ENV=production`. Không commit `.env`; Docker cũng loại `.env` khỏi image.
4. Khởi tạo database và tài khoản quản trị một lần từ máy có PHP CLI, sau khi cấu hình các biến `DB_*` trỏ tới database ở trên:

   ```powershell
   C:/xampp/php/php.exe scripts/install.php --email=admin@example.com --password="mat-khau-it-nhat-10-ky-tu"
   ```

   Tài khoản database cần quyền tạo database và bảng trong lần cài đặt đầu tiên. Nếu nhà cung cấp đã tạo sẵn database, cấp quyền tạo schema cho tài khoản đó hoặc dùng tài khoản quản trị database để chạy bước cài đặt. Bỏ `--demo` để không tạo dữ liệu mẫu.
5. Render tự build và deploy khi push commit. Mở URL Render để kiểm tra; ứng dụng cần kết nối database đã khởi tạo. Lưu credential chỉ trong Render Environment, bật HTTPS và sao lưu database định kỳ.

Chưa tích hợp GPS, bản đồ, SMS hay cổng thanh toán. Đối soát là thao tác xác nhận nội bộ sau khi thanh toán thực tế. Giới hạn đăng nhập hiện áp dụng theo phiên; bổ sung rate limit theo IP ở reverse proxy khi triển khai công khai, kể cả đường dẫn tra cứu.

## Phân quyền, trang tài xế và tra cứu

- **Quản trị viên:** toàn bộ chức năng, tạo và khóa/mở khóa tài khoản ở mục **Quản lý tài khoản**. Không thể khóa chính tài khoản đang đăng nhập.
- **Điều phối viên:** quản lý đơn hàng, khách hàng, nhân viên, báo cáo và đối soát; không quản lý tài khoản.
- **Tài xế:** đăng nhập cùng địa chỉ ứng dụng và tự chuyển vào trang **Đơn giao của tôi**. Chỉ xem/cập nhật đơn được phân công; gọi người nhận; ghi lý do thất bại; không hủy đơn, tự phân công, xuất dữ liệu hoặc đối soát COD.
- Khi tạo tài khoản tài xế, chọn một nhân viên đang hoạt động chưa có tài khoản. Khóa tài khoản hoặc cho nhân viên tạm nghỉ sẽ ngăn sử dụng cả phiên đăng nhập hiện tại ở yêu cầu tiếp theo.
- Tra cứu: `/?page=track`, hoặc bấm **Tra cứu vận đơn** ở trang đăng nhập. Khách cần mã đơn và đầy đủ số điện thoại người nhận. Kết quả chỉ hiển thị trạng thái, thời gian; không hiển thị địa chỉ, số tiền hoặc ghi chú nội bộ.
- Các trang này hiện chạy trên máy cục bộ. Muốn khách truy cập từ Internet cần triển khai máy chủ với HTTPS.

Với database của phiên bản cũ, chạy một lần trước khi sử dụng phiên bản mới (không xóa dữ liệu):

```powershell
C:/xampp/php/php.exe scripts/migrate.php
```

Database trên máy này đã được cập nhật. Tài khoản quản trị cũ được giữ nguyên.

Kiểm thử phân quyền và tra cứu:

```powershell
C:/xampp/php/php.exe tests/roles.php
```

Bộ kiểm thử tạo tài khoản và đơn TEST, kiểm tra quyền đọc/ghi và tra cứu, rồi xóa dữ liệu của chính lượt chạy. Chỉ chạy trong môi trường phát triển.
