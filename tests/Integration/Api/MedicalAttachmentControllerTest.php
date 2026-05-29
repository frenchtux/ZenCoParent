<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Integration\Api;

use ZenCoParent\Tests\Integration\Support\IntegrationTestCase;

/**
 * Tests for medical record file attachments:
 *   GET    /medical-records/{id}/attachments
 *   POST   /medical-records/{id}/attachments  (multipart upload)
 *   DELETE /medical-records/{id}/attachments/{attachmentId}
 *   GET    /medical-records/{id}/attachments/{attachmentId}/download
 */
final class MedicalAttachmentControllerTest extends IntegrationTestCase
{
    private string $tenantId;
    private string $userId;
    private string $childId;
    private string $recordId;
    private string $jwt;
    private string $csrf;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable medical module via override so RequireModuleMiddleware passes
        $this->tenantId = $this->createTenant('Test Family', 'test-family');
        $this->pdo->prepare(
            "UPDATE tenants SET modules_override = '{\"medical\":true}' WHERE id = :id"
        )->execute(['id' => $this->tenantId]);

        $this->userId  = $this->createUser($this->tenantId, 'parent@example.com', 'Parent99!', 'parent');
        $this->childId = $this->createChild($this->tenantId);
        $this->recordId = $this->createMedicalRecord($this->tenantId, $this->childId, $this->userId);

        [$this->jwt, $this->csrf] = $this->loginAs();
    }

    // ── GET /medical-records/{id}/attachments ────────────────────────────────

    public function test_list_attachments_returns_empty_initially(): void
    {
        $response = $this->makeRequest(
            'GET',
            "/medical-records/{$this->recordId}/attachments",
            cookies: ['jwt' => $this->jwt],
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertSame([], $body['data']);
    }

    public function test_list_attachments_requires_auth(): void
    {
        $response = $this->makeRequest('GET', "/medical-records/{$this->recordId}/attachments");
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_list_attachments_returns_404_for_unknown_record(): void
    {
        $fakeId   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $response = $this->makeRequest(
            'GET',
            "/medical-records/{$fakeId}/attachments",
            cookies: ['jwt' => $this->jwt],
        );
        $this->assertSame(404, $response->getStatusCode());
    }

    // ── POST /medical-records/{id}/attachments ───────────────────────────────

    public function test_upload_pdf_succeeds(): void
    {
        $response = $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            '%PDF-1.4 test content',
            'rapport.pdf',
            'application/pdf',
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            csrfToken: $this->csrf,
        );

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertSame('rapport.pdf', $body['data']['filename']);
        $this->assertSame('application/pdf', $body['data']['mime_type']);
        $this->assertArrayHasKey('download_url', $body['data']);
        $this->assertArrayNotHasKey('storage_key', $body['data']); // never exposed
    }

    public function test_upload_image_succeeds(): void
    {
        // Minimal valid JPEG header
        $jpegContent = "\xFF\xD8\xFF\xE0" . str_repeat('x', 20);
        $response = $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            $jpegContent,
            'radio.jpg',
            'image/jpeg',
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            csrfToken: $this->csrf,
        );

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $response = $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            '#!/bin/sh echo hello',
            'evil.sh',
            'application/x-sh',
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            csrfToken: $this->csrf,
        );

        $this->assertSame(415, $response->getStatusCode());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        // 11 MB — over the 10 MB limit
        $bigContent = str_repeat('x', 11 * 1024 * 1024);
        $response = $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            $bigContent,
            'large.pdf',
            'application/pdf',
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            csrfToken: $this->csrf,
        );

        $this->assertSame(413, $response->getStatusCode());
    }

    public function test_upload_requires_auth(): void
    {
        $response = $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            'content',
            'test.pdf',
            'application/pdf',
        );
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── DELETE /medical-records/{id}/attachments/{attachmentId} ─────────────

    public function test_delete_attachment_succeeds(): void
    {
        // Upload first
        $uploadResponse = $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            '%PDF-1.4 test',
            'doc.pdf',
            'application/pdf',
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            csrfToken: $this->csrf,
        );
        $attachmentId = $this->decodeJson($uploadResponse)['data']['id'];

        // Delete
        $response = $this->makeRequest(
            'DELETE',
            "/medical-records/{$this->recordId}/attachments/{$attachmentId}",
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeJson($response);
        $this->assertTrue($body['data']['deleted']);

        // Confirm gone from list
        $listResponse = $this->makeRequest(
            'GET',
            "/medical-records/{$this->recordId}/attachments",
            cookies: ['jwt' => $this->jwt],
        );
        $this->assertSame([], $this->decodeJson($listResponse)['data']);
    }

    public function test_delete_returns_404_for_unknown_attachment(): void
    {
        $fakeId   = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $response = $this->makeRequest(
            'DELETE',
            "/medical-records/{$this->recordId}/attachments/{$fakeId}",
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            headers: ['X-CSRF-Token' => $this->csrf],
        );
        $this->assertSame(404, $response->getStatusCode());
    }

    // ── LIST shows uploaded file ─────────────────────────────────────────────

    public function test_uploaded_attachment_appears_in_list(): void
    {
        $this->makeUploadRequest(
            "/medical-records/{$this->recordId}/attachments",
            '%PDF-1.4 content',
            'ordonnance.pdf',
            'application/pdf',
            cookies: ['jwt' => $this->jwt, 'csrf_token' => $this->csrf],
            csrfToken: $this->csrf,
        );

        $response = $this->makeRequest(
            'GET',
            "/medical-records/{$this->recordId}/attachments",
            cookies: ['jwt' => $this->jwt],
        );

        $body = $this->decodeJson($response);
        $this->assertCount(1, $body['data']);
        $this->assertSame('ordonnance.pdf', $body['data'][0]['filename']);
        $this->assertStringContainsString('/download', $body['data'][0]['download_url']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function loginAs(): array
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email'       => 'parent@example.com',
            'password'    => 'Parent99!',
            'tenant_slug' => 'test-family',
        ]);

        $jwt = $csrf = null;
        foreach ($response->getHeader('Set-Cookie') as $c) {
            if (str_starts_with($c, 'jwt='))         { $jwt  = explode(';', substr($c, 4))[0]; }
            if (str_starts_with($c, 'csrf_token='))  { $csrf = explode(';', substr($c, 11))[0]; }
        }
        return [$jwt, $csrf];
    }
}
