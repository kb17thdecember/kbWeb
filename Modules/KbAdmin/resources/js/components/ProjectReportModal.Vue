<template>
    <div
        class="modal"
        :class="{ open }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="projectReportModalTitle"
        @click.self="closeModal"
    >
        <div class="dialog">
            <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting" @click="closeModal">×</button>
            <div class="dialog-head">
                <div class="eyebrow">ADMIN / PROJECT REPORT</div>
                <h2 id="projectReportModalTitle">{{ projectReport ? 'Sửa báo cáo dự án' : 'Thêm báo cáo dự án' }}</h2>
                <p>Nhập thông tin dự án, tiến độ và đầu mối liên hệ.</p>
            </div>
            <form class="form" @submit.prevent="saveReport">
                <div class="formgrid">
                    <div class="field full">
                        <label for="projectReportName">Tên dự án</label>
                        <input id="projectReportName" v-model="form.project_name" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label for="projectReportCode">Mã dự án</label>
                        <input id="projectReportCode" v-model="form.project_code" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label for="projectReportArea">Khu vực (Tỉnh/Thành phố)</label>
                        <select id="projectReportArea" v-model="form.project_area" required>
                            <option value="" disabled>Chọn tỉnh/thành phố</option>
                            <option
                                v-if="form.project_area && !provinceOptions.includes(form.project_area)"
                                :value="form.project_area"
                            >
                                {{ form.project_area }} (chọn lại theo đơn vị hành chính mới)
                            </option>
                            <option v-for="province in provinceOptions" :key="province" :value="province">
                                {{ province }}
                            </option>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="projectReportLocation">Địa chỉ cụ thể (không bắt buộc)</label>
                        <input
                            id="projectReportLocation"
                            v-model="form.project_location"
                            maxlength="255"
                            placeholder="Ví dụ: Phường Cầu Giấy, đường ..., số ..."
                        >
                    </div>
                    <div class="field">
                        <label for="projectReportDeveloper">Đơn vị phát triển</label>
                        <input id="projectReportDeveloper" v-model="form.developer" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="projectReportConsultant">Tư vấn</label>
                        <input id="projectReportConsultant" v-model="form.me_consultant" maxlength="255">
                    </div>
                    <div class="field full">
                        <label for="projectReportContractor">Nhà thầu M&amp;E</label>
                        <input id="projectReportContractor" v-model="form.me_contractor" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="projectReportProgress">Tiến độ</label>
                        <input id="projectReportProgress" v-model="form.progress" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="projectReportEstimate">Giá trị ước tính</label>
                        <input
                            id="projectReportEstimate"
                            v-model="form.estimate_value"
                            type="number"
                            min="0"
                            step="any"
                        >
                    </div>
                    <div class="field full">
                        <label for="projectReportContact">Contact person</label>
                        <input id="projectReportContact" v-model="form.contact_person" maxlength="255">
                    </div>
                    <div class="field full">
                        <label for="projectReportUpdate">Cập nhật dự án</label>
                        <textarea id="projectReportUpdate" v-model="form.project_update" maxlength="255" required></textarea>
                    </div>
                </div>
                <div class="formfoot">
                    <button type="button" class="btn" :disabled="isSubmitting" @click="closeModal">Hủy</button>
                    <button type="submit" class="btn primary" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Đang lưu...' : projectReport ? 'Cập nhật dự án ↗' : 'Lưu dự án ↗' }}
                    </button>
                </div>
            </form>
        </div>
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </div>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { onBeforeUnmount, reactive, ref, watch } from 'vue';

    type ProjectReport = {
        id: number;
        project_name: string;
        project_code: string;
        project_area: string;
        project_location: string | null;
        developer: string | null;
        me_consultant: string | null;
        me_contractor: string | null;
        progress: string | null;
        estimate_value: string | null;
        contact_person: string | null;
        project_update: string;
    };

    type ProjectReportForm = Omit<ProjectReport, 'id'>;

    const props = defineProps<{
        open: boolean;
        projectReport: ProjectReport | null;
        provinceOptions: string[];
    }>();

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated'];
    }>();

    const emptyForm = (): ProjectReportForm => ({
        project_name: '',
        project_code: '',
        project_area: '',
        project_location: '',
        developer: '',
        me_consultant: '',
        me_contractor: '',
        progress: '',
        estimate_value: '',
        contact_person: '',
        project_update: '',
    });
    const form = reactive<ProjectReportForm>(emptyForm());
    const isSubmitting = ref(false);
    const toastMessage = ref('');
    const isToastVisible = ref(false);
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    watch(
        () => [props.open, props.projectReport] as const,
        ([isOpen]) => {
            if (isOpen) {
                const report = props.projectReport;
                Object.assign(form, emptyForm(), report ? {
                    project_name: report.project_name,
                    project_code: report.project_code,
                    project_area: report.project_area,
                    project_location: report.project_location,
                    developer: report.developer ?? '',
                    me_consultant: report.me_consultant ?? '',
                    me_contractor: report.me_contractor ?? '',
                    progress: report.progress,
                    estimate_value: report.estimate_value ?? '',
                    contact_person: report.contact_person ?? '',
                    project_update: report.project_update,
                } : {});
                toastMessage.value = '';
                isToastVisible.value = false;
            }
        },
    );

    function closeModal(): void {
        if (!isSubmitting.value) {
            emit('update:open', false);
        }
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
        }, 2800);
    }

    function saveReport(): void {
        if (isSubmitting.value) {
            return;
        }

        isSubmitting.value = true;
        const data = { ...form };
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.projectReport ? 'updated' : 'created');
            },
            onError: (errors: Record<string, string>) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError ?? 'Không thể lưu báo cáo. Vui lòng kiểm tra lại thông tin.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        };

        if (props.projectReport) {
            router.put(`/admin/project-reports/${props.projectReport.id}`, data, options);
            return;
        }

        router.post('/admin/project-reports', data, options);
    }

    onBeforeUnmount(() => {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>
