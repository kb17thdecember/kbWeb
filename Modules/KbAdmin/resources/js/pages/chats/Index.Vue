<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Chat với khách hàng</h2>
                <p>Trả lời tin nhắn khách gửi từ website. Tin mới được cập nhật tự động.</p>
            </div>
            <div class="actions">
                <em>{{ totalUnread }} tin chưa đọc</em>
            </div>
        </div>

        <div class="chat-admin">
            <aside class="chat-list" aria-label="Danh sách hội thoại">
                <p v-if="!conversations.length" class="chat-list-empty">Chưa có cuộc trò chuyện nào.</p>
                <button
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="chat-list-item"
                    :class="{ active: conversation.id === activeId }"
                    type="button"
                    @click="selectConversation(conversation.id)"
                >
                    <div class="chat-list-top">
                        <b>{{ conversation.visitor_name || `Khách #${conversation.id}` }}</b>
                        <span v-if="conversation.unread_count" class="count" style="background-color: red; color: white; padding: 1px 7px; border-radius: 12px;">{{ conversation.unread_count }}</span>
                    </div>
                    <small>{{ conversation.last_message || '...' }}</small>
                    <small class="chat-list-meta">
                        {{ formatDateTime(conversation.last_message_at) }}
                        <template v-if="conversation.status === 'closed'"> · Đã đóng</template>
                    </small>
                </button>
            </aside>

            <section class="chat-thread">
                <div v-if="!activeConversation" class="chat-thread-empty">
                    Chọn một cuộc trò chuyện để bắt đầu trả lời.
                </div>
                <template v-else>
                    <header class="chat-thread-head">
                        <div>
                            <b>{{ activeConversation.visitor_name || `Khách #${activeConversation.id}` }}</b>
                            <small>{{ [activeConversation.visitor_email, activeConversation.visitor_phone].filter(Boolean).join(' · ') || 'Chưa có email / số điện thoại' }}</small>
                        </div>
                        <div class="chat-thread-actions">
                            <button class="btn" type="button" @click="toggleStatus">
                                {{ activeConversation.status === 'closed' ? 'Mở lại' : 'Đóng hội thoại' }}
                            </button>
                            <button class="btn" type="button" @click="removeConversation">
                                <FontAwesomeIcon :icon="['fas', 'trash']" /> Xóa
                            </button>
                        </div>
                    </header>

                    <div ref="threadElement" class="chat-thread-body">
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="chat-msg"
                            :class="message.sender === 'admin' ? 'is-admin' : 'is-visitor'"
                        >
                            <span>{{ message.body }}</span>
                            <small>{{ formatDateTime(message.created_at) }}</small>
                        </div>
                    </div>

                    <form class="chat-reply" @submit.prevent="send">
                        <textarea
                            v-model="draft"
                            rows="2"
                            maxlength="2000"
                            placeholder="Nhập câu trả lời... (Enter để gửi, Shift+Enter xuống dòng)"
                            aria-label="Nội dung trả lời"
                            @keydown.enter="onEnter"
                        ></textarea>
                        <button class="btn primary" type="submit" :disabled="isSending || !draft.trim()">
                            <FontAwesomeIcon :icon="['fas', 'paper-plane']" /> Gửi
                        </button>
                    </form>
                </template>
            </section>
        </div>

        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';

    type Conversation = {
        id: number;
        visitor_name: string | null;
        visitor_email: string | null;
        visitor_phone: string | null;
        status: 'open' | 'closed';
        unread_count: number;
        last_message: string | null;
        last_message_at: string | null;
    };

    type ChatMessage = {
        id: number;
        sender: 'visitor' | 'admin';
        body: string;
        created_at: string | null;
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        conversations: Conversation[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const POLL_MS = 4000;

    const conversations = ref<Conversation[]>(props.conversations);
    const activeId = ref<number | null>(null);
    const messages = ref<ChatMessage[]>([]);
    const draft = ref('');
    const isSending = ref(false);
    const threadElement = ref<HTMLElement | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let lastId = 0;
    let pollTimer: ReturnType<typeof setTimeout> | undefined;
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const activeConversation = computed(() =>
        conversations.value.find((conversation) => conversation.id === activeId.value) ?? null,
    );
    const totalUnread = computed(() =>
        conversations.value.reduce((sum, conversation) => sum + conversation.unread_count, 0),
    );

    watch(() => props.conversations, (value) => {
        conversations.value = value;
    });

    const csrfToken = (): string =>
        decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');

    const request = (url: string, init: RequestInit = {}): Promise<Response> =>
        fetch(url, {
            credentials: 'same-origin',
            ...init,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
                ...init.headers,
            },
        });

    const formatDateTime = (value: string | null): string =>
        value
            ? new Date(value).toLocaleString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
                day: '2-digit',
                month: '2-digit',
            })
            : '';

    function showToast(message: string): void {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }

        toastMessage.value = message;
        isToastVisible.value = true;
        toastTimeout = setTimeout(() => {
            isToastVisible.value = false;
        }, 3500);
    }

    async function scrollToBottom(): Promise<void> {
        await nextTick();
        if (threadElement.value) {
            threadElement.value.scrollTop = threadElement.value.scrollHeight;
        }
    }

    async function loadMessages(): Promise<void> {
        if (activeId.value === null) {
            return;
        }

        const id = activeId.value;
        const response = await request(`/admin/chats/${id}/messages?after=${lastId}`);

        if (!response.ok || id !== activeId.value) {
            return;
        }

        const data = await response.json() as { messages: ChatMessage[] };
        const fresh = data.messages.filter((message) => message.id > lastId);

        if (fresh.length) {
            messages.value.push(...fresh);
            lastId = fresh[fresh.length - 1].id;
            void scrollToBottom();
        }
    }

    async function loadConversations(): Promise<void> {
        const response = await request('/admin/chats/list');

        if (response.ok) {
            const data = await response.json() as { conversations: Conversation[] };
            conversations.value = data.conversations;
        }
    }

    async function poll(): Promise<void> {
        try {
            await loadMessages();
            await loadConversations();
        } catch {
            // Retried on the next poll.
        }

        pollTimer = setTimeout(poll, POLL_MS);
    }

    async function selectConversation(id: number): Promise<void> {
        activeId.value = id;
        messages.value = [];
        lastId = 0;
        await loadMessages();
        await loadConversations();
    }

    async function send(): Promise<void> {
        const body = draft.value.trim();

        if (!body || activeId.value === null || isSending.value) {
            return;
        }

        isSending.value = true;

        try {
            const response = await request(`/admin/chats/${activeId.value}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                throw new Error('send failed');
            }

            draft.value = '';
            await loadMessages();
            await loadConversations();
        } catch {
            showToast('Không thể gửi tin nhắn. Vui lòng thử lại.');
        } finally {
            isSending.value = false;
        }
    }

    function onEnter(event: KeyboardEvent): void {
        if (!event.shiftKey) {
            event.preventDefault();
            void send();
        }
    }

    function toggleStatus(): void {
        if (!activeConversation.value) {
            return;
        }

        const closing = activeConversation.value.status === 'open';

        router.patch(`/admin/chats/${activeConversation.value.id}/status`, {
            status: closing ? 'closed' : 'open',
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast(closing ? 'Đã đóng hội thoại.' : 'Đã mở lại hội thoại.');
                void loadConversations();
            },
        });
    }

    function removeConversation(): void {
        if (!activeConversation.value || !window.confirm('Xóa cuộc trò chuyện này?')) {
            return;
        }

        router.delete(`/admin/chats/${activeConversation.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                activeId.value = null;
                messages.value = [];
                showToast('Đã xóa cuộc trò chuyện.');
            },
        });
    }

    onMounted(() => {
        pollTimer = setTimeout(poll, POLL_MS);
    });

    onBeforeUnmount(() => {
        if (pollTimer) {
            clearTimeout(pollTimer);
        }

        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>
