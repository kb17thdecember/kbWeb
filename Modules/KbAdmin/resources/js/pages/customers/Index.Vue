<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý khách hàng</h2>
                <p>Theo dõi tài khoản khách hàng đăng ký, thông tin liên hệ và lịch sử yêu cầu tư vấn.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="openCreateModal">＋ Thêm khách hàng</button>
                <input
                    v-model="search"
                    class="search"
                    type="search"
                    placeholder="Tìm tên, email, SĐT..."
                    aria-label="Tìm kiếm khách hàng"
                >
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <span>TỪ NGÀY</span>
                <AdminDatePicker v-model="dateFrom" placeholder="Chọn từ ngày..." aria-label="Lọc khách hàng từ ngày" />
            </div>
            <div class="filter-group">
                <span>ĐẾN NGÀY</span>
                <AdminDatePicker v-model="dateTo" placeholder="Chọn đến ngày..." aria-label="Lọc khách hàng đến ngày" />
            </div>
            <label>
                LOẠI TÀI KHOẢN
                <select v-model="loginTypeFilter" aria-label="Lọc theo loại tài khoản">
                    <option value="all">Tất cả phương thức</option>
                    <option value="account">Email &amp; Mật khẩu</option>
                    <option value="google">Tài khoản Google</option>
                </select>
            </label>
            <button
                v-if="dateFrom || dateTo || loginTypeFilter !== 'all'"
                class="btn"
                type="button"
                @click="clearFilters"
            >Xóa lọc ngày</button>
            <em>{{ filteredCustomers.length }} / {{ customers.length }} khách hàng</em>
        </div>

        <div v-if="filteredCustomers.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Loại tài khoản</th>
                        <th>Hoạt động</th>
                        <th>Ngày đăng ký</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="customer in paginatedCustomers" :key="customer.id">
                        <td>
                            <div class="item">
                                <div class="request-detail-avatar">{{ initials(customer.name) }}</div>
                                <div>
                                    <b>{{ customer.name }}</b>
                                    <small>✉ {{ customer.email }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <b>{{ customer.phone || 'Chưa cập nhật' }}</b>
                        </td>
                        <td>
                            <span class="badge">
                                {{ customer.login_type === 'google' ? 'Google OAuth' : 'Email & Mật khẩu' }}
                            </span>
                        </td>
                        <td>
                            <div class="item">
                                <div>
                                    <b>{{ customer.contacts_count }} yêu cầu tư vấn</b>
                                    <small>{{ customer.wishlists_count }} yêu thích · {{ customer.product_reviews_count }} đánh giá</small>
                                </div>
                            </div>
                        </td>
                        <td class="request-date">{{ formatDate(customer.created_at) }}</td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết khách hàng ${customer.name}`"
                                    title="Xem chi tiết"
                                    @click="openDetail(customer)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                <button class="mini" type="button" @click="openEditModal(customer)">Sửa</button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingCustomerId === customer.id"
                                    @click="deleteCustomer(customer)"
                                >{{ deletingCustomerId === customer.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{
                customers.length
                    ? 'Không tìm thấy khách hàng phù hợp với điều kiện lọc.'
                    : 'Chưa có khách hàng nào đăng ký trên hệ thống.'
            }}
        </div>

        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredCustomers.length"
            @page-change="goToPage"
        />

        <!-- Modal xem chi tiết khách hàng -->
        <div v-if="selectedCustomer" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="customerDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">CUSTOMER PROFILE / DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">{{ initials(selectedCustomer.name) }}</span>
                        <div>
                            <h2 id="customerDetailTitle">{{ selectedCustomer.name }}</h2>
                            <p>Mã khách hàng: #{{ selectedCustomer.id }} · {{ selectedCustomer.email }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Họ và tên</small>
                        <div>{{ selectedCustomer.name }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Số điện thoại</small>
                        <div>{{ selectedCustomer.phone || 'Chưa cập nhật' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Địa chỉ Email</small>
                        <div>{{ selectedCustomer.email }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Phương thức đăng nhập</small>
                        <div>{{ selectedCustomer.login_type === 'google' ? 'Tài khoản Google' : 'Email & Mật khẩu' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Ngày đăng ký</small>
                        <div>{{ formatDate(selectedCustomer.created_at) }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Thống kê tương tác</small>
                        <div>
                            {{ selectedCustomer.contacts_count }} yêu cầu ·
                            {{ selectedCustomer.wishlists_count }} yêu thích ·
                            {{ selectedCustomer.product_reviews_count }} đánh giá
                        </div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Lịch sử yêu cầu tư vấn / báo giá gần đây</small>
                        <div v-if="selectedCustomer.recent_contacts.length" class="tablewrap" style="margin-top: 8px">
                            <table class="table" style="min-width: 480px">
                                <thead>
                                    <tr>
                                        <th>Dự án / Hạng mục</th>
                                        <th>Nội dung</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày gửi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="contact in selectedCustomer.recent_contacts" :key="contact.id">
                                        <td>
                                            <b>{{ contact.project_name || contact.category_id || 'Yêu cầu báo giá' }}</b>
                                        </td>
                                        <td>{{ excerpt(contact.message || contact.product || '') }}</td>
                                        <td>{{ statusLabel(contact.status) }}</td>
                                        <td class="request-date">{{ formatDate(contact.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else>Khách hàng chưa gửi yêu cầu tư vấn nào.</div>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button class="btn" type="button" @click="openEditFromDetail(selectedCustomer)">Chỉnh sửa</button>
                    <button class="btn primary" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

        <!-- Modal thêm / sửa khách hàng -->
        <div v-if="isFormModalOpen" class="modal open" role="presentation" @click.self="closeFormModal">
            <section
                class="dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="customerFormTitle"
            >
                <button class="close" type="button" aria-label="Đóng biểu mẫu" @click="closeFormModal">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">{{ editingCustomer ? 'UPDATE CUSTOMER' : 'NEW CUSTOMER' }}</div>
                    <h2 id="customerFormTitle">{{ editingCustomer ? 'Cập nhật khách hàng' : 'Thêm khách hàng mới' }}</h2>
                    <p>{{ editingCustomer ? 'Chỉnh sửa thông tin liên hệ hoặc đặt lại mật khẩu cho khách hàng.' : 'Tạo tài khoản khách hàng mới trên hệ thống HVAC Mart.' }}</p>
                </div>
                <form class="form" @submit.prevent="submitCustomerForm">
                    <div class="formgrid">
                        <div class="field">
                            <label for="customerName">HỌ VÀ TÊN *</label>
                            <input
                                id="customerName"
                                v-model="customerForm.name"
                                type="text"
                                maxlength="255"
                                placeholder="Nguyễn Văn A"
                                required
                            >
                            <small v-if="customerForm.errors.name" class="auth-error">{{ customerForm.errors.name }}</small>
                        </div>

                        <div class="field">
                            <label for="customerPhone">SỐ ĐIỆN THOẠI</label>
                            <input
                                id="customerPhone"
                                v-model="customerForm.phone"
                                type="tel"
                                maxlength="30"
                                placeholder="09xx xxx xxx"
                            >
                            <small v-if="customerForm.errors.phone" class="auth-error">{{ customerForm.errors.phone }}</small>
                        </div>

                        <div class="field full">
                            <label for="customerEmail">ĐỊA CHỈ EMAIL *</label>
                            <input
                                id="customerEmail"
                                v-model="customerForm.email"
                                type="email"
                                maxlength="255"
                                placeholder="khachhang@congty.vn"
                                required
                            >
                            <small v-if="customerForm.errors.email" class="auth-error">{{ customerForm.errors.email }}</small>
                        </div>

                        <div class="field full">
                            <label for="customerPassword">
                                {{ editingCustomer ? 'MẬT KHẨU MỚI (ĐỂ TRỐNG NẾU KHÔNG ĐỔI)' : 'MẬT KHẨU *' }}
                            </label>
                            <input
                                id="customerPassword"
                                v-model="customerForm.password"
                                type="password"
                                minlength="8"
                                maxlength="255"
                                :placeholder="editingCustomer ? 'Nhập tối thiểu 8 ký tự nếu muốn đổi mật khẩu' : 'Tối thiểu 8 ký tự'"
                                :required="!editingCustomer"
                            >
                            <small v-if="customerForm.errors.password" class="auth-error">{{ customerForm.errors.password }}</small>
                        </div>
                    </div>

                    <div class="formfoot">
                        <button class="btn" type="button" @click="closeFormModal">Hủy</button>
                        <button class="btn primary" type="submit" :disabled="customerForm.processing">
                            {{ customerForm.processing ? 'Đang lưu...' : (editingCustomer ? 'Lưu cập nhật' : 'Tạo khách hàng') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router, useForm } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import AdminDatePicker from '../../components/AdminDatePicker.Vue';
    import AdminPagination from '../../components/AdminPagination.Vue';
    import { useAdminSearchParam, useClientPagination } from '../../composables/useClientPagination';

    type CustomerContactSummary = {
        id: number;
        project_name: string | null;
        category_id: string | null;
        product: string | null;
        message: string | null;
        status: string;
        created_at: string | null;
    };

    type CustomerRecord = {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        avatar: string | null;
        login_type: 'google' | 'account';
        email_verified_at: string | null;
        created_at: string | null;
        contacts_count: number;
        wishlists_count: number;
        product_reviews_count: number;
        recent_contacts: CustomerContactSummary[];
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        customers: CustomerRecord[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = useAdminSearchParam();
    const loginTypeFilter = ref<'all' | 'account' | 'google'>('all');
    const dateFrom = ref('');
    const dateTo = ref('');
    const selectedCustomer = ref<CustomerRecord | null>(null);
    const isFormModalOpen = ref(false);
    const editingCustomer = ref<CustomerRecord | null>(null);
    const deletingCustomerId = ref<number | null>(null);

    const customerForm = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
    });

    const filteredCustomers = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.customers.filter((customer) => {
            const searchableText = [
                customer.name,
                customer.email,
                customer.phone,
            ].join(' ').toLocaleLowerCase();
            const createdDate = dateKey(customer.created_at);

            return (
                (!query || searchableText.includes(query))
                && (loginTypeFilter.value === 'all' || customer.login_type === loginTypeFilter.value)
                && (!dateFrom.value || createdDate >= dateFrom.value)
                && (!dateTo.value || createdDate <= dateTo.value)
            );
        });
    });

    const { currentPage, goToPage, paginatedItems: paginatedCustomers, resetPage } = useClientPagination(filteredCustomers);

    watch([search, loginTypeFilter, dateFrom, dateTo], resetPage);

    function initials(name: string): string {
        const words = name.trim().split(/\s+/).filter(Boolean);

        return words.length
            ? words.slice(-2).map((word) => word[0]).join('').toLocaleUpperCase()
            : 'KH';
    }

    function dateKey(value: string | null): string {
        if (!value) {
            return '';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function formatDate(value: string | null): string {
        if (!value) {
            return '—';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function statusLabel(status: string): string {
        if (status === 'processing') {
            return 'Đang xử lý';
        }
        if (status === 'resolved') {
            return 'Đã xử lý';
        }

        return 'Mới';
    }

    function excerpt(text: string): string {
        return text.length > 80 ? `${text.slice(0, 80)}…` : text || '—';
    }

    function clearFilters(): void {
        loginTypeFilter.value = 'all';
        dateFrom.value = '';
        dateTo.value = '';
    }

    function openDetail(customer: CustomerRecord): void {
        selectedCustomer.value = customer;
    }

    function closeDetail(): void {
        selectedCustomer.value = null;
    }

    function openCreateModal(): void {
        editingCustomer.value = null;
        customerForm.reset();
        customerForm.clearErrors();
        isFormModalOpen.value = true;
    }

    function openEditModal(customer: CustomerRecord): void {
        editingCustomer.value = customer;
        customerForm.name = customer.name;
        customerForm.email = customer.email;
        customerForm.phone = customer.phone ?? '';
        customerForm.password = '';
        customerForm.clearErrors();
        isFormModalOpen.value = true;
    }

    function openEditFromDetail(customer: CustomerRecord): void {
        closeDetail();
        openEditModal(customer);
    }

    function closeFormModal(): void {
        isFormModalOpen.value = false;
        editingCustomer.value = null;
    }

    function submitCustomerForm(): void {
        if (editingCustomer.value) {
            customerForm.put(`/admin/customers/${editingCustomer.value.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    closeFormModal();
                },
            });

            return;
        }

        customerForm.post('/admin/customers', {
            preserveScroll: true,
            onSuccess: () => {
                closeFormModal();
            },
        });
    }

    function deleteCustomer(customer: CustomerRecord): void {
        if (
            deletingCustomerId.value !== null
            || !window.confirm(`Bạn có chắc muốn xóa tài khoản khách hàng "${customer.name}" (${customer.email}) không?`)
        ) {
            return;
        }

        deletingCustomerId.value = customer.id;
        router.delete(`/admin/customers/${customer.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedCustomer.value?.id === customer.id) {
                    closeDetail();
                }
            },
            onFinish: () => {
                deletingCustomerId.value = null;
            },
        });
    }

    function onEscape(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            closeDetail();
            closeFormModal();
        }
    }

    watch([selectedCustomer, isFormModalOpen], ([detail, formOpen]) => {
        document.body.style.overflow = detail || formOpen ? 'hidden' : '';
    });

    onMounted(() => {
        window.addEventListener('keydown', onEscape);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onEscape);
        document.body.style.overflow = '';
    });
</script>
