<?php

declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Auth\LoginResult;
use ZenCoParent\Application\Invitation\AcceptInvitationCommand;
use ZenCoParent\Application\Invitation\AcceptInvitationHandler;
use ZenCoParent\Application\Invitation\CreateInvitationCommand;
use ZenCoParent\Application\Invitation\CreateInvitationHandler;
use ZenCoParent\Application\Invitation\GetInvitationHandler;
use ZenCoParent\Domain\Invitation\InvitationRepositoryInterface;

final class InvitationController
{
    public function __construct(
        private CreateInvitationHandler       $createHandler,
        private GetInvitationHandler          $getHandler,
        private AcceptInvitationHandler       $acceptHandler,
        private InvitationRepositoryInterface $invitationRepo,
    ) {}

    /**
     * POST /invitations — create an invitation (auth required)
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $attrs    = $request->getAttributes();
        $tenantId = $attrs['tenantId'] ?? null;
        $userId   = $attrs['userId']   ?? null;

        if ($tenantId === null || $userId === null) {
            return ApiResponse::error($response, 'Non authentifié', 401);
        }

        $body  = (array) $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $role  = trim((string) ($body['role']  ?? 'parent'));

        if ($email === '') {
            return ApiResponse::error($response, "L'adresse e-mail est requise.", 400);
        }

        $invitation = $this->createHandler->handle(
            new CreateInvitationCommand($tenantId, $userId, $email, $role)
        );

        $appUrl    = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $inviteUrl = $appUrl . '/frontend/invitation.html?token=' . $invitation->getToken();

        $data = $invitation->toArray();
        $data['invite_url'] = $inviteUrl;

        return ApiResponse::success($response, $data, 201);
    }

    /**
     * GET /invitations/{token} — public, show invitation info
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = $args['token'] ?? '';

        $data = $this->getHandler->handle($token);

        return ApiResponse::success($response, $data);
    }

    /**
     * POST /invitations/{token}/accept — public, accept and create account
     */
    public function accept(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = $args['token'] ?? '';

        $body      = (array) $request->getParsedBody();
        $firstName = trim((string) ($body['first_name'] ?? ''));
        $lastName  = trim((string) ($body['last_name']  ?? ''));
        $password  = (string) ($body['password']        ?? '');

        if ($firstName === '' || $lastName === '' || $password === '') {
            return ApiResponse::error($response, 'Champs requis : first_name, last_name, password', 400);
        }

        $result = $this->acceptHandler->handle(
            new AcceptInvitationCommand($token, $firstName, $lastName, $password)
        );

        return $this->applyAuthCookies($response, $result)->withStatus(201);
    }

    /**
     * GET /invitations — list invitations for tenant (auth required)
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $attrs    = $request->getAttributes();
        $tenantId = $attrs['tenantId'] ?? null;

        if ($tenantId === null) {
            return ApiResponse::error($response, 'Non authentifié', 401);
        }

        $invitations = $this->invitationRepo->findByTenantId($tenantId);
        $data = array_map(static fn($inv) => $inv->toArray(), $invitations);

        return ApiResponse::success($response, $data);
    }

    // ─── Cookie helpers ───────────────────────────────────────────────────────

    private function applyAuthCookies(ResponseInterface $response, LoginResult $result): ResponseInterface
    {
        $secure    = ($_ENV['APP_ENV'] ?? 'production') !== 'local' ? '; Secure' : '';
        $jwtExpiry = (int) ($_ENV['JWT_EXPIRY']         ?? 3600);
        $rfExpiry  = (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000);

        $jwtCookie  = "jwt={$result->accessToken}; Path=/; HttpOnly; SameSite=Strict{$secure}; Max-Age={$jwtExpiry}";
        $rfCookie   = "refresh_token={$result->refreshToken}; Path=/auth/refresh; HttpOnly; SameSite=Strict{$secure}; Max-Age={$rfExpiry}";
        $csrfToken  = bin2hex(random_bytes(32));
        $csrfCookie = "csrf_token={$csrfToken}; Path=/; SameSite=Strict{$secure}; Max-Age={$jwtExpiry}";

        return ApiResponse::success($response, ['user' => $result->user->toArray()])
            ->withAddedHeader('Set-Cookie', $jwtCookie)
            ->withAddedHeader('Set-Cookie', $rfCookie)
            ->withAddedHeader('Set-Cookie', $csrfCookie);
    }
}
