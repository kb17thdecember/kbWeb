<script setup lang="ts">
import AdminLayout from '../../layout/AdminLayout.Vue';
</script>

<template>
    <AdminLayout>
        <div class="page-head">
            <div>
                <h2>Quản lý sản phẩm</h2>
                <p>Thêm, sửa, xóa mặt hàng và quản lý thư viện ảnh.</p>
            </div>
            <div class="actions">
                <button class="btn primary" data-create-product="">＋ Thêm sản phẩm</button>
                <input class="search" id="localSearch" placeholder="Tìm sản phẩm..." value="">
            </div>
        </div>
        <div class="tablewrap"><table class="table"><thead><tr><th>Sản phẩm</th><th>Danh mục</th><th>Giá tham khảo</th><th>Ảnh</th><th>Thao tác</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item">
                            <div class="thumb">
                                <span>T</span>
                            </div>
                            <div>
                                <b>Bình nước nóng Ariston Vitaly 20L</b>
                                <small>ARISTON · AR-V20</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        Ariston<small>Bình nước nóng gián tiếp</small>
                    </td>
                    <td>2.190.000 ₫</td>
                    <td>0 ảnh</td>
                    <td>
                        <div class="row-actions">
                            <button class="mini" data-edit-product="1">Sửa</button>
                            <button class="mini danger" data-delete-product="1">Xóa</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table></div>
    </AdminLayout>
</template>