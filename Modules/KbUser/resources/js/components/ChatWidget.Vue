<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

type AuthUser = {
    name?: string;
    email?: string;
    phone?: string | null;
};

type ChatMessage = {
    id: number;
    sender: 'visitor' | 'admin';
    body: string;
    created_at: string | null;
};

const POLL_OPEN_MS = 2000;
const POLL_CLOSED_MS = 20000;
const MAX_LENGTH = 2000;

const page = usePage<{ auth?: { user?: AuthUser | null } }>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);

const isOpen = ref(false);
const messages = ref<ChatMessage[]>([]);
const draft = ref('');
const name = ref('');
const email = ref('');
const phone = ref('');
const errorMessage = ref('');
const isSending = ref(false);
const unreadCount = ref(0);
const listElement = ref<HTMLElement | null>(null);
let lastId = 0;

const SEEN_KEY = 'kb_chat_seen_id';
let seenId = 0;

try {
    seenId = Number(localStorage.getItem(SEEN_KEY)) || 0;
} catch {
    // Trình duyệt chặn localStorage thì coi như chưa đọc gì.
}

const markSeen = (): void => {
    seenId = Math.max(seenId, lastId);

    try {
        localStorage.setItem(SEEN_KEY, String(seenId));
    } catch {
        // Bỏ qua nếu không lưu được.
    }
};
let pollTimer: ReturnType<typeof setTimeout> | undefined;
let isPolling = false;
let tempId = 0;

const IDENTITY_KEY = 'kb_chat_identity';

try {
    const saved = JSON.parse(localStorage.getItem(IDENTITY_KEY) ?? '{}') as Record<string, string>;
    name.value = authUser.value?.name ?? saved.name ?? '';
    email.value = authUser.value?.email ?? saved.email ?? '';
    phone.value = authUser.value?.phone ?? saved.phone ?? '';
} catch {
    name.value = authUser.value?.name ?? '';
    email.value = authUser.value?.email ?? '';
    phone.value = authUser.value?.phone ?? '';
}

watch(authUser, (user) => {
    if (user) {
        if (!name.value && user.name) {
            name.value = user.name;
        }
        if (!email.value && user.email) {
            email.value = user.email;
        }
        if (!phone.value && user.phone) {
            phone.value = user.phone;
        }
    }
});

const saveIdentity = (): void => {
    try {
        localStorage.setItem(
            IDENTITY_KEY,
            JSON.stringify({ name: name.value, email: email.value, phone: phone.value }),
        );
    } catch {
        // Trình duyệt chặn localStorage thì bỏ qua.
    }
};

const hasMessages = computed(() => messages.value.length > 0);
const canSend = computed(() => !isSending.value && draft.value.trim().length > 0);

const csrfToken = (): string =>
    decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');

const formatTime = (value: string | null): string =>
    value
        ? new Date(value).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
        : '';

const scrollToBottom = async (): Promise<void> => {
    await nextTick();
    if (listElement.value) {
        listElement.value.scrollTop = listElement.value.scrollHeight;
    }
};

const appendMessages = (incoming: ChatMessage[]): void => {
    const known = new Set(messages.value.map((message) => message.id));
    const fresh = incoming.filter((message) => message.id > lastId && !known.has(message.id));

    if (!fresh.length) {
        return;
    }

    messages.value.push(...fresh);
    lastId = Math.max(lastId, ...fresh.map((message) => message.id));

    if (isOpen.value) {
        markSeen();
        void scrollToBottom();
    } else {
        unreadCount.value += fresh.filter((message) => message.sender === 'admin' && message.id > seenId).length;
    }
};

