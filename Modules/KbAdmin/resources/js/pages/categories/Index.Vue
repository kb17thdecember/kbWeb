<template>
    <AdminLayout>
        <div class="page-head">
            <div>
                <h2>Quản lý danh mục</h2>
                <p>Quản lý danh mục chính và các dòng danh mục con.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createCategory">＋ Thêm danh mục</button>
                <input class="search" id="localSearch" placeholder="Tìm danh mục..." value="">
            </div>
        </div>

        <div class="cardgrid">
            <article class="card">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:10px">
                    <div>
                        <div class="eyebrow">ARISTON · 4 DÒNG CON</div>
                        <h3 style="font-size:17px;margin-top:8px">Ariston</h3>
                    </div>

                    <div class="row-actions">
                        <button class="mini" data-edit-category="ariston">Sửa</button>
                        <button class="mini danger" data-delete-category="ariston">Xóa</button>
                    </div>
                </div>

                <p>Thiết bị nước nóng cho nhà ở và dự án.</p>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                    <b style="font-size:10px">Dòng danh mục</b>
                    <button class="mini" data-create-sub="ariston">＋ Thêm dòng con</button>
                </div>

                <div class="tablewrap">
                    <table class="table" style="min-width:400px">
                        <thead>
                            <tr>
                                <th>Tên dòng danh mục</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <b>Bình nước nóng trực tiếp</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="ariston::direct">Sửa</button>
                                        <button class="mini danger" data-delete-sub="ariston::direct">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Bình nước nóng gián tiếp</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="ariston::indirect">Sửa</button>
                                        <button class="mini danger" data-delete-sub="ariston::indirect">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Bơm nhiệt nước nóng</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="ariston::heat-pump">Sửa</button>
                                        <button class="mini danger" data-delete-sub="ariston::heat-pump">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Thái dương năng</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="ariston::solar">Sửa</button>
                                        <button class="mini danger" data-delete-sub="ariston::solar">Xóa</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:10px">
                    <div>
                        <div class="eyebrow">KFLEX · 5 DÒNG CON</div>
                        <h3 style="font-size:17px;margin-top:8px">K-FLEX</h3>
                    </div>

                    <div class="row-actions">
                        <button class="mini" data-edit-category="kflex">Sửa</button>
                        <button class="mini danger" data-delete-category="kflex">Xóa</button>
                    </div>
                </div>

                <p>Vật liệu bảo ôn cho hệ thống kỹ thuật.</p>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                    <b style="font-size:10px">Dòng danh mục</b>
                    <button class="mini" data-create-sub="kflex">＋ Thêm dòng con</button>
                </div>

                <div class="tablewrap">
                    <table class="table" style="min-width:400px">
                        <thead>
                            <tr>
                                <th>Tên dòng danh mục</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <b>NBR / Elastomeric</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="kflex::nbr">Sửa</button>
                                        <button class="mini danger" data-delete-sub="kflex::nbr">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Polyolefin</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="kflex::polyolefin">Sửa</button>
                                        <button class="mini danger" data-delete-sub="kflex::polyolefin">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Bảo ôn dạng tấm</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="kflex::elastomeric">Sửa</button>
                                        <button class="mini danger" data-delete-sub="kflex::elastomeric">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Keo dán &amp; băng keo</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="kflex::adhesive">Sửa</button>
                                        <button class="mini danger" data-delete-sub="kflex::adhesive">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Phụ kiện bảo ôn</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="kflex::kflex-accessories">Sửa</button>
                                        <button class="mini danger" data-delete-sub="kflex::kflex-accessories">Xóa</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:10px">
                    <div>
                        <div class="eyebrow">HVAC · 3 DÒNG CON</div>
                        <h3 style="font-size:17px;margin-top:8px">HVAC</h3>
                    </div>

                    <div class="row-actions">
                        <button class="mini" data-edit-category="hvac">Sửa</button>
                        <button class="mini danger" data-delete-category="hvac">Xóa</button>
                    </div>
                </div>

                <p>Vật tư và phụ kiện điều hòa, thông gió.</p>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                    <b style="font-size:10px">Dòng danh mục</b>
                    <button class="mini" data-create-sub="hvac">＋ Thêm dòng con</button>
                </div>

                <div class="tablewrap">
                    <table class="table" style="min-width:400px">
                        <thead>
                            <tr>
                                <th>Tên dòng danh mục</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <b>Ống gió &amp; ống gió mềm</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="hvac::flex-duct">Sửa</button>
                                        <button class="mini danger" data-delete-sub="hvac::flex-duct">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Phụ kiện HVAC</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="hvac::duct-accessories">Sửa</button>
                                        <button class="mini danger" data-delete-sub="hvac::duct-accessories">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Vật liệu cách nhiệt HVAC</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="hvac::hvac-insulation">Sửa</button>
                                        <button class="mini danger" data-delete-sub="hvac::hvac-insulation">Xóa</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:10px">
                    <div>
                        <div class="eyebrow">MEP · 3 DÒNG CON</div>
                        <h3 style="font-size:17px;margin-top:8px">Vật tư MEP</h3>
                    </div>

                    <div class="row-actions">
                        <button class="mini" data-edit-category="mep">Sửa</button>
                        <button class="mini danger" data-delete-category="mep">Xóa</button>
                    </div>
                </div>

                <p>Thiết bị cơ điện và phụ kiện đường ống.</p>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                    <b style="font-size:10px">Dòng danh mục</b>
                    <button class="mini" data-create-sub="mep">＋ Thêm dòng con</button>
                </div>

                <div class="tablewrap">
                    <table class="table" style="min-width:400px">
                        <thead>
                            <tr>
                                <th>Tên dòng danh mục</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <b>Van &amp; thiết bị điều khiển</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="mep::valves">Sửa</button>
                                        <button class="mini danger" data-delete-sub="mep::valves">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Phụ kiện đường ống</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="mep::fittings">Sửa</button>
                                        <button class="mini danger" data-delete-sub="mep::fittings">Xóa</button>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Giá đỡ &amp; vật tư lắp đặt</b>
                                    <small>pipe-supports</small>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" data-edit-sub="mep::pipe-supports">Sửa</button>
                                        <button class="mini danger" data-delete-sub="mep::pipe-supports">Xóa</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import AdminLayout from '../../layout/AdminLayout.Vue';

    type Category = {
        id: number;
        name: string;
        description: string | null;
        description_2: string | null;
        image: string | null;
        children_count: number;
        products_count?: number;
        children: Category[];
    };

    defineProps<{
        categories: Category[];
    }>();
</script>