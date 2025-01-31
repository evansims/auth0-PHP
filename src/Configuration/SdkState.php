<?php

declare(strict_types=1);

namespace Auth0\SDK\Configuration;

use Auth0\SDK\Contract\ConfigurableContract;
use Auth0\SDK\Mixins\ConfigurableMixin;

final class SdkState implements ConfigurableContract
{
    use ConfigurableMixin;

    /**
     * SdkState Constructor.
     *
     * @param  array<mixed>|null  $configuration  Optional. Pass an array of parameter keys and values to define the internal state of the SDK.
     * @param  string|null  $idToken  Optional. The id token currently in use for the session, if available.
     * @param  string|null  $accessToken  Optional. The access token currently in use for the session, if available.
     * @param  array<string>|null  $accessTokenScope  Optional. The scopes from the access token for the session, if available.
     * @param  string|null  $refreshToken  Optional. The refresh token currently in use for the session, if available.
     * @param  array<mixed>|null  $user  Optional. An array representing the user data, if available.
     * @param  int|null  $accessTokenExpiration  Optional. When the $accessToken is expected to expire, if available.
     */
    public function __construct(
        private ?array $configuration = null,
        public ?string $idToken = null,
        public ?string $accessToken = null,
        public ?array $accessTokenScope = null,
        public ?string $refreshToken = null,
        public ?array $user = null,
        public ?int $accessTokenExpiration = null,
    ) {
        $configuration ??= [];
        $this->applyConfigurationState($configuration);
    }

    public function setIdToken(?string $idToken = null): self
    {
        $this->idToken = $idToken;

        return $this;
    }

    public function getIdToken(?\Throwable $exceptionIfNull = null): ?string
    {
        $this->exceptionIfNull($this->idToken, $exceptionIfNull);

        return $this->idToken;
    }

    public function hasIdToken(): bool
    {
        return null !== $this->idToken;
    }

    public function setAccessToken(?string $accessToken = null): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getAccessToken(?\Throwable $exceptionIfNull = null): ?string
    {
        $this->exceptionIfNull($this->accessToken, $exceptionIfNull);

        return $this->accessToken;
    }

    public function hasAccessToken(): bool
    {
        return null !== $this->accessToken;
    }

    /**
     * @param  array<string>|null  $accessTokenScope  an array of strings representing the scopes for the access token
     */
    public function setAccessTokenScope(?array $accessTokenScope): self
    {
        $this->accessTokenScope = $this->filterArray($accessTokenScope);

        return $this;
    }

    /**
     * @return array<string>|null
     */
    public function getAccessTokenScope(?\Throwable $exceptionIfNull = null): ?array
    {
        $this->exceptionIfNull($this->accessTokenScope, $exceptionIfNull);

        return $this->accessTokenScope;
    }

    public function hasAccessTokenScope(): bool
    {
        return null !== $this->accessTokenScope;
    }

    /**
     * @param  array<string>|string  $scopes  a string (or array of strings) representing the scopes to add for the access token
     * @return array<string>|null
     */
    public function pushAccessTokenScope(array|string $scopes): ?array
    {
        if (\is_string($scopes)) {
            $scopes = [$scopes];
        }

        $this->setAccessTokenScope(array_merge($this->accessTokenScope ?? [], $scopes));

        return $this->accessTokenScope;
    }

    public function setRefreshToken(?string $refreshToken = null): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getRefreshToken(?\Throwable $exceptionIfNull = null): ?string
    {
        $this->exceptionIfNull($this->refreshToken, $exceptionIfNull);

        return $this->refreshToken;
    }

    public function hasRefreshToken(): bool
    {
        return null !== $this->refreshToken;
    }

    /**
     * @param  array<mixed>|null  $user  an array representing user data
     */
    public function setUser(?array $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return array<mixed>|null $user an array representing user data
     */
    public function getUser(?\Throwable $exceptionIfNull = null): ?array
    {
        $this->exceptionIfNull($this->user, $exceptionIfNull);

        return $this->user;
    }

    public function hasUser(): bool
    {
        return null !== $this->user;
    }

    public function setAccessTokenExpiration(?int $accessTokenExpiration = null): self
    {
        $this->accessTokenExpiration = $accessTokenExpiration;

        return $this;
    }

    public function getAccessTokenExpiration(?\Throwable $exceptionIfNull = null): ?int
    {
        $this->exceptionIfNull($this->accessTokenExpiration, $exceptionIfNull);

        return $this->accessTokenExpiration;
    }

    public function hasAccessTokenExpiration(): bool
    {
        return null !== $this->accessTokenExpiration;
    }
}