const poll = async (): Promise<void> => {
    if (isPolling) {
        return;
    }

    isPolling = true;

    try {
        const response = await fetch(`/chat/messages?after=${lastId}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const data = await response.json() as { messages: ChatMessage[] };
            appendMessages(data.messages);
        }
    } catch {
        // Network errors are retried on the next poll.
    } finally {
        isPolling = false;
    }

    schedulePoll();
};

const schedulePoll = (): void => {
    if (pollTimer) {
        clearTimeout(pollTimer);
    }

    pollTimer = setTimeout(poll, isOpen.value ? POLL_OPEN_MS : POLL_CLOSED_MS);
};

const send = async (): Promise<void> => {
    const body = draft.value.trim();

    if (!body || isSending.value) {
        return;
    }

    isSending.value = true;
    errorMessage.value = '';
    saveIdentity();

    // Hiện tin ngay lập tức, thay bằng bản thật khi server phản hồi.
    const pending: ChatMessage = { id: --tempId, sender: 'visitor', body, created_at: new Date().toISOString() };
    messages.value.push(pending);
    draft.value = '';
    void scrollToBottom();

    const removePending = (): void => {
        messages.value = messages.value.filter((message) => message.id !== pending.id);
    };

    try {
        const response = await fetch('/chat/messages', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                body,
                name: name.value.trim() || null,
                email: email.value.trim() || null,
                phone: phone.value.trim() || null,
            }),
        });

        if (response.status === 422) {
            const data = await response.json() as { errors: Record<string, string[]> };
            errorMessage.value = Object.values(data.errors).flat().join(' ');
            removePending();
            draft.value = body;

            return;
        }

        if (response.status === 429) {
            errorMessage.value = 'Bạn gửi quá nhanh, vui lòng thử lại sau ít phút.';
            removePending();
            draft.value = body;

            return;
        }

        if (!response.ok) {
            throw new Error('send failed');
        }

        const data = await response.json() as { message: ChatMessage };
        removePending();
        appendMessages([data.message]);
    } catch {
        errorMessage.value = 'Không thể gửi tin nhắn. Vui lòng thử lại.';
        removePending();
        draft.value = body;
    } finally {
        isSending.value = false;
    }
};

const onEnter = (event: KeyboardEvent): void => {
    if (!event.shiftKey) {
        event.preventDefault();
        void send();
    }
};

const toggle = (): void => {
    isOpen.value = !isOpen.value;
};

watch(isOpen, (open) => {
    if (open) {
        unreadCount.value = 0;
        markSeen();
        void scrollToBottom();
    }

    schedulePoll();
});

void poll();

onBeforeUnmount(() => {
    if (pollTimer) {
        clearTimeout(pollTimer);
    }
});
</script>

<template>
    <div class="chat-widget">
        <section v-if="isOpen" class="chat-panel" role="dialog" aria-label="Chat với HVAC Mart">
            <header class="chat-header">
                <div>
                    <b>Chat với HVAC Mart</b>
                    <small>Chúng tôi phản hồi trong giờ làm việc</small>
                </div>
                <button class="chat-close" type="button" aria-label="Đóng chat" @click="toggle">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
            </header>

            <div ref="listElement" class="chat-messages" aria-live="polite">
                <p v-if="!hasMessages" class="chat-empty">
                    Xin chào! Hãy để lại câu hỏi về sản phẩm hoặc báo giá, chuyên viên sẽ trả lời bạn tại đây.
                </p>
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="chat-bubble"
                    :class="message.sender === 'visitor' ? 'is-visitor' : 'is-admin'"
                >
                    <span>{{ message.body }}</span>
                    <small>{{ formatTime(message.created_at) }}</small>
                </div>
            </div>

            <form class="chat-form" @submit.prevent="send">
                <div class="chat-identity">
                    <input v-model="name" type="text" maxlength="255" placeholder="Tên của bạn" autocomplete="name" @change="saveIdentity">
                    <input v-model="phone" type="tel" maxlength="30" pattern="[0-9+().\-\s]{8,30}" placeholder="Số điện thoại" autocomplete="tel" @change="saveIdentity">
                    <input v-model="email" type="email" maxlength="255" placeholder="Email" autocomplete="email" @change="saveIdentity">
                </div>
                <p v-if="errorMessage" class="chat-error">{{ errorMessage }}</p>
                <div class="chat-input-row">
                    <textarea
                        :value="draft"
                        rows="1"
                        :maxlength="MAX_LENGTH"
                        placeholder="Nhập tin nhắn..."
                        aria-label="Nội dung tin nhắn"
                        @input="draft = ($event.target as HTMLTextAreaElement).value"
                        @keydown.enter="onEnter"
                    ></textarea>
                    <button type="submit" :class="{ 'is-ready': canSend }" :disabled="!canSend" aria-label="Gửi tin nhắn">
                        <FontAwesomeIcon :icon="['fas', 'paper-plane']" />
                    </button>
                </div>
            </form>
        </section>

        <button class="chat-toggle" type="button" :aria-label="isOpen ? 'Đóng chat' : 'Mở chat'" @click="toggle">
            <FontAwesomeIcon :icon="['fas', isOpen ? 'xmark' : 'comments']" />
            <span v-if="unreadCount > 0 && !isOpen" class="chat-badge">{{ unreadCount }}</span>
        </button>
    </div>
</template>
