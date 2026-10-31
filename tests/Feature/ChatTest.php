<?php

use App\Mail\ChatMessageReceived;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function visitorSendsMessage(string $body = 'Xin chào'): void
{
    test()->postJson('/chat/messages', ['body' => $body, 'name' => 'Khách A', 'email' => 'a@example.com'])
        ->assertCreated();
}

it('lets a visitor start a conversation and read replies', function () {
    visitorSendsMessage('Cần báo giá bình nước nóng');

    $conversation = ChatConversation::query()->firstOrFail();
    expect($conversation->visitor_name)->toBe('Khách A')
        ->and($conversation->messages()->count())->toBe(1);

    $conversation->messages()->create(['sender' => ChatMessage::SENDER_ADMIN, 'body' => 'Chào bạn']);

    $this->getJson('/chat/messages')
        ->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.1.body', 'Chào bạn');

    expect($conversation->messages()->where('sender', 'admin')->whereNull('read_at')->count())->toBe(0);
});

it('emails the admin only for the first unread visitor message', function () {
    Mail::fake();

    visitorSendsMessage('một');
    visitorSendsMessage('hai');

    Mail::assertSent(ChatMessageReceived::class, 1);

    ChatMessage::query()->update(['read_at' => now()]);
    visitorSendsMessage('ba');

    Mail::assertSent(ChatMessageReceived::class, 2);
});

it('still saves the message when the notification mail fails', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    visitorSendsMessage('vẫn lưu');

    expect(ChatMessage::query()->count())->toBe(1);
});

it('stores the visitor phone and rejects an invalid one', function () {
    $this->postJson('/chat/messages', ['body' => 'hi', 'phone' => '0901234567'])->assertCreated();
    expect(ChatConversation::query()->firstOrFail()->visitor_phone)->toBe('0901234567');

    $this->postJson('/chat/messages', ['body' => 'hi', 'phone' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('only returns messages after the given id', function () {
    visitorSendsMessage('một');
    visitorSendsMessage('hai');

    $firstId = ChatMessage::query()->orderBy('id')->value('id');

    $this->getJson("/chat/messages?after={$firstId}")
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'hai');
});

it('does not expose another visitors conversation', function () {
    $other = ChatConversation::factory()->create();
    ChatMessage::factory()->for($other, 'conversation')->create(['body' => 'bí mật']);

    $this->getJson('/chat/messages')->assertOk()->assertJsonCount(0, 'messages');
});

it('validates the visitor message', function () {
    $this->postJson('/chat/messages', ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->postJson('/chat/messages', ['body' => str_repeat('a', 2001)])->assertUnprocessable();
    $this->postJson('/chat/messages', ['body' => 'hi', 'email' => 'not-an-email'])->assertUnprocessable();
});

it('reopens a closed conversation when the visitor writes again', function () {
    visitorSendsMessage();
    ChatConversation::query()->update(['status' => 'closed']);

    visitorSendsMessage('còn đó không');

    expect(ChatConversation::query()->firstOrFail()->status)->toBe('open');
});

it('requires admin login for the chat inbox', function () {
    $this->get('/admin/chats')->assertRedirect();
    $this->getJson('/admin/chats/list')->assertUnauthorized();
});

it('lets an admin read and reply to a conversation', function () {
    $conversation = ChatConversation::factory()->create();
    ChatMessage::factory()->for($conversation, 'conversation')->create(['body' => 'Khách hỏi']);

    $this->actingAs(User::factory()->admin()->create());

    $this->getJson('/admin/chats/list')
        ->assertOk()
        ->assertJsonPath('conversations.0.unread_count', 1);

    $this->getJson("/admin/chats/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Khách hỏi');

    $this->getJson('/admin/chats/list')->assertJsonPath('conversations.0.unread_count', 0);

    $this->postJson("/admin/chats/{$conversation->id}/messages", ['body' => 'Đã nhận'])->assertCreated();
    $this->postJson("/admin/chats/{$conversation->id}/messages", ['body' => ''])->assertUnprocessable();

    expect($conversation->messages()->where('sender', 'admin')->value('body'))->toBe('Đã nhận');
});

it('uses the authenticated user details when sending a chat message without identity fields', function () {
    $user = User::factory()->create([
        'name' => 'Nguyễn Văn Minh',
        'email' => 'minh@example.com',
        'phone' => '0987654321',
    ]);

    $this->actingAs($user)
        ->postJson('/chat/messages', ['body' => 'Tôi cần tư vấn báo giá'])
        ->assertCreated();

    $conversation = ChatConversation::query()->firstOrFail();
    expect($conversation->visitor_name)->toBe('Nguyễn Văn Minh')
        ->and($conversation->visitor_email)->toBe('minh@example.com')
        ->and($conversation->visitor_phone)->toBe('0987654321');
});

it('lets an admin close and delete a conversation', function () {
    $conversation = ChatConversation::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->patch("/admin/chats/{$conversation->id}/status", ['status' => 'closed'])->assertRedirect();
    expect($conversation->fresh()->status)->toBe('closed');

    $this->patch("/admin/chats/{$conversation->id}/status", ['status' => 'bogus'])->assertSessionHasErrors('status');

    $this->delete("/admin/chats/{$conversation->id}")->assertRedirect();
    expect(ChatConversation::query()->count())->toBe(0);
});
