<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Infrastructure\Auth\JWTService;
use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

final class ThreadControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $userId;
    private string $userId2;
    private string $jwtToken;
    private string $jwtToken2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->createTenant();
        $this->userId   = $this->createUser($this->tenantId, 'alice@example.com');
        $this->userId2  = $this->createUser($this->tenantId, 'bob@example.com');

        $jwtService       = new JWTService($_ENV['JWT_SECRET'], 3600);
        $this->jwtToken   = $jwtService->generateAccessToken($this->userId,  $this->tenantId, 'parent');
        $this->jwtToken2  = $jwtService->generateAccessToken($this->userId2, $this->tenantId, 'parent');
    }

    public function test_index_returns_empty_list_initially(): void
    {
        $response = $this->makeRequest('GET', '/threads', cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame([], $body['data']);
    }

    public function test_create_parents_thread_returns_201(): void
    {
        $response = $this->makeRequest('POST', '/threads', body: [
            'type'            => 'parents',
            'participant_ids' => [$this->userId, $this->userId2],
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('parents', $body['data']['type']);
        $this->assertContains($this->userId, $body['data']['participant_ids']);
        $this->assertContains($this->userId2, $body['data']['participant_ids']);
    }

    public function test_create_family_thread_returns_201(): void
    {
        $response = $this->makeRequest('POST', '/threads', body: [
            'type'            => 'family',
            'participant_ids' => [$this->userId, $this->userId2],
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertSame('family', $body['data']['type']);
    }

    public function test_create_returns_400_when_type_missing(): void
    {
        $response = $this->makeRequest('POST', '/threads', body: [
            'participant_ids' => [$this->userId2],
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_create_returns_422_for_invalid_type(): void
    {
        $response = $this->makeRequest('POST', '/threads', body: [
            'type' => 'invalid',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_created_thread_appears_in_index(): void
    {
        $this->makeRequest('POST', '/threads', body: [
            'type'            => 'parents',
            'participant_ids' => [$this->userId, $this->userId2],
        ], cookies: ['jwt' => $this->jwtToken]);

        $list = $this->decodeJson(
            $this->makeRequest('GET', '/threads', cookies: ['jwt' => $this->jwtToken])
        );
        $this->assertCount(1, $list['data']);
    }

    public function test_show_returns_thread_for_participant(): void
    {
        $created  = $this->decodeJson($this->makeRequest('POST', '/threads', body: [
            'type'            => 'parents',
            'participant_ids' => [$this->userId, $this->userId2],
        ], cookies: ['jwt' => $this->jwtToken]));

        $id       = $created['data']['id'];
        $response = $this->makeRequest('GET', "/threads/{$id}", cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($id, $this->decodeJson($response)['data']['id']);
    }

    public function test_show_returns_404_for_non_participant(): void
    {
        // Use fixture directly so auto-population is bypassed (userId2 is excluded)
        $threadId = $this->createThread($this->tenantId, [$this->userId]);

        // userId2 is NOT a participant
        $response = $this->makeRequest('GET', "/threads/{$threadId}", cookies: ['jwt' => $this->jwtToken2]);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_send_message_returns_201(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId, $this->userId2]);

        $response = $this->makeRequest('POST', "/threads/{$threadId}/messages", body: [
            'content' => 'Hello from Alice!',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('Hello from Alice!', $body['data']['content']);
        $this->assertSame($this->userId, $body['data']['senderId']);
        $this->assertFalse($body['data']['isRead']);
    }

    public function test_send_message_returns_400_for_empty_content(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId]);

        $response = $this->makeRequest('POST', "/threads/{$threadId}/messages", body: [
            'content' => '',
        ], cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_send_message_returns_401_for_non_participant(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId]);

        $response = $this->makeRequest('POST', "/threads/{$threadId}/messages", body: [
            'content' => 'Sneaky message',
        ], cookies: ['jwt' => $this->jwtToken2]);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_get_messages_returns_messages_in_order(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId, $this->userId2]);

        $this->makeRequest('POST', "/threads/{$threadId}/messages", body: ['content' => 'First'], cookies: ['jwt' => $this->jwtToken]);
        $this->makeRequest('POST', "/threads/{$threadId}/messages", body: ['content' => 'Second'], cookies: ['jwt' => $this->jwtToken2]);

        $response = $this->makeRequest('GET', "/threads/{$threadId}/messages", cookies: ['jwt' => $this->jwtToken]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertCount(2, $body['data']);
        $this->assertSame('First', $body['data'][0]['content']);
        $this->assertSame('Second', $body['data'][1]['content']);
    }

    public function test_get_messages_with_since_filters_old_messages(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId]);

        // Insert a "old" message directly with a past timestamp
        $oldMsgId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->pdo->prepare(
            "INSERT INTO messages (id, thread_id, tenant_id, sender_id, content, read_at, created_at)
             VALUES (:id, :tid, :tenid, :sid, 'Old message', NULL, '2020-01-01 00:00:00')"
        )->execute(['id' => $oldMsgId, 'tid' => $threadId, 'tenid' => $this->tenantId, 'sid' => $this->userId]);

        // Send a new message via API (it gets NOW() as created_at)
        $this->makeRequest('POST', "/threads/{$threadId}/messages", body: ['content' => 'New message'], cookies: ['jwt' => $this->jwtToken]);

        // Poll with ?since=2021-01-01T00:00:00+00:00 — should only return the new one
        $since    = urlencode('2021-01-01T00:00:00+00:00');
        $response = $this->makeRequest('GET', "/threads/{$threadId}/messages?since={$since}", cookies: ['jwt' => $this->jwtToken]);

        $body = $this->decodeJson($response);
        $this->assertCount(1, $body['data']);
        $this->assertSame('New message', $body['data'][0]['content']);
    }

    public function test_mark_read_returns_200_and_is_idempotent(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId, $this->userId2]);

        $sent = $this->decodeJson($this->makeRequest('POST', "/threads/{$threadId}/messages", body: [
            'content' => 'Read me',
        ], cookies: ['jwt' => $this->jwtToken]));

        $msgId = $sent['data']['id'];

        // Mark read once
        $r1 = $this->makeRequest('PATCH', "/threads/{$threadId}/messages/{$msgId}/read", cookies: ['jwt' => $this->jwtToken2]);
        $this->assertSame(200, $r1->getStatusCode());

        // Mark read again (idempotent)
        $r2 = $this->makeRequest('PATCH', "/threads/{$threadId}/messages/{$msgId}/read", cookies: ['jwt' => $this->jwtToken2]);
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function test_unread_count_reflected_in_thread(): void
    {
        $threadId = $this->createThread($this->tenantId, [$this->userId, $this->userId2]);

        // Alice sends 2 messages
        $this->makeRequest('POST', "/threads/{$threadId}/messages", body: ['content' => 'Msg 1'], cookies: ['jwt' => $this->jwtToken]);
        $this->makeRequest('POST', "/threads/{$threadId}/messages", body: ['content' => 'Msg 2'], cookies: ['jwt' => $this->jwtToken]);

        // Bob should see 2 unread messages
        $bobThread = $this->decodeJson(
            $this->makeRequest('GET', "/threads/{$threadId}", cookies: ['jwt' => $this->jwtToken2])
        );
        $this->assertSame(2, $bobThread['data']['unreadCount']);
    }

    public function test_returns_401_without_jwt(): void
    {
        $this->assertSame(401, $this->makeRequest('GET', '/threads')->getStatusCode());
    }
}
