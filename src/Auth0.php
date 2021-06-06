<?php

declare(strict_types=1);

namespace Auth0\SDK;

use Auth0\SDK\API\Authentication;
use Auth0\SDK\API\Management;
use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Configuration\SdkState;
use Auth0\SDK\Utility\HttpResponse;
use Auth0\SDK\Utility\TransientStoreHandler;

/**
 * Class Auth0.
 */
final class Auth0
{
    public const VERSION = '8.0.0';

    /**
     * Instance of SdkConfiguration, for shared configuration across classes.
     */
    private SdkConfiguration $configuration;

    /**
     * Instance of SdkState, for shared state across classes.
     */
    private SdkState $state;

    /**
     * Instance of TransientStoreHandler for storing ephemeral data.
     */
    private TransientStoreHandler $transient;

    /**
     * Authentication Client.
     */
    private ?Authentication $authentication = null;

    /**
     * Authentication Client.
     */
    private ?Management $management = null;

    /**
     * Auth0 Constructor.
     *
     * @param SdkConfiguration|array<mixed> $configuration Required. Base configuration options for the SDK. See the SdkConfiguration class constructor for options.
     *
     * @throws \Auth0\SDK\Exception\ConfigurationException When `domain`, `clientId`, or `redirectUri` are not provided.
     * @throws \Auth0\SDK\Exception\ConfigurationException When `tokenAlgorithm` is provided but the value is not supported.
     * @throws \Auth0\SDK\Exception\ConfigurationException When `tokenMaxAge` or `tokenLeeway` are provided but the value is not numeric.
     */
    public function __construct(
        $configuration
    ) {
        // If we're passed an array, construct a new SdkConfiguration from that structure.
        if (is_array($configuration)) {
            $configuration = new SdkConfiguration($configuration);
        }

        // Store the configuration internally.
        $this->configuration = $configuration;

        // Create a transient storage handler using the configured transientStorage medium.
        $this->transient = new TransientStoreHandler($configuration->getTransientStorage());

        // Setup active state using session data when available.
        // Otherwise, instantiate a new session.
        $this->restoreState();
    }

    /**
     * Create, configure, and return an instance of the Authentication class.
     */
    public function authentication(): Authentication
    {
        if ($this->authentication === null) {
            $this->authentication = new Authentication($this->configuration);
        }

        return $this->authentication;
    }

    /**
     * Create, configure, and return an instance of the Management class.
     */
    public function management(): Management
    {
        if ($this->management === null) {
            $this->management = new Management($this->configuration);
        }

        return $this->management;
    }

    /**
     * Redirect to the hosted login page for a specific client.
     *
     * @param array<int|string|null>|null $params Additional parameters to include with the request.
     *
     * @link https://auth0.com/docs/api/authentication#login
     */
    public function login(
        ?array $params = null
    ): void {
        header('Location: ' . $this->authentication()->getLoginLink($params));
    }

    /**
     * Delete any persistent data and clear out all stored properties, and redirect to Auth0 /logout endpoint.
     *
     * @param string|null                 $returnUri Optional. URI to return to after logging out. Defaults to the SDK's configured redirectUri.
     * @param array<int|string|null>|null $params    Optional. Additional parameters to include with the request.
     */
    public function logout(
        ?string $returnUri = null,
        ?array $params = null
    ): void {
        $this->clear();
        header('Location: ' . $this->authentication()->getLogoutLink($returnUri, $params));
    }

    /**
     * Delete any persistent data and clear out all stored properties.
     */
    public function clear(): void
    {
        if ($this->configuration->hasSessionStorage()) {
            foreach (['user', 'idToken', 'accessToken', 'refreshToken', 'accessTokenExpiration'] as $key) {
                $this->configuration->getSessionStorage()->delete($key);
            }
        }

        $this->state->reset();
    }

