=== AO – Asset Optimizer (0.3.x) ===
Contributors: you
Tags: performance, optimize, css, javascript, fonts, lazyload, preload, preconnect
Requires at least: 5.8
Tested up to: 6.6
License: GPLv2 or later

= 0.3.3.4 =
- Compat Capture tôn trọng thiết lập "Bật theo route" (không còn tạo bundle cho route đã tắt).

= 0.3.3.3 =
- Thêm tùy chọn bỏ qua tối ưu cho người dùng đã đăng nhập (mặc định bật) để admin/test không bị ảnh hưởng.
- Giữ các vá trước: UI escape label `<link>/<script>`, sửa compat capture, đổi hook sang `wp` để tránh notice conditional tags, fix AO Debug.

= 0.3.3.2 =
- Fix Admin UI: escape label có ký tự < > trong phần Settings.

= 0.3.3.1 =
- Sửa parse error ở compat capture; đổi hook sang `wp`; tránh gọi conditional tags sớm; fix nhỏ AO Debug.

= 0.3.3 =
- Compat mode (opt-in): Bắt & gộp các thẻ <link rel="stylesheet"> và <script src> được theme/plugin in thẳng (không enqueue). Tự nhóm CSS theo media; JS chia nhóm head/foot.
- Gộp CSS theo media (kể cả media != all). Hash cache-bust; chỉ gộp asset local; skip CDN/external.
- Giữ an toàn Cart/Checkout.

= 0.3.3.5 =
- Sửa lỗi cú pháp ở ao-hints.php (thiếu dấu ;) gây Parse error.
- Tránh gọi conditional tags trên trang Admin (chặn ở AO_Util::current_route) để hết các notice is_search/is_embed.

= 0.3.3.6 =
- Sửa lỗi rewrite URL CSS với đường dẫn ../fonts/... gây 404 font.

= 0.3.3.7 =
- Chuyển toàn bộ cài đặt sang **1 menu top-level**: WP Admin → AO Optimizer (icon ⚡). Debug nằm dưới dạng submenu.
- Tải CSS/JS admin của plugin theo hook mới (toplevel_page_ao-optimizer / ao-optimizer_page_*).
- Nút Purge Cache và liên kết nội bộ cập nhật sang `admin.php?page=ao-optimizer`.
