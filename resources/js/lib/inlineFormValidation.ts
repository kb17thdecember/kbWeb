import { router } from '@inertiajs/vue3';

type FormControlElement = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const PHONE_REGEX = /^[0-9+\s().\-]{8,20}$/;

function extractFieldLabel(control: FormControlElement): string {
    const fieldContainer = control.closest('.field, .kb-profile-field, .kb-auth-field, label');

    if (fieldContainer) {
        const labelEl =
            fieldContainer.tagName === 'LABEL'
                ? fieldContainer
                : fieldContainer.querySelector('label:not(.image-upload):not(.check)');

        if (labelEl) {
            const clone = labelEl.cloneNode(true) as HTMLElement;
            clone
                .querySelectorAll('input, select, textarea, small, span.field-error, .product-picker-badge')
                .forEach((el) => el.remove());
            const rawText = (clone.textContent ?? '')
                .replace(/\*/g, '')
                .replace(/\(không bắt buộc\)/gi, '')
                .replace(/\s+/g, ' ')
                .trim();

            if (rawText) {
                return rawText.toLowerCase();
            }
        }
    }

    if (control.id) {
        const explicitLabel = document.querySelector(`label[for="${CSS.escape(control.id)}"]`);
        if (explicitLabel?.textContent) {
            return explicitLabel.textContent.replace(/\*/g, '').trim().toLowerCase();
        }
    }

    const placeholder = control.getAttribute('placeholder')?.trim();
    if (placeholder) {
        return placeholder.replace(/\.\.\.$/, '').toLowerCase();
    }

    return 'trường này';
}

function getAnchorElement(control: FormControlElement): HTMLElement {
    const wrapper = control.closest(
        '.auth-password-wrap, .kb-password-field, .kb-input-wrap, .image-upload',
    ) as HTMLElement | null;

    return wrapper ?? control;
}

function setControlError(control: FormControlElement, message: string): void {
    const anchor = getAnchorElement(control);
    control.classList.add('is-invalid');
    anchor.classList.add('is-invalid');
    control.setAttribute('aria-invalid', 'true');

    const container =
        control.closest('.field, .kb-profile-field, .kb-auth-field') ??
        anchor.parentElement ??
        control.parentElement;

    if (!container) {
        return;
    }

    const existingVueError = Array.from(container.querySelectorAll('.field-error')).find(
        (el) => !el.hasAttribute('data-inline-error') && (el.textContent ?? '').trim() !== '',
    );

    if (existingVueError) {
        return;
    }

    let errorEl = container.querySelector(
        ':scope > small.field-error[data-inline-error="true"], :scope > span.field-error[data-inline-error="true"]',
    ) as HTMLElement | null;

    if (!errorEl) {
        errorEl = document.createElement('small');
        errorEl.className = 'field-error';
        errorEl.setAttribute('data-inline-error', 'true');
        errorEl.setAttribute('role', 'alert');
        anchor.insertAdjacentElement('afterend', errorEl);
    }

    errorEl.textContent = message;
}

export function clearControlError(control: FormControlElement): void {
    const anchor = getAnchorElement(control);
    control.classList.remove('is-invalid');
    anchor.classList.remove('is-invalid');
    control.removeAttribute('aria-invalid');

    const container =
        control.closest('.field, .kb-profile-field, .kb-auth-field') ??
        anchor.parentElement ??
        control.parentElement;

    if (!container) {
        return;
    }

    container
        .querySelectorAll('.field-error[data-inline-error="true"]')
        .forEach((el) => el.remove());
}