    /**
     * Verifies and decodes an ID token using the properties in this class.
     *
     * @param string             $token             ID token to verify and decode.
     * @param array<string>      $tokenAudience     Optional. An array of allowed values for the 'aud' claim. Successful if ANY match.
     * @param array<string>|null $tokenOrganization Optional. An array of allowed values for the 'org_id' claim. Successful if ANY match.
     * @param string|null        $tokenNonce        Optional. The value expected for the 'nonce' claim.
     * @param int|null           $tokenMaxAge       Optional. Maximum window of time in seconds since the 'auth_time' to accept the token.
     * @param int|null           $tokenLeeway       Optional. Leeway in seconds to allow during time calculations. Defaults to 60.
     * @param int|null           $tokenNow          Optional. Optional. Unix timestamp representing the current point in time to use for time calculations.
     *
     * @throws \Auth0\SDK\Exception\InvalidTokenException
     */
    public function decode(
        string $token,
        ?array $tokenAudience = null,
        ?array $tokenOrganization = null,
        ?string $tokenNonce = null,
        ?int $tokenMaxAge = null,
        ?int $tokenLeeway = null,
        ?int $tokenNow = null
    ): Token {
        // instantiate Token handler using the provided JWT, expecting an ID token, using the SDK configuration.
        $token = new Token($this->configuration, $token, Token::TYPE_ID_TOKEN);

        // Verify token signature.
        $token->verify();

        // Validate token claims.
        $token->validate(
            null,
            $tokenAudience,
            $tokenOrganization,
            $tokenNonce,
            $tokenMaxAge,
            $tokenLeeway,
            $tokenNow
        );

        return $token;
    }

    /**
     * Exchange authorization code for access, ID, and refresh tokens
     *
     * @throws \Auth0\SDK\Exception\StateException If the state value is missing or invalid.
     * @throws \Auth0\SDK\Exception\StateException If there is already an active session.
     * @throws \Auth0\SDK\Exception\StateException If access token is missing from the response.
     * @throws \Auth0\SDK\Exception\NetworkException When the API request fails due to a network error.
     *
     * @link https://auth0.com/docs/api-auth/tutorials/authorization-code-grant
     */
    public function exchange(): bool
    {
        $code = $this->getRequestParameter('code');
        $state = $this->getRequestParameter('state');
        $code_verifier = null;

        if ($code === null) {
            return false;
        }

        if ($state === null || ! $this->transient->verify('state', $state)) {
            throw \Auth0\SDK\Exception\StateException::invalidState();
        }

        if ($this->configuration->getUsePkce()) {
            $code_verifier = $this->transient->getOnce('code_verifier');

            if ($code_verifier === null) {
                throw \Auth0\SDK\Exception\StateException::missingCodeVerifier();
            }
        }

        if ($this->state->hasUser()) {
            throw \Auth0\SDK\Exception\StateException::existingSession();
        }

        $response = $this->authentication()->codeExchange($code, $this->configuration->getRedirectUri(), $code_verifier);

        if (! HttpResponse::wasSuccessful($response)) {
            throw \Auth0\SDK\Exception\StateException::failedCodeExchange();
        }

        $response = HttpResponse::decodeContent($response);

        if (! isset($response['access_token']) || ! $response['access_token']) {
            throw \Auth0\SDK\Exception\StateException::badAccessToken();
        }

        $this->setAccessToken($response['access_token']);

        if (isset($response['refresh_token'])) {
            $this->setRefreshToken($response['refresh_token']);
        }

        if (isset($response['id_token'])) {
            if (! $this->transient->isset('nonce')) {
                throw \Auth0\SDK\Exception\StateException::missingNonce();
            }

            $this->setIdToken($response['id_token']);
        }

        if (isset($response['expires_in']) && is_numeric($response['expires_in'])) {
            $expiresIn = time() + (int) $response['expires_in'];
            $this->setAccessTokenExpiration($expiresIn);
        }

        $user = $this->state->getIdTokenDecoded();

        if ($user === null || $this->configuration->getQueryUserInfo() === true) {
            $response = $this->authentication()->userInfo($response['access_token']);

            if (HttpResponse::wasSuccessful($response)) {
                $user = HttpResponse::decodeContent($response);
            }
        }

        $this->setUser($user ?? []);
        return true;
    }

    /**
     * Renews the access token and ID token using an existing refresh token.
     * Scope "offline_access" must be declared in order to obtain refresh token for later token renewal.
     *
     * @param array<int|string|null>|null $params Optional. Additional parameters to include with the request.
     *
     * @throws \Auth0\SDK\Exception\StateException If the Auth0 object does not have access token and refresh token, or the API did not renew tokens properly.
     *
     * @link   https://auth0.com/docs/tokens/refresh-token/current
     */
    public function renew(
        ?array $params = null
    ): void {
        $refreshToken = $this->state->getRefreshToken();

        if ($refreshToken === null) {
            throw \Auth0\SDK\Exception\StateException::failedRenewTokenMissingRefreshToken();
        }

        $response = $this->authentication()->refreshToken($refreshToken, $params);
        $response = HttpResponse::decodeContent($response);

        if (! isset($response['access_token']) || ! $response['access_token']) {
            throw \Auth0\SDK\Exception\StateException::failedRenewTokenMissingAccessToken();
        }

        $this->setAccessToken($response['access_token']);

        if (isset($response['id_token'])) {
            $this->setIdToken($response['id_token']);
        }
    }

