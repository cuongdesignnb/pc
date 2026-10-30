<?php

/*
 * The policy pages Bộ Công Thương requires, adapted from the shop's sample
 * documents. {{tokens}} are filled from Cài đặt → Thông tin pháp lý / Liên hệ
 * when the page is served, so the shop edits its details in one place.
 *
 * Used by the 2026_11_02 migration; the shop edits the pages in Admin → Trang tĩnh.
 */

return [
    [
        'slug' => 'chinh-sach-bao-hanh',
        'title' => 'CHÍNH SÁCH BẢO HÀNH',
        'meta_title' => 'Chính sách bảo hành',
        'meta_description' => 'Hình thức, điều kiện và địa điểm bảo hành laptop, linh kiện, phụ kiện tại {{ten_website}}.',
        'body' => <<<'HTML'
<h2>1. Các hình thức bảo hành</h2>
<p>Sản phẩm bán ra bởi {{ten_doanh_nghiep}} được bảo hành đối với các lỗi kỹ thuật do nhà sản xuất hoặc lỗi phần cứng phát sinh trong quá trình sử dụng bình thường. Thời hạn bảo hành của từng sản phẩm được ghi trên trang sản phẩm, trên phiếu bảo hành và hoá đơn bán hàng (khách có thể chọn thêm gói bảo hành mở rộng khi mua nếu sản phẩm có hỗ trợ).</p>
<ul>
<li><strong>Bảo hành 1 đổi 1:</strong> trong 07 ngày kể từ ngày mua hoặc nhận hàng, sản phẩm lỗi phần cứng thoả mãn điều kiện dưới đây được đổi sang sản phẩm cùng model, cùng cấu hình.</li>
<li><strong>Bảo hành miễn phí:</strong> trong thời hạn bảo hành, sản phẩm lỗi được sửa chữa hoặc thay thế linh kiện miễn phí tại cửa hàng.</li>
<li><strong>Đổi trả, hoàn tiền:</strong> thực hiện theo <a href="/chinh-sach-giao-hang-doi-tra-va-hoan-tien">Chính sách giao hàng, đổi trả và hoàn tiền</a>.</li>
</ul>
<p><em>Lưu ý:</em> thời hạn bảo hành không được tính lại sau khi sản phẩm được sửa chữa hoặc bảo hành.</p>
<h2>2. Điều kiện bảo hành 1 đổi 1</h2>
<ul>
<li>Sản phẩm mua tại {{ten_doanh_nghiep}}, còn trong 07 ngày tính đến ngày cửa hàng nhận bảo hành.</li>
<li>Sản phẩm gặp lỗi phần cứng được kỹ thuật viên của cửa hàng xác nhận.</li>
<li>Sản phẩm chưa qua sửa chữa ở nơi khác, người dùng chưa tự tháo mở hoặc can thiệp phần cứng.</li>
<li>Sản phẩm không bị tác động của môi trường (vào nước, hoá chất, quá nhiệt do tác động bên ngoài).</li>
<li>Còn nguyên tem bảo hành, số serial (S/N) trùng với thông tin bảo hành của cửa hàng.</li>
<li>Trường hợp hết sản phẩm cùng loại, khách được đổi sang sản phẩm khác có giá trị tương đương hoặc cao hơn (bù chênh lệch), hoặc hoàn tiền theo chính sách đổi trả.</li>
</ul>
<h2>3. Điều kiện bảo hành miễn phí</h2>
<ul>
<li>Sản phẩm còn trong thời hạn bảo hành, tính từ ngày giao hàng hoặc ngày ghi trên phiếu bảo hành.</li>
<li>Có phiếu bảo hành hoặc hoá đơn do {{ten_doanh_nghiep}} phát hành, hoặc số điện thoại dùng khi mua hàng còn lưu trên hệ thống bán hàng.</li>
<li>Bảo hành áp dụng cho sản phẩm chính. Phụ kiện đi kèm (sạc, chuột, túi…) bảo hành theo thời hạn riêng nếu có ghi trên phiếu bảo hành.</li>
<li>Sản phẩm mới chính hãng còn được bảo hành theo quy định của từng nhà sản xuất.</li>
</ul>
<h2>4. Các trường hợp không được bảo hành</h2>
<ul>
<li>Sản phẩm đã hết thời hạn bảo hành.</li>
<li>Số serial trên máy và thông tin bảo hành không trùng khớp hoặc không xác định được; tem bảo hành bị rách, sửa đổi.</li>
<li>Hư hỏng do sử dụng không đúng hướng dẫn, dùng sai nguồn điện, bộ sạc.</li>
<li>Hư hỏng do rơi, vỡ, va đập, móp méo, vào nước, ẩm ướt, hoen rỉ.</li>
<li>Hư hỏng do chuột bọ, côn trùng, hoả hoạn, thiên tai.</li>
<li>Sản phẩm đã bị sửa chữa bởi cá nhân, tổ chức không phải {{ten_doanh_nghiep}}.</li>
<li>Lỗi phần mềm, virus, mất dữ liệu (cửa hàng hỗ trợ cài đặt lại nhưng không chịu trách nhiệm về dữ liệu của khách).</li>
</ul>
<h2>5. Địa điểm bảo hành</h2>
<ul>
<li>Địa chỉ: {{dia_chi}}</li>
<li>Hotline: {{hotline}}</li>
<li>Thời gian: {{gio_lam_viec}}</li>
</ul>
HTML,
    ],
    [
        'slug' => 'chinh-sach-bao-mat-va-bao-ve-du-lieu-ca-nhan',
        'title' => 'CHÍNH SÁCH BẢO MẬT VÀ BẢO VỆ DỮ LIỆU CÁ NHÂN',
        'meta_title' => 'Chính sách bảo mật thông tin cá nhân',
        'meta_description' => 'Mục đích, phạm vi sử dụng và cách {{ten_website}} bảo vệ thông tin cá nhân của khách hàng.',
        'body' => <<<'HTML'
<h2>1. Mục đích thu thập thông tin cá nhân</h2>
<p>Website {{website}} thu thập thông tin cá nhân của khách hàng gồm họ tên, số điện thoại, email và địa chỉ giao hàng nhằm:</p>
<ul>
<li>Xử lý đơn hàng, xác nhận giao dịch và giao sản phẩm tới khách hàng.</li>
<li>Chăm sóc khách hàng, tư vấn kỹ thuật, hỗ trợ bảo hành và sửa chữa.</li>
<li>Thông báo trạng thái đơn hàng, xác nhận thanh toán, giải quyết thắc mắc phát sinh.</li>
<li>Gửi thông tin khuyến mãi, ưu đãi (chỉ khi khách hàng đồng ý).</li>
</ul>
<h2>2. Phạm vi sử dụng thông tin</h2>
<p>Thông tin chỉ được sử dụng trong nội bộ {{ten_doanh_nghiep}} và phục vụ trực tiếp việc thực hiện đơn hàng. Chúng tôi cam kết không bán, chia sẻ hay trao đổi thông tin cá nhân của khách hàng cho bên thứ ba, trừ đơn vị vận chuyển phục vụ giao hàng và đơn vị thanh toán phục vụ xác nhận giao dịch.</p>
<h2>3. Thời gian lưu trữ thông tin</h2>
<p>Dữ liệu cá nhân được lưu trữ an toàn trên hệ thống máy chủ của {{ten_doanh_nghiep}} cho đến khi khách hàng yêu cầu huỷ bỏ, hoặc theo thời hạn pháp luật quy định.</p>
<h2>4. Những người hoặc tổ chức có thể được tiếp cận thông tin</h2>
<ul>
<li>Bộ phận bán hàng, chăm sóc khách hàng và kỹ thuật của {{ten_doanh_nghiep}}.</li>
<li>Đơn vị vận chuyển, chuyển phát phục vụ giao nhận hàng.</li>
<li>Cơ quan nhà nước có thẩm quyền khi có yêu cầu bằng văn bản theo quy định của pháp luật.</li>
</ul>
<h2>5. Đơn vị thu thập và quản lý thông tin</h2>
<ul>
<li>{{ten_doanh_nghiep}}</li>
<li>Địa chỉ: {{dia_chi}}</li>
<li>Hotline: {{hotline}}</li>
<li>Email: {{email}}</li>
</ul>
<h2>6. Phương thức tiếp cận và chỉnh sửa dữ liệu cá nhân</h2>
<p>Khách hàng có quyền tự kiểm tra, cập nhật thông tin trong mục Tài khoản trên website, hoặc yêu cầu {{ten_doanh_nghiep}} kiểm tra, điều chỉnh, huỷ bỏ thông tin cá nhân bằng cách liên hệ hotline hoặc email nêu trên.</p>
<h2>7. Cam kết bảo mật</h2>
<p>Thông tin được truyền qua kết nối mã hoá (HTTPS); mật khẩu tài khoản được mã hoá một chiều. Khi phát hiện thông tin bị truy cập trái phép, chúng tôi sẽ thông báo cho khách hàng và cơ quan chức năng theo quy định.</p>
HTML,
    ],
    [
        'slug' => 'chinh-sach-gia',
        'title' => 'CHÍNH SÁCH GIÁ',
        'meta_title' => 'Chính sách giá',
        'meta_description' => 'Quy định về giá niêm yết, thuế và chi phí phát sinh khi mua hàng tại {{ten_website}}.',
        'body' => <<<'HTML'
<ul>
<li>Giá niêm yết trên website {{website}} là giá bán chính thức của sản phẩm tại thời điểm truy cập, tính bằng Việt Nam đồng.</li>
<li>Giá niêm yết đã bao gồm thuế giá trị gia tăng (nếu có) theo quy định hiện hành, trừ sản phẩm có ghi chú riêng.</li>
<li>Giá chưa bao gồm phí vận chuyển (nếu có), gói bảo hành mở rộng khách chọn thêm, hoặc dịch vụ cài đặt, nâng cấp theo yêu cầu riêng. Các khoản này được hiển thị rõ ở bước thanh toán hoặc được thông báo, thống nhất trước với khách hàng.</li>
<li>{{ten_doanh_nghiep}} có thể thay đổi giá niêm yết theo chương trình khuyến mãi hoặc biến động thị trường. Giá áp dụng cho đơn hàng là giá đã xác nhận tại thời điểm đặt hàng thành công.</li>
<li>Trường hợp giá hiển thị sai do lỗi hệ thống, cửa hàng sẽ liên hệ khách hàng để xác nhận lại trước khi giao hàng; khách hàng có quyền huỷ đơn mà không mất phí.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'chinh-sach-thanh-toan',
        'title' => 'CHÍNH SÁCH THANH TOÁN',
        'meta_title' => 'Phương thức thanh toán',
        'meta_description' => 'Các phương thức thanh toán được hỗ trợ khi mua hàng tại {{ten_website}}.',
        'body' => <<<'HTML'
<p>{{ten_doanh_nghiep}} hỗ trợ các phương thức thanh toán sau:</p>
<h2>1. Thanh toán khi nhận hàng (COD)</h2>
<p>Khách hàng thanh toán tiền mặt cho nhân viên giao hàng sau khi kiểm tra sản phẩm. Áp dụng cho khu vực có hỗ trợ thu hộ của đơn vị vận chuyển.</p>
<h2>2. Chuyển khoản ngân hàng</h2>
<p>Khách hàng chuyển khoản bằng mã QR hoặc thông tin tài khoản ngân hàng chính thức của cửa hàng hiển thị ở bước hoàn tất đơn hàng. Hệ thống tự động xác nhận khi nhận được tiền. Một số đơn hàng có thể chọn đặt cọc trước một phần, phần còn lại thanh toán khi nhận hàng.</p>
<p>Cửa hàng không yêu cầu khách chuyển khoản vào tài khoản cá nhân khác ngoài tài khoản hiển thị trên website.</p>
<h2>3. Thanh toán trực tiếp tại cửa hàng</h2>
<p>Bằng tiền mặt, thẻ hoặc chuyển khoản tại địa chỉ: {{dia_chi}}.</p>
HTML,
    ],
    [
        'slug' => 'chinh-sach-giao-hang-doi-tra-va-hoan-tien',
        'title' => 'CHÍNH SÁCH GIAO HÀNG, ĐỔI TRẢ VÀ HOÀN TIỀN',
        'meta_title' => 'Chính sách giao hàng, đổi trả và hoàn tiền',
        'meta_description' => 'Thời gian giao hàng, điều kiện đổi trả và hoàn tiền khi mua hàng tại {{ten_website}}.',
        'body' => <<<'HTML'
<h2>1. Chính sách giao hàng</h2>
<ul>
<li>Nội thành Hà Nội: giao trong ngày hoặc theo thời gian thoả thuận với khách hàng.</li>
<li>Tỉnh, thành phố khác: giao qua đơn vị chuyển phát từ 2 – 5 ngày làm việc.</li>
<li>Phí giao hàng được hiển thị ở bước thanh toán, tính theo biểu phí của đơn vị vận chuyển hoặc chương trình miễn phí vận chuyển của cửa hàng tại từng thời điểm.</li>
<li>Khách hàng được kiểm tra ngoại quan sản phẩm (mẫu mã, tình trạng, phụ kiện) trước khi nhận hàng.</li>
</ul>
<h2>2. Điều kiện đổi trả</h2>
<p>Sản phẩm được đổi trả trong vòng 07 ngày kể từ ngày nhận hàng khi:</p>
<ul>
<li>Sản phẩm phát sinh lỗi kỹ thuật được kỹ thuật viên xác nhận là lỗi phần cứng hoặc lỗi của nhà sản xuất.</li>
<li>Sản phẩm bị hư hỏng, trầy xước, móp méo trong quá trình vận chuyển.</li>
<li>Sản phẩm giao không đúng model, cấu hình, màu sắc như đơn hàng đã xác nhận.</li>
</ul>
<p>Sản phẩm đổi trả cần còn nguyên tem bảo hành, đầy đủ phụ kiện, hộp (nếu có) và chứng từ bán hàng (hoá đơn, phiếu bảo hành). Không áp dụng đổi trả khi sản phẩm bị lỗi do người dùng như mô tả tại <a href="/chinh-sach-bao-hanh">Chính sách bảo hành</a>.</p>
<h2>3. Hoàn tiền</h2>
<ul>
<li>Khi sản phẩm đủ điều kiện đổi trả nhưng cửa hàng không còn sản phẩm tương đương để thay thế, hoặc khách hàng huỷ đơn đã thanh toán trước khi giao, {{ten_doanh_nghiep}} hoàn tiền cho khách hàng.</li>
<li>Hình thức: chuyển khoản vào tài khoản ngân hàng của khách hàng trong 3 – 5 ngày làm việc sau khi tiếp nhận và kiểm tra xong sản phẩm.</li>
<li>Phí vận chuyển đổi trả do lỗi của nhà sản xuất hoặc của cửa hàng do {{ten_doanh_nghiep}} chi trả.</li>
</ul>
<h2>4. Dịch vụ sửa chữa</h2>
<p>Với dịch vụ sửa chữa, nâng cấp: cửa hàng báo giá và thời gian trước khi thực hiện. Khách hàng có thể dừng dịch vụ trước khi cửa hàng tiến hành sửa chữa mà không mất phí; khi linh kiện đã thay thế, phí linh kiện và công thực hiện không được hoàn lại, trừ trường hợp lỗi do cửa hàng.</p>
HTML,
    ],
    [
        'slug' => 'quy-trinh-giai-quyet-khieu-nai',
        'title' => 'TIẾP NHẬN VÀ GIẢI QUYẾT KHIẾU NẠI',
        'meta_title' => 'Phương thức tiếp nhận và giải quyết phản ánh, khiếu nại',
        'meta_description' => 'Quy trình tiếp nhận và giải quyết phản ánh, yêu cầu, khiếu nại của khách hàng tại {{ten_website}}.',
        'body' => <<<'HTML'
<p>{{ten_doanh_nghiep}} tôn trọng và bảo vệ quyền lợi của người tiêu dùng. Phản ánh, yêu cầu và khiếu nại được giải quyết theo 4 bước:</p>
<h2>Bước 1: Tiếp nhận</h2>
<p>Khách hàng gửi phản ánh, khiếu nại qua:</p>
<ul>
<li>Hotline: {{hotline}}</li>
<li>Email: {{email}}</li>
<li>Trực tiếp tại cửa hàng: {{dia_chi}} ({{gio_lam_viec}})</li>
</ul>
<h2>Bước 2: Xác minh và kiểm tra</h2>
<p>Bộ phận chăm sóc khách hàng đối soát thông tin đơn hàng, tình trạng sản phẩm và liên hệ lại khách hàng trong vòng 24 – 48 giờ làm việc.</p>
<h2>Bước 3: Đưa ra phương án xử lý</h2>
<p>Cửa hàng thương lượng với khách hàng phương án phù hợp: sửa chữa, bảo hành, đổi sản phẩm, hoàn tiền hoặc bồi thường tuỳ mức độ lỗi.</p>
<h2>Bước 4: Hoàn tất</h2>
<p>Thực hiện phương án đã thống nhất và đóng hồ sơ khiếu nại. Trường hợp không thoả thuận được, các bên có thể đề nghị cơ quan quản lý nhà nước có thẩm quyền hoặc Toà án giải quyết theo quy định của pháp luật.</p>
HTML,
    ],
    [
        'slug' => 'dieu-kien-cung-cap-hang-hoa-dich-vu',
        'title' => 'ĐIỀU KIỆN VÀ HẠN CHẾ CUNG CẤP HÀNG HOÁ, DỊCH VỤ',
        'meta_title' => 'Điều kiện và hạn chế cung cấp hàng hoá, dịch vụ',
        'meta_description' => 'Phạm vi, điều kiện và giới hạn cung cấp hàng hoá, dịch vụ của {{ten_website}}.',
        'body' => <<<'HTML'
<ul>
<li><strong>Phạm vi cung cấp:</strong> {{ten_doanh_nghiep}} bán và giao hàng trên phạm vi toàn quốc. Dịch vụ sửa chữa, nâng cấp thực hiện tại cửa hàng.</li>
<li><strong>Hạn chế về địa lý, thời gian:</strong> với vùng sâu, vùng xa, hải đảo, thời gian giao hàng có thể dài hơn thông thường; một số khu vực không hỗ trợ thanh toán khi nhận hàng.</li>
<li><strong>Hạn chế về số lượng:</strong> sản phẩm có số lượng giới hạn hoặc thuộc chương trình khuyến mãi có thể bị giới hạn số lượng mua trên mỗi khách hàng.</li>
<li><strong>Tình trạng sản phẩm:</strong> sản phẩm đã qua sử dụng, tân trang làm mới được ghi rõ trên trang sản phẩm; hình ảnh minh hoạ có thể khác biệt nhỏ về ngoại quan so với máy thực tế.</li>
<li><strong>Nghĩa vụ của người bán:</strong> cung cấp đúng chủng loại, cấu hình, tình trạng sản phẩm như đã công bố, kèm chứng từ bán hàng, phiếu bảo hành và hỗ trợ kỹ thuật.</li>
<li><strong>Nghĩa vụ của khách hàng:</strong> cung cấp thông tin nhận hàng chính xác, kiểm tra hàng khi nhận và tuân thủ hướng dẫn sử dụng, cảnh báo an toàn của nhà sản xuất.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'quyen-va-nghia-vu-cac-ben',
        'title' => 'QUYỀN VÀ NGHĨA VỤ CỦA CÁC BÊN',
        'meta_title' => 'Quyền và nghĩa vụ của các bên',
        'meta_description' => 'Quyền và nghĩa vụ của {{ten_website}} và khách hàng khi mua bán trên website.',
        'body' => <<<'HTML'
<h2>1. Quyền và nghĩa vụ của {{ten_doanh_nghiep}} (bên bán)</h2>
<h3>Nghĩa vụ</h3>
<ul>
<li>Cung cấp đúng, đủ sản phẩm theo mẫu mã, cấu hình, tình trạng, số lượng và chất lượng đã cam kết.</li>
<li>Công bố trung thực thông tin sản phẩm: xuất xứ, năm sản xuất, thông số kỹ thuật, tình trạng (mới, đã qua sử dụng, tân trang làm mới), chế độ bảo hành, cảnh báo và hướng dẫn sử dụng.</li>
<li>Giao hàng đúng thời gian, địa điểm đã thoả thuận; cung cấp hoá đơn, chứng từ theo quy định pháp luật.</li>
<li>Thực hiện bảo hành, hỗ trợ kỹ thuật và giải quyết khiếu nại theo các chính sách công bố trên website {{website}}.</li>
<li>Bảo mật thông tin cá nhân của khách hàng theo <a href="/chinh-sach-bao-mat-va-bao-ve-du-lieu-ca-nhan">Chính sách bảo mật</a>.</li>
</ul>
<h3>Quyền</h3>
<ul>
<li>Yêu cầu khách hàng thanh toán đầy đủ, đúng hạn theo phương thức đã chọn.</li>
<li>Từ chối giao hàng hoặc huỷ đơn khi khách hàng không đáp ứng điều kiện thanh toán hoặc cung cấp sai thông tin nhận hàng.</li>
<li>Từ chối bảo hành với sản phẩm vi phạm điều kiện bảo hành (tự ý tháo mở, rơi vỡ, vào nước, sử dụng sai hướng dẫn…).</li>
</ul>
<h2>2. Quyền và nghĩa vụ của khách hàng (bên mua)</h2>
<h3>Nghĩa vụ</h3>
<ul>
<li>Cung cấp chính xác thông tin cá nhân, địa chỉ nhận hàng, số điện thoại khi đặt hàng.</li>
<li>Thanh toán đầy đủ, đúng hạn giá trị đơn hàng theo phương thức đã chọn.</li>
<li>Kiểm tra tình trạng hàng hoá (bao bì, tem, phụ kiện) khi nhận hàng.</li>
<li>Đọc và tuân thủ hướng dẫn sử dụng, cảnh báo an toàn, điều kiện bảo hành do cửa hàng và nhà sản xuất cung cấp.</li>
</ul>
<h3>Quyền</h3>
<ul>
<li>Được nhận đúng sản phẩm đã đặt mua với chất lượng, nguồn gốc và chứng từ hợp lệ.</li>
<li>Được tư vấn, hỗ trợ kỹ thuật, bảo hành, đổi trả theo chính sách công bố công khai trên website.</li>
<li>Được yêu cầu kiểm tra, cập nhật hoặc huỷ bỏ thông tin cá nhân lưu trên hệ thống.</li>
<li>Được phản ánh, khiếu nại theo <a href="/quy-trinh-giai-quyet-khieu-nai">quy trình tiếp nhận và giải quyết khiếu nại</a>.</li>
</ul>
HTML,
    ],
];
