<?php

declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Auth;

use League\OAuth2\Client\Provider\Google;

final class GoogleOAuthService
{
    private Google $provider;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        $this->provider = new Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUri,
        ]);
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->provider->getAuthorizationUrl([
            'state' => $state,
            'scope' => ['openid', 'email', 'profile'],
        ]);
    }

    /**
     * Returns ['id', 'email', 'firstName', 'lastName'].
     */
    public function getUserInfo(string $code): array
    {
        $token     = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
        $ownerData = $this->provider->getResourceOwner($token);
        $arr       = $ownerData->toArray();
        return [
            'id'        => $ownerData->getId(),
            'email'     => $ownerData->getEmail(),
            'firstName' => $arr['given_name']  ?? '',
            'lastName'  => $arr['family_name'] ?? '',
        ];
    }
}