export function clearFormErrors(form: HTMLFormElement): void {
    form.querySelectorAll<HTMLElement>('.is-invalid').forEach((el) => {
        el.classList.remove('is-invalid');
        el.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('.field-error[data-inline-error="true"]').forEach((el) => el.remove());
}

export function validateControl(control: FormControlElement): boolean {
    if (control.disabled || control.type === 'hidden' || control.type === 'submit' || control.type === 'button') {
        clearControlError(control);
        return true;
    }

    if (control instanceof HTMLInputElement && control.type === 'file') {
        clearControlError(control);
        return true;
    }

    const rawValue = control.value ?? '';
    const trimmedValue = rawValue.trim();
    const isRequired = control.required || control.hasAttribute('required');
    const label = extractFieldLabel(control);

    if (isRequired && trimmedValue === '') {
        if (control instanceof HTMLSelectElement) {
            setControlError(control, `Vui lòng chọn ${label}.`);
        } else {
            setControlError(control, `Vui lòng nhập ${label}.`);
        }
        return false;
    }

    if (trimmedValue !== '') {
        const isEmailField =
            (control instanceof HTMLInputElement && control.type === 'email') ||
            control.name === 'email' ||
            control.name === 'contact_email';

        if (isEmailField && !EMAIL_REGEX.test(trimmedValue)) {
            setControlError(control, 'Địa chỉ email không đúng định dạng (ví dụ: ten@congty.vn).');
            return false;
        }

        const isPhoneField =
            (control instanceof HTMLInputElement &&
                (control.type === 'tel' || control.inputMode === 'tel')) ||
            control.name === 'phone' ||
            control.name === 'hotline' ||
            control.name === 'zalo_phone';

        if (isPhoneField) {
            const digitsOnly = trimmedValue.replace(/\D/g, '');
            if (!PHONE_REGEX.test(trimmedValue) || digitsOnly.length < 8 || digitsOnly.length > 15) {
                setControlError(control, 'Số điện thoại không hợp lệ (gồm 8–15 chữ số).');
                return false;
            }
        }

        if (control instanceof HTMLInputElement && control.type === 'number') {
            const numericVal = Number(trimmedValue);
            const minAttr = control.getAttribute('min');
            const maxAttr = control.getAttribute('max');

            if (Number.isNaN(numericVal)) {
                setControlError(control, `Giá trị ${label} phải là số hợp lệ.`);
                return false;
            }

            if (minAttr !== null && minAttr !== '' && numericVal < Number(minAttr)) {
                setControlError(control, `Giá trị ${label} không được nhỏ hơn ${minAttr}.`);
                return false;
            }

            if (maxAttr !== null && maxAttr !== '' && numericVal > Number(maxAttr)) {
                setControlError(control, `Giá trị ${label} vượt quá giới hạn cho phép.`);
                return false;
            }
        }

        const minLength = control.getAttribute('minlength');
        if (minLength && trimmedValue.length < Number(minLength)) {
            setControlError(control, `Vui lòng nhập ít nhất ${minLength} ký tự cho ${label}.`);
            return false;
        }

        if (control.validity && !control.validity.valid) {
            if (control.validity.patternMismatch) {
                setControlError(
                    control,
                    control.getAttribute('title') || `Định dạng ${label} không hợp lệ.`,
                );
                return false;
            }
            if (control.validity.typeMismatch) {
                setControlError(control, `Định dạng ${label} không hợp lệ.`);
                return false;
            }
        }
    }

    clearControlError(control);
    return true;
}

function validateSpecialModalFields(form: HTMLFormElement): HTMLElement | null {
    let firstInvalidEl: HTMLElement | null = null;

    // 1. Catalogue Modal: PDF file required when creating a new catalogue
    const catalogueFileInput = form.querySelector<HTMLInputElement>('#catalogueFile');
    if (catalogueFileInput) {
        const uploadLabel = catalogueFileInput.closest<HTMLElement>('.image-upload');
        const fieldWrap = catalogueFileInput.closest<HTMLElement>('.field');
        const hasExistingPdfHelp = Boolean(fieldWrap?.querySelector('.image-help'));
        const hasSelectedPdf = Boolean(
            catalogueFileInput.files?.length ||
                uploadLabel?.querySelector('small')?.textContent?.includes('MB') ||
                uploadLabel?.querySelector('small')?.textContent?.includes('KB'),
        );

        fieldWrap?.querySelectorAll('.field-error[data-inline-error="true"]').forEach((el) => el.remove());
        uploadLabel?.classList.remove('is-invalid');

        if (!hasExistingPdfHelp && !hasSelectedPdf && uploadLabel && fieldWrap) {
            uploadLabel.classList.add('is-invalid');
            const err = document.createElement('small');
            err.className = 'field-error';
            err.setAttribute('data-inline-error', 'true');
            err.textContent = 'Vui lòng chọn tệp PDF cho catalogue.';
            uploadLabel.insertAdjacentElement('afterend', err);
            firstInvalidEl ??= catalogueFileInput;
        }
    }

    // 2. Article Modal: RichTextEditor content required
    const richEditor = form.querySelector<HTMLElement>('.rich-editor');
    if (richEditor) {
        const editorBody = richEditor.querySelector<HTMLElement>('.editor-area, [contenteditable="true"]');
        const fieldWrap = richEditor.closest<HTMLElement>('.field');
        fieldWrap?.querySelectorAll('.field-error[data-inline-error="true"]').forEach((el) => el.remove());
        richEditor.classList.remove('is-invalid');

        const textContent = (editorBody?.textContent ?? '').trim();
        const hasImages = Boolean(editorBody?.querySelector('img'));

        if (editorBody && textContent === '' && !hasImages && fieldWrap) {
            richEditor.classList.add('is-invalid');
            const err = document.createElement('small');
            err.className = 'field-error';
            err.setAttribute('data-inline-error', 'true');
            err.textContent = 'Vui lòng nhập nội dung bài viết.';
            richEditor.insertAdjacentElement('afterend', err);
            firstInvalidEl ??= editorBody;
        }
    }

    // 3. Featured Project Modal: require at least 1 image
    const projectNameInput = form.querySelector<HTMLInputElement>('#featuredProjectName');
    if (projectNameInput) {
        const imageUpload = form.querySelector<HTMLElement>('.image-upload');
        const imageField = imageUpload?.closest<HTMLElement>('.field');
        const hasPreviewImages = Boolean(form.querySelector('.project-images-preview .project-image-preview'));

        imageField?.querySelectorAll('.field-error[data-inline-error="true"]').forEach((el) => el.remove());
        imageUpload?.classList.remove('is-invalid');

        if (imageUpload && imageField && !hasPreviewImages) {
            imageUpload.classList.add('is-invalid');
            const err = document.createElement('small');
            err.className = 'field-error';
            err.setAttribute('data-inline-error', 'true');
            err.textContent = 'Vui lòng chọn ít nhất 1 ảnh cho dự án.';
            imageUpload.insertAdjacentElement('afterend', err);
            firstInvalidEl ??= imageUpload;
        }
    }

    return firstInvalidEl;
}

function attachNoValidateToForms(root: ParentNode = document): void {
    root.querySelectorAll<HTMLFormElement>('form').forEach((form) => {
        if (form.getAttribute('role') !== 'search') {
            form.noValidate = true;
        }
    });
}

export function setupInlineFormValidation(): void {
    if (typeof window === 'undefined' || (window as unknown as Record<string, boolean>).__kbInlineValidationReady) {
        return;
    }
    (window as unknown as Record<string, boolean>).__kbInlineValidationReady = true;

    attachNoValidateToForms();

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'attributes' && mutation.target instanceof HTMLElement) {
                if (mutation.target.classList.contains('modal') && !mutation.target.classList.contains('open')) {
                    mutation.target.querySelectorAll<HTMLFormElement>('form').forEach((form) => {
                        clearFormErrors(form);
                        form.querySelectorAll<HTMLElement>('[data-touched]').forEach((el) => {
                            delete el.dataset.touched;
                        });
                    });
                }
                continue;
            }

            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    if (node.tagName === 'FORM' && node.getAttribute('role') !== 'search') {
                        (node as HTMLFormElement).noValidate = true;
                    } else {
                        attachNoValidateToForms(node);
                    }
                }
            });
        }
    });

    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class'],
        });
    } else {
        window.addEventListener('DOMContentLoaded', () => {
            attachNoValidateToForms();
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class'],
            });
        });
    }

    document.addEventListener(
        'focusin',
        (event) => {
            const target = event.target;
            if (target instanceof HTMLElement) {
                const form = target.closest('form');
                if (form && form.getAttribute('role') !== 'search') {
                    form.noValidate = true;
                }
            }
        },
        true,
    );

    document.addEventListener(
        'blur',
        (event) => {
            const target = event.target;
            if (
                target instanceof HTMLInputElement ||
                target instanceof HTMLSelectElement ||
                target instanceof HTMLTextAreaElement
            ) {
                const form = target.closest('form');
                if (!form || form.getAttribute('role') === 'search') {
                    return;
                }
                if (target.dataset.touched === 'true' || target.value.trim() !== '') {
                    validateControl(target);
                }
            }
        },
        true,
    );

    const handleRealtimeChange = (event: Event): void => {
        const target = event.target;
        if (
            target instanceof HTMLInputElement ||
            target instanceof HTMLSelectElement ||
            target instanceof HTMLTextAreaElement
        ) {
            target.dataset.touched = 'true';
            if (target.type === 'file') {
                const uploadLabel = target.closest<HTMLElement>('.image-upload');
                const fieldWrap = target.closest<HTMLElement>('.field');
                uploadLabel?.classList.remove('is-invalid');
                fieldWrap?.querySelectorAll('.field-error[data-inline-error="true"]').forEach((el) => el.remove());
                return;
            }
            if (
                target.classList.contains('is-invalid') ||
                target.closest('.field, .kb-profile-field, .kb-auth-field')?.querySelector('.field-error')
            ) {
                validateControl(target);
            }
        } else if (target instanceof HTMLElement && target.closest('.rich-editor')) {
            const richEditor = target.closest<HTMLElement>('.rich-editor');
            const fieldWrap = richEditor?.closest<HTMLElement>('.field');
            if (richEditor && (target.textContent ?? '').trim() !== '') {
                richEditor.classList.remove('is-invalid');
                fieldWrap?.querySelectorAll('.field-error[data-inline-error="true"]').forEach((el) => el.remove());
            }
        }
    };

    document.addEventListener('input', handleRealtimeChange, true);
    document.addEventListener('change', handleRealtimeChange, true);

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.getAttribute('role') === 'search') {
                return;
            }

            form.noValidate = true;
            const controls = Array.from(
                form.querySelectorAll<FormControlElement>('input, select, textarea'),
            );

            let firstInvalidControl: HTMLElement | null = null;

            for (const control of controls) {
                control.dataset.touched = 'true';
                const isValid = validateControl(control);
                if (!isValid && !firstInvalidControl) {
                    firstInvalidControl = control;
                }
            }

            const specialInvalid = validateSpecialModalFields(form);
            if (!firstInvalidControl && specialInvalid) {
                firstInvalidControl = specialInvalid;
            }

            if (firstInvalidControl) {
                event.preventDefault();
                event.stopImmediatePropagation();
                firstInvalidControl.focus({ preventScroll: false });
            }
        },
        true,
    );

    // Map server-side Inertia validation errors to matching form inputs
    router.on('error', (event) => {
        const errors = (event.detail.errors ?? {}) as Record<string, string>;
        const activeForm =
            document.querySelector<HTMLFormElement>('.modal.open form, .modal-wrap.open form') ??
            document.querySelector<HTMLFormElement>('form');

        if (!activeForm) {
            return;
        }

        Object.entries(errors).forEach(([fieldKey, message]) => {
            if (!message) {
                return;
            }

            const control = activeForm.querySelector<FormControlElement>(
                `[name="${CSS.escape(fieldKey)}"]`,
            );

            if (control) {
                setControlError(control, message);
            }
        });
    });
}
