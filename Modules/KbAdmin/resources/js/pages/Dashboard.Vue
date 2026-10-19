<script setup lang="ts">
    import AdminLayout from '../layout/AdminLayout.Vue';
</script>

<template>
    <AdminLayout>
        <div class="cardgrid">
            <div class="card">
                <h3>Yêu cầu tư vấn trong 7 ngày gần nhất</h3>
                <p>Thống kê theo ngày tạo của từng yêu cầu.</p>

                <div class="chart">
                    <div class="barcol">
                        <span>0</span>
                        <div class="bar" style="height:4%"></div>
                        <span>Thứ 3</span>
                    </div>

                    <div class="barcol">
                        <span>1</span>
                        <div class="bar" style="height:100%"></div>
                        <span>Thứ 4</span>
                    </div>

                    <div class="barcol">
                        <span>0</span>
                        <div class="bar" style="height:4%"></div>
                        <span>Thứ 5</span>
                    </div>

                    <div class="barcol">
                        <span>1</span>
                        <div class="bar" style="height:100%"></div>
                        <span>Thứ 6</span>
                    </div>

                    <div class="barcol">
                        <span>1</span>
                        <div class="bar" style="height:100%"></div>
                        <span>Thứ 7</span>
                    </div>

                    <div class="barcol">
                        <span>1</span>
                        <div class="bar" style="height:100%"></div>
                        <span>CN</span>
                    </div>

                    <div class="barcol">
                        <span>0</span>
                        <div class="bar" style="height:4%"></div>
                        <span>Thứ 2</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Trạng thái xử lý yêu cầu</h3>
                <p>Theo dõi tiến độ tiếp nhận và xử lý khách hàng.</p>

                <div class="statusline">
                    <span class="dot" style="background:#5066f6"></span>
                    <div>
                        <b>Mới</b>
                        <small>Chờ tiếp nhận</small>
                    </div>
                    <strong>2</strong>
                </div>

                <div class="statusline">
                    <span class="dot" style="background:#e6a044"></span>
                    <div>
                        <b>Đang xử lý</b>
                        <small>Đang theo dõi</small>
                    </div>
                    <strong>1</strong>
                </div>

                <div class="statusline">
                    <span class="dot" style="background:#32a879"></span>
                    <div>
                        <b>Đã xử lý</b>
                        <small>Đã hoàn tất</small>
                    </div>
                    <strong>1</strong>
                </div>

                <div style="margin-top:16px">
                    <button class="btn primary" data-goto="requests">
                        Xem yêu cầu tư vấn ↗
                    </button>
                </div>
            </div>

            <div class="card">
                <h3>Thao tác nhanh</h3>
                <p>Cập nhật thông tin website.</p>

                <div class="shortcuts">
                    <button class="shortcut" data-create-product="">
                        ＋ Thêm sản phẩm
                        <small>Giá, mô tả, danh mục và thư viện ảnh</small>
                    </button>

                    <button class="shortcut" data-create-project="">
                        ＋ Thêm dự án
                        <small>Nhóm, vị trí, hạng mục và hình ảnh</small>
                    </button>

                    <button class="shortcut" data-goto="categories">
                        ✎ Quản lý danh mục
                        <small>Thêm, sửa hoặc xóa nhóm</small>
                    </button>

                    <button class="shortcut" data-goto="requests">
                        ☷ Xử lý yêu cầu
                        <small>Lọc theo thời gian và cập nhật trạng thái</small>
                    </button>
                </div>
            </div>

            <div class="card">
                <h3>Trạng thái dữ liệu</h3>
                <p>
                    Sản phẩm, dự án, danh mục và yêu cầu tư vấn dùng cấu trúc dữ liệu
                    tương thích với website NOVA TECH v19.
                </p>

                <span class="badge">● Lưu cục bộ</span>

                <p style="margin-top:13px">
                    Để đồng bộ trực tiếp, hai trang cần chạy cùng origin/domain và có
                    quyền sử dụng localStorage. Khi mở file riêng, có thể chuyển dữ
                    liệu bằng JSON.
                </p>

                <div class="actions">
                    <button class="btn" data-export="">Xuất bản sao</button>
                    <button class="btn" data-import="">Nhập bản sao</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