    /**
     * Return an object representing the current session state (including id token, access token, access token expiration, refresh token and user data) without triggering an authorization flow. Returns null when session data is not available.
     */
    public function getState(): ?object
    {
        $user = $this->state->getUser();

        if ($user === null) {
            return null;
        }

        $token = $this->state->getIdToken();
        $accessToken = $this->state->getAccessToken();
        $accessTokenExpiration = $this->state->getAccessTokenExpiration();
        $accessTokenExpired = false;
        $refreshToken = $this->state->getRefreshToken();

        if ($accessTokenExpiration === null) {
            $accessTokenExpired = null;
        } else {
            if (time() >= $accessTokenExpiration) {
                $accessTokenExpired = true;
            }
        }

        return (object) [
            'user' => $user,
            'token' => $token,
            'accessToken' => $accessToken,
            'accessTokenExpiration' => $accessTokenExpiration,
            'accessTokenExpired' => $accessTokenExpired,
            'refreshToken' => $refreshToken,
        ];
    }

    /**
     * Get ID token from persisted session or from a code exchange
     *
     * @throws \Auth0\SDK\Exception\StateException (see self::exchange()).
     * @throws \Auth0\SDK\Exception\SdkException (see self::exchange()).
     */
    public function getIdToken(): ?string
    {
        if (! $this->state->hasIdToken()) {
            $this->exchange();
        }

        return $this->state->getIdToken();
    }

    /**
     * Get userinfo from persisted session or from a code exchange
     *
     * @return array<string,array|int|string>|null
     *
     * @throws \Auth0\SDK\Exception\StateException (see self::exchange()).
     * @throws \Auth0\SDK\Exception\SdkException (see self::exchange()).
     */
    public function getUser(): ?array
    {
        if (! $this->state->hasUser()) {
            $this->exchange();
        }

        return $this->state->getUser();
    }

    /**
     * Get access token from persisted session or from a code exchange
     *
     * @throws \Auth0\SDK\Exception\StateException (see self::exchange()).
     * @throws \Auth0\SDK\Exception\SdkException (see self::exchange()).
     */
    public function getAccessToken(): ?string
    {
        if (! $this->state->hasAccessToken()) {
            $this->exchange();
        }

        return $this->state->getAccessToken();
    }

    /**
     * Get refresh token from persisted session or from a code exchange
     *
     * @throws \Auth0\SDK\Exception\StateException (see self::exchange()).
     * @throws \Auth0\SDK\Exception\SdkException (see self::exchange()).
     */
    public function getRefreshToken(): ?string
    {
        if (! $this->state->hasRefreshToken()) {
            $this->exchange();
        }

        return $this->state->getRefreshToken();
    }

    /**
     * Get token expiration from persisted session or from a code exchange
     *
     * @throws \Auth0\SDK\Exception\StateException (see self::exchange()).
     * @throws \Auth0\SDK\Exception\SdkException (see self::exchange()).
     */
    public function getAccessTokenExpiration(): ?int
    {
        if (! $this->state->hasAccessTokenExpiration()) {
            $this->exchange();
        }

        return $this->state->getAccessTokenExpiration();
    }

    /**
     * Sets, validates, and persists the ID token.
     *
     * @param string $idToken Id token returned from the code exchange.
     *
     * @throws \Auth0\SDK\Exception\InvalidTokenException When an invalid token is passed.
     */
    public function setIdToken(
        string $idToken
    ): self {
        $this->state->setIdTokenDecoded($this->decode($idToken)->toArray());
        $this->state->setIdToken($idToken);

        if ($this->configuration->hasSessionStorage() && $this->configuration->getPersistIdToken()) {
            $this->configuration->getSessionStorage()->set('idToken', $idToken);
        }

        return $this;
    }

