<?php

use App\Models\AdminModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Models\User;

beforeEach(function () {
    $this->admin = AdminModel::create(['name' => 'Test Admin', 'email' => 'testadmin_init@example.com', 'password' => bcrypt('password')]);
    $this->user = User::factory()->create(['email' => 'admininit_http@example.com']);
});

afterEach(function () {
    SupportInquiry::where('user_id', $this->user->id)->delete();
    ChatMessage::whereIn('chat_session_id', ChatSession::where('user_id', $this->user->id)->pluck('id'))->delete();
    ChatSession::where('user_id', $this->user->id)->delete();
    $this->user->delete();
    AdminModel::where('email', 'testadmin_init@example.com')->delete();
});

test('admin can search users', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson('/admin/support/users/search?q='.substr($this->user->name, 0, 2))
        ->assertOk()
        ->assertJsonPath('users.0.email', $this->user->email);
});

test('admin search requires at least 2 chars', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson('/admin/support/users/search?q=a')
        ->assertOk()
        ->assertJson(['users' => []]);
});

test('admin can initiate direct message to user without prior ticket', function () {
    $res = $this->actingAs($this->admin, 'admin')
        ->postJson('/admin/support/initiate', ['user_id' => $this->user->id, 'message' => 'Hello from admin initiate'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $inquiryId = $res->json('inquiry.id');
    expect(SupportInquiry::find($inquiryId)->initiated_by)->toBe('admin');
    expect(SupportInquiry::find($inquiryId)->status)->toBe(SupportInquiry::STATUS_HUMAN_ACTIVE);

    // User can see it via history (token recovered)
    $sessionToken = $res->json('inquiry.ticket_number') ? SupportInquiry::find($inquiryId)->chatSession->session_token : ChatSession::where('user_id', $this->user->id)->first()->session_token;
    $this->actingAs($this->user)
        ->getJson('/chat/history?session_token='.$sessionToken)
        ->assertOk()
        ->assertJsonPath('handoff_status', SupportInquiry::STATUS_HUMAN_ACTIVE);
});

test('user discovers admin-initiated via active endpoint even with stale token', function () {
    // Create an admin-initiated inquiry
    $this->actingAs($this->admin, 'admin')->postJson('/admin/support/initiate', ['user_id' => $this->user->id, 'message' => 'Admin hello']);
    $latest = ChatSession::where('user_id', $this->user->id)->latest()->first();

    // Simulate stale token (different guest token)
    $stale = ChatSession::generateToken();
    ChatSession::create(['session_token' => $stale, 'user_id' => null]);

    $this->actingAs($this->user)
        ->getJson('/chat/active')
        ->assertOk()
        ->assertJsonPath('has_active', true)
        ->assertJsonPath('session_token', $latest->session_token);
});

test('chat history survives logout+login token loss', function () {
    // Simulate user chatting, token T1
    $token = ChatSession::generateToken();
    $session = ChatSession::create(['session_token' => $token, 'user_id' => $this->user->id]);
    ChatMessage::create(['chat_session_id' => $session->id, 'sender' => 'user', 'message' => 'Persist me']);
    ChatMessage::create(['chat_session_id' => $session->id, 'sender' => 'bot', 'message' => 'Persisted']);

    // Guest probing should not leak and should not overwrite owner's token (isolated flag)
    $guestRes = $this->getJson('/chat/history?session_token='.$token);
    $guestRes->assertOk()->assertJson(['isolated' => true, 'messages' => []]);

    // Authed user with null token recovers via latest
    $this->actingAs($this->user)->getJson('/chat/history')
        ->assertOk()->assertJsonPath('messages.0.text', 'Persist me');
});

test('poll returns isolated false for owner', function () {
    $token = ChatSession::generateToken();
    $session = ChatSession::create(['session_token' => $token, 'user_id' => $this->user->id]);
    ChatMessage::create(['chat_session_id' => $session->id, 'sender' => 'admin', 'message' => 'Hi admin']);
    $this->actingAs($this->user)->getJson('/chat/poll?session_token='.$token)->assertOk()->assertJson(['isolated' => false]);
});
