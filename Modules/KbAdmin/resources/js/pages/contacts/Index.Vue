<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Yêu cầu tư vấn của khách hàng</h2>
                <p>Lọc theo thông tin liên hệ hoặc khoảng ngày nhận yêu cầu.</p>
            </div>
            <div class="actions">
                <input
                    v-model="search"
                    class="search"
                    type="search"
                    placeholder="Tìm khách hàng/nội dung..."
                    aria-label="Tìm khách hàng hoặc nội dung yêu cầu"
                >
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <span>TỪ NGÀY</span>
                <AdminDatePicker v-model="dateFrom" placeholder="Chọn từ ngày..." aria-label="Lọc yêu cầu từ ngày" />
            </div>
            <div class="filter-group">
                <span>ĐẾN NGÀY</span>
                <AdminDatePicker v-model="dateTo" placeholder="Chọn đến ngày..." aria-label="Lọc yêu cầu đến ngày" />
            </div>
            <button
                v-if="dateFrom || dateTo"
                class="btn"
                type="button"
                @click="clearDates"
            >Xóa lọc ngày</button>
            <em>{{ filteredContacts.length }} / {{ contacts.length }} yêu cầu</em>
        </div>

        <div v-if="filteredContacts.length" class="tablewrap consultation-tablewrap">
            <table class="table consultation-table">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Hạng mục quan tâm</th>
                        <th>Dự án cần báo giá</th>
                        <th>Mô tả nhu cầu</th>
                        <th>Ngày nhận</th>
                        <th>Trạng thái</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="contact in paginatedContacts" :key="contact.id">
                        <td>
                            <div class="item">
                                <div class="request-detail-avatar">{{ initials(contact.name) }}</div>
                                <div>
                                    <b>{{ contact.name || 'Chưa có tên' }}</b>
                                    <small>☎ {{ contact.phone || 'Chưa có SĐT' }}<br>✉ {{ contact.email || 'Chưa có email' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <b>{{ contact.category_id || 'Chưa phân loại' }}</b>
                        </td>
                        <td>
                            <div class="request-summary">{{ contact.project_name || 'Chưa cung cấp' }}</div>
                        </td>
                        <td>
                            <div class="request-summary">
                                {{ excerpt(contact.message) }}
                            </div>
                        </td>
                        <td class="request-date">{{ formatDate(contact.created_at) }}</td>
                        <td>
                            <select
                                class="search request-status"
                                :class="`status-${contactStatus(contact.status)}`"
                                :value="contactStatus(contact.status)"
                                :disabled="updatingStatusId === contact.id"
                                :aria-label="`Trạng thái yêu cầu của ${contact.name}`"
                                @change="updateContactStatus(contact, $event)"
                            >
                                <option value="pending">Mới</option>
                                <option value="processing">Đang xử lý</option>
                                <option value="resolved">Đã xử lý</option>
                            </select>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết yêu cầu của ${contact.name}`"
                                    title="Xem chi tiết & Phản hồi báo giá"
                                    @click="openDetail(contact)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                <button
                                    class="mini primary"
                                    type="button"
                                    :title="`Tạo báo giá PDF cho ${contact.name}`"
                                    @click="goToQuotationBuilder(contact)"
                                >
                                    {{ contact.quotation_url ? 'Đã BG' : 'Báo giá' }}
                                </button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingContactId === contact.id"
                                    @click="deleteContact(contact)"
                                >{{ deletingContactId === contact.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{
                contacts.length
                    ? 'Không có yêu cầu phù hợp với điều kiện lọc.'
                    : 'Chưa có yêu cầu tư vấn nào được gửi từ website.'
            }}
        </div>
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredContacts.length"
            @page-change="goToPage"
        />

        <div v-if="selectedContact" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="requestDetailTitle"
                aria-describedby="requestDetailSubtitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
                <div class="dialog-head">
                    <div class="eyebrow">CUSTOMER / REQUEST DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">{{ initials(selectedContact.name) }}</span>
                        <div>
                            <h2 id="requestDetailTitle">{{ selectedContact.name || 'Chi tiết yêu cầu' }}</h2>
                            <p id="requestDetailSubtitle">Mã yêu cầu: {{ selectedContact.id }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Tên khách hàng</small>
                        <div>{{ selectedContact.name || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Số điện thoại</small>
                        <div>{{ selectedContact.phone || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Email</small>
                        <div>{{ selectedContact.email || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Công ty / Đơn vị</small>
                        <div>—</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Hạng mục quan tâm</small>
                        <div>{{ selectedContact.category_id || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Dự án cần báo giá</small>
                        <div>{{ selectedContact.project_name || 'Chưa cung cấp' }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Sản phẩm yêu cầu báo giá</small>
                        <div style="white-space: pre-line">{{ selectedContact.product || 'Chưa cung cấp' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Loại yêu cầu</small>
                        <div>Yêu cầu tư vấn</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Ngày gửi yêu cầu</small>
                        <div>{{ formatDate(selectedContact.created_at) }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Trạng thái</small>
                        <div>{{ statusLabel(selectedContact.status) }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Mô tả nhu cầu</small>
                        <div>{{ selectedContact.message || 'Khách hàng chưa cung cấp mô tả.' }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Hồ sơ đính kèm (Bảng khối lượng BOQ / Bản vẽ kỹ thuật)</small>
                        <div v-if="selectedContact.attachment_url" class="request-detail-attachment">
                            <span class="request-detail-attachment-name">
                                📎 {{ selectedContact.attachment_name || 'Tài liệu đính kèm' }}
                            </span>
                            <div class="request-detail-attachment-actions">
                                <a
                                    class="btn"
                                    :href="selectedContact.attachment_url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Mở xem tệp
                                </a>
                                <a
                                    class="btn primary"
                                    :href="selectedContact.attachment_url"
                                    :download="selectedContact.attachment_name || true"
                                >
                                    Tải xuống
                                </a>
                            </div>
                        </div>
                        <div v-else>Khách hàng không đính kèm tệp.</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Phản hồi Báo giá chính thức cho khách hàng (Hiển thị trong Tài khoản khách hàng)</small>
                        <div v-if="selectedContact.quotation_url" class="request-detail-attachment" style="margin-bottom: 10px;">
                            <span class="request-detail-attachment-name">
                                📄 {{ selectedContact.quotation_name || 'Bảng báo giá đã gửi' }}
                                <em v-if="selectedContact.quoted_at" style="font-size: 12px; color: #68707d; margin-left: 6px;">
                                    ({{ formatDate(selectedContact.quoted_at) }})
                                </em>
                            </span>
                            <div class="request-detail-attachment-actions">
                                <a
                                    class="btn"
                                    :href="selectedContact.quotation_url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Mở xem báo giá
                                </a>
                                <a
                                    class="btn primary"
                                    :href="selectedContact.quotation_url"
                                    :download="selectedContact.quotation_name || true"
                                >
                                    Tải xuống
                                </a>
                            </div>
                        </div>
                        <form class="quotation-response-form" @submit.prevent="submitQuotationResponse">
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 6px;">
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #4b5563;">
                                        Đính kèm file Báo giá PDF / Excel (.pdf, .xlsx, .docx)
                                    </label>
                                    <input
                                        ref="quotationFileInputRef"
                                        type="file"
                                        accept=".pdf,.xlsx,.xls,.doc,.docx,.zip"
                                        class="search"
                                        style="width: 100%; padding: 8px;"
                                        @change="onQuotationFileChange"
                                    >
                                </div>
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #4b5563;">
                                        Ghi chú phản hồi cho khách hàng
                                    </label>
                                    <textarea
                                        v-model="adminNoteInput"
                                        rows="2"
                                        class="search"
                                        style="width: 100%; resize: vertical; padding: 8px 10px;"
                                        placeholder="Nhập ghi chú chiết khấu, thời gian giao hàng hoặc hướng dẫn cho khách..."
                                    ></textarea>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                    <button
                                        class="btn primary"
                                        type="submit"
                                        :disabled="isSubmittingQuotation"
                                    >
                                        {{ isSubmittingQuotation ? 'Đang gửi phản hồi...' : 'Lưu & Gửi báo giá cho khách' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button
                        class="btn primary"
                        type="button"
                        @click="goToQuotationBuilder(selectedContact)"
                    >
                        Chuyển sang Tạo Báo Giá PDF
                    </button>
                    <button class="btn" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
            {{ toastMessage }}
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import AdminDatePicker from '../../components/AdminDatePicker.Vue';
    import AdminPagination from '../../components/AdminPagination.Vue';
    import { useAdminSearchParam, useClientPagination } from '../../composables/useClientPagination';

    type Contact = {
        id: number;
        name: string;
        email: string;
        phone: string;
        message: string | null;
        status: string;
        category_id: string | null;
        project_name: string | null;
        product: string | null;
        attachment_name: string | null;
        attachment_url: string | null;
        quotation_name?: string | null;
        quotation_url?: string | null;
        admin_note?: string | null;
        quoted_at?: string | null;
        created_at: string;
    };

    type ContactStatus = 'pending' | 'processing' | 'resolved';

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        contacts: Contact[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = useAdminSearchParam();
    const dateFrom = ref('');
    const dateTo = ref('');
    const selectedContact = ref<Contact | null>(null);
    const quotationFile = ref<File | null>(null);
    const quotationFileInputRef = ref<HTMLInputElement | null>(null);
    const adminNoteInput = ref('');
    const isSubmittingQuotation = ref(false);
    const updatingStatusId = ref<number | null>(null);
    const deletingContactId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredContacts = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.contacts.filter((contact) => {
            const searchableText = [
                contact.name,
                contact.phone,
                contact.email,
                contact.category_id,
                contact.project_name,
                contact.message,
                contact.attachment_name,
                statusLabel(contact.status),
            ].join(' ').toLocaleLowerCase();
            const createdDate = dateKey(contact.created_at);

            return (
                (!query || searchableText.includes(query))
                && (!dateFrom.value || createdDate >= dateFrom.value)
                && (!dateTo.value || createdDate <= dateTo.value)
            );
        });
    });
    const { currentPage, goToPage, paginatedItems: paginatedContacts, resetPage } = useClientPagination(filteredContacts);

    watch([search, dateFrom, dateTo], resetPage);

    function contactStatus(status: string): ContactStatus {
        if (status === 'processing' || status === 'resolved') {
            return status;
        }

        return 'pending';
    }

    function isContactStatus(status: string): status is ContactStatus {
        return status === 'pending' || status === 'processing' || status === 'resolved';
    }

    function statusLabel(status: string): string {
        return {
            pending: 'Mới',
            processing: 'Đang xử lý',
            resolved: 'Đã xử lý',
        }[contactStatus(status)];
    }

    function initials(name?: string | null): string {
        const words = (name ?? '').trim().split(/\s+/).filter(Boolean);

        return words.length
            ? words.slice(-2).map((word) => word[0]).join('').toLocaleUpperCase()
            : 'KH';
    }

    function dateKey(value: string): string {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function formatDate(value: string): string {
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

    function excerpt(message?: string | null): string {
        const text = (message ?? '').trim();

        if (!text) {
            return 'Chưa có mô tả';
        }

        return text.length > 125 ? `${text.slice(0, 125)}…` : text;
    }

    function clearDates(): void {
        dateFrom.value = '';
        dateTo.value = '';
    }

    function showToast(message: string): void {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }

        toastMessage.value = message;
        isToastVisible.value = true;
        toastTimeout = setTimeout(() => {
            isToastVisible.value = false;
            toastTimeout = undefined;
        }, 2400);
    }

    function openDetail(contact: Contact): void {
        selectedContact.value = contact;
        adminNoteInput.value = contact.admin_note ?? '';
        quotationFile.value = null;
        if (quotationFileInputRef.value) {
            quotationFileInputRef.value.value = '';
        }
    }

    function closeDetail(): void {
        selectedContact.value = null;
        quotationFile.value = null;
    }

    function goToQuotationBuilder(contact: Contact): void {
        closeDetail();
        router.visit(`/admin/quotations?contact_id=${contact.id}`);
    }

    function onQuotationFileChange(event: Event): void {
        const input = event.target as HTMLInputElement | null;
        quotationFile.value = input?.files?.[0] ?? null;
    }

    function submitQuotationResponse(): void {
        if (!selectedContact.value || isSubmittingQuotation.value) {
            return;
        }

        const contactId = selectedContact.value.id;
        isSubmittingQuotation.value = true;

        router.post(
            `/admin/consultations/${contactId}/quotation`,
            {
                quotation_file: quotationFile.value,
                admin_note: adminNoteInput.value,
                status: 'resolved',
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    const updated = props.contacts.find((item) => item.id === contactId);
                    if (updated) {
                        selectedContact.value = updated;
                    }
                    quotationFile.value = null;
                    if (quotationFileInputRef.value) {
                        quotationFileInputRef.value.value = '';
                    }
                    showToast('Đã lưu và gửi phản hồi báo giá cho khách hàng.');
                },
                onError: (errors) => {
                    showToast(Object.values(errors)[0] || 'Không thể lưu phản hồi báo giá.');
                },
                onFinish: () => {
                    isSubmittingQuotation.value = false;
                },
            },
        );
    }

    watch(
        () => props.contacts,
        (list) => {
            if (selectedContact.value) {
                const fresh = list.find((item) => item.id === selectedContact.value?.id);
                if (fresh) {
                    selectedContact.value = fresh;
                }
            }
        },
    );

    function updateContactStatus(contact: Contact, event: Event): void {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement)) {
            return;
        }
        if (!isContactStatus(target.value)) {
            showToast('Trạng thái yêu cầu không hợp lệ.');
            return;
        }

        updatingStatusId.value = contact.id;
        router.patch(`/admin/consultations/${contact.id}/status`, {
            status: target.value,
        }, {
            preserveScroll: true,
            onSuccess: () => showToast('Đã cập nhật trạng thái yêu cầu.'),
            onError: () => showToast('Không thể cập nhật trạng thái. Vui lòng thử lại.'),
            onFinish: () => {
                updatingStatusId.value = null;
            },
        });
    }

    function deleteContact(contact: Contact): void {
        if (
            deletingContactId.value !== null
            || !window.confirm(`Bạn có chắc muốn xóa yêu cầu tư vấn của "${contact.name}" không?`)
        ) {
            return;
        }

        deletingContactId.value = contact.id;
        router.delete(`/admin/consultations/${contact.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedContact.value?.id === contact.id) {
                    closeDetail();
                }
                showToast('Đã xóa yêu cầu tư vấn.');
            },
            onError: () => showToast('Không thể xóa yêu cầu. Vui lòng thử lại.'),
            onFinish: () => {
                deletingContactId.value = null;
            },
        });
    }

    function onEscape(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            closeDetail();
        }
    }

    watch(selectedContact, (contact) => {
        document.body.style.overflow = contact ? 'hidden' : '';
    });

    onMounted(() => {
        window.addEventListener('keydown', onEscape);
        const contactIdParam = Number(new URLSearchParams(window.location.search).get('contact_id'));
        if (contactIdParam > 0) {
            const matched = props.contacts.find((item) => item.id === contactIdParam);
            if (matched) {
                openDetail(matched);
            }
        }
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onEscape);
        document.body.style.overflow = '';
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>