    /**
     * Set the user property to a userinfo array and, if configured, persist
     *
     * @param array<array|int|string> $user User data to store.
     */
    public function setUser(
        array $user
    ): self {
        $this->state->setUser($user);

        if ($this->configuration->hasSessionStorage() && $this->configuration->getPersistUser()) {
            $this->configuration->getSessionStorage()->set('user', $user);
        }

        return $this;
    }

    /**
     * Sets and persists the access token.
     *
     * @param string $accessToken Access token returned from the code exchange.
     */
    public function setAccessToken(
        string $accessToken
    ): self {
        $this->state->setAccessToken($accessToken);

        if ($this->configuration->hasSessionStorage() && $this->configuration->getPersistAccessToken()) {
            $this->configuration->getSessionStorage()->set('accessToken', $accessToken);
        }

        return $this;
    }

    /**
     * Sets and persists the refresh token.
     *
     * @param string $refreshToken Refresh token returned from the code exchange.
     */
    public function setRefreshToken(
        string $refreshToken
    ): self {
        $this->state->setRefreshToken($refreshToken);

        if ($this->configuration->hasSessionStorage() && $this->configuration->getPersistRefreshToken()) {
            $this->configuration->getSessionStorage()->set('refreshToken', $refreshToken);
        }

        return $this;
    }

    /**
     * Sets and persists the refresh token.
     *
     * @param int $accessTokenExpiration Unix timestamp representing the expiration time on the access token.
     */
    public function setAccessTokenExpiration(
        int $accessTokenExpiration
    ): self {
        $this->state->setAccessTokenExpiration($accessTokenExpiration);

        if ($this->configuration->hasSessionStorage() && $this->configuration->getPersistAccessToken()) {
            $this->configuration->getSessionStorage()->set('accessTokenExpiration', $accessTokenExpiration);
        }

        return $this;
    }

    /**
     * Get the specified parameter from POST or GET, depending on configured response mode.
     *
     * @param string $parameterName Name of the parameter to pull from the request.
     */
    public function getRequestParameter(
        string $parameterName
    ): ?string {
        $responseMode = $this->configuration->getResponseMode();

        if ($responseMode === 'query' && isset($_GET[$parameterName])) {
            return filter_var($_GET[$parameterName], FILTER_SANITIZE_STRING, FILTER_NULL_ON_FAILURE);
        }

        if ($responseMode === 'form_post' && isset($_POST[$parameterName])) {
            return filter_var($_POST[$parameterName], FILTER_SANITIZE_STRING, FILTER_NULL_ON_FAILURE);
        }

        return null;
    }

    /**
     * Get the invitation details GET request
     */
    public function getInvitationParameters(): ?object
    {
        $invite = $this->getRequestParameter('invitation');
        $orgId = $this->getRequestParameter('organization');
        $orgName = $this->getRequestParameter('organization_name');

        if ($invite !== null && $orgId !== null && $orgName !== null) {
            return (object) [
                'invitation' => $invite,
                'organization' => $orgId,
                'organizationName' => $orgName,
            ];
        }

        return null;
    }

    /**
     * If invitation parameters are present in the request, handle extraction and automatically redirect to Universal Login.
     */
    public function handleInvitation(): void
    {
        $invite = $this->getInvitationParameters();

        if ($invite !== null) {
            $this->login([
                'invitation' => (string) $invite->invitation,
                'organization' => (string) $invite->organization,
            ]);
        }
    }

    /**
     * Retrieve state from session storage and configure SDK state.
     */
    private function restoreState(): void
    {
        $state = [];

        if ($this->configuration->hasSessionStorage()) {
            if ($this->configuration->getPersistUser()) {
                $state['user'] = $this->configuration->getSessionStorage()->get('user');
            }

            if ($this->configuration->getPersistIdToken()) {
                $state['idToken'] = $this->configuration->getSessionStorage()->get('idToken');
            }

            if ($this->configuration->getPersistAccessToken()) {
                $state['accessToken'] = $this->configuration->getSessionStorage()->get('accessToken');

                $expires = $this->configuration->getSessionStorage()->get('accessTokenExpiration');

                if ($expires !== null) {
                    $state['accessTokenExpiration'] = (int) $expires;
                }
            }

            if ($this->configuration->getPersistRefreshToken()) {
                $state['refreshToken'] = $this->configuration->getSessionStorage()->get('refreshToken');
            }
        }

        $this->state = new SdkState($state);
    }
}
