<?php

declare(strict_types=1);

namespace Auth0\SDK\Store;

use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Contract\StoreInterface;
use Auth0\SDK\Utility\Validate;

/**
 * Class CookieStore.
 * This class provides a layer to persist transient auth data using cookies.
 */
class CookieStore implements StoreInterface
{
    public const KEY_HASHING_ALGO = 'sha256';
    public const KEY_CHUNKING_THRESHOLD = 4096;
    public const KEY_SEPARATOR = '_';
    public const VAL_CRYPTO_ALGO = 'aes-128-gcm';

    /**
     * Instance of SdkConfiguration, for shared configuration across classes.
     */
    private SdkConfiguration $configuration;

    /**
     * Cookie base name.
     * Use 'cookiePrefix' argument to set this during instantiation.
     */
    private string $cookiePrefix;

    /**
     * Number of bytes to deduct/buffer from KEY_CHUNKING_THRESHOLD before chunking begins.
     */
    private int $chunkingThreshold;

    /**
     * CookieStore constructor.
     *
     * @param SdkConfiguration $configuration   Required. Base configuration options for the SDK. See the SdkConfiguration class constructor for options.
     * @param string           $cookiePrefix    Optional. A string to prefix stored cookie keys with.
     */
    public function __construct(
        SdkConfiguration &$configuration,
        string $cookiePrefix = 'auth0'
    ) {
        Validate::string($cookiePrefix, 'cookiePrefix');

        $this->configuration = & $configuration;
        $this->cookiePrefix = $cookiePrefix;

        $this->chunkingThreshold = self::KEY_CHUNKING_THRESHOLD - strlen(hash(self::KEY_HASHING_ALGO, 'threshold'));
    }

    /**
     * Persists $value on cookies, identified by $key.
     *
     * @param string $key   Cookie to set.
     * @param mixed  $value Value to use.
     */
    public function set(
        string $key,
        $value
    ): void {
        Validate::string($key, 'key');

        $cookieName = $this->getCookieName($key);

        $expiration = $this->configuration->getCookieExpires();

        if ($expiration !== 0) {
            $expiration = time() + $expiration;
        }

        $cookieOptions = [
            'expires' => $expiration,
            'domain' => $this->configuration->getCookieDomain() ?? $_SERVER['HTTP_HOST'],
            'path' => $this->configuration->getCookiePath(),
            'secure' => $this->configuration->getCookieSecure(),
            'httponly' => true,
            'samesite' => $this->configuration->getResponseMode() === 'form_post' ? 'None' : 'Lax',
        ];

        $value = $this->encrypt($value);

        $_COOKIE[$cookieName] = $value;

        if (strlen($value) >= $this->chunkingThreshold) {
            $chunks = str_split($value, $this->chunkingThreshold);

            // @phpstan-ignore-next-line
            if ($chunks !== false) {
                $chunkIndex = 1;

                setcookie(join(self::KEY_SEPARATOR, [ $cookieName, '0']), (string) count($chunks), $cookieOptions);

                foreach ($chunks as $chunk) {
                    setcookie(join(self::KEY_SEPARATOR, [ $cookieName, $chunkIndex]), $chunk, $cookieOptions);
                    $chunkIndex++;
                }

                return;
            }
        }

        setcookie($cookieName, $value, $cookieOptions);
    }

    /**
     * Gets persisted values identified by $key.
     * If the value is not set, returns $default.
     *
     * @param string $key     Cookie to set.
     * @param mixed  $default Default to return if nothing was found.
     *
     * @return mixed
     */
    public function get(
        string $key,
        $default = null
    ) {
        Validate::string($key, 'key');

        $cookieName = $this->getCookieName($key);
        $chunks = $this->isCookieChunked($key);
        $value = '';

        if ($chunks === null) {
            return $default;
        }

        if ($chunks !== 0) {
            for ($chunk = 1; $chunk <= $chunks; $chunk++) {
                $chunkData = $_COOKIE[join(self::KEY_SEPARATOR, [ $cookieName, $chunk])] ?? null;

                if ($chunkData === null) {
                    return $default;
                }

                $value .= (string) $chunkData;
            }
        }

        if ($chunks === 0) {
            $value = $_COOKIE[$cookieName] ?? null;
        }

        if ($value !== null && mb_strlen($value) !== 0) {
            $data = $this->decrypt($value);

            if ($data !== null) {
                return $data;
            }
        }

        return $default;
    }

    /**
     * Removes a persisted value identified by $key.
     *
     * @param string $key Cookie to delete.
     */
    public function delete(
        string $key
    ): void {
        Validate::string($key, 'key');

        $cookieName = $this->getCookieName($key);
        $chunks = $this->isCookieChunked($key);
        $cookieOptions = [
            'expires' => time() - 1000,
            'path' => $this->configuration->getCookiePath(),
            'domain' => $this->configuration->getCookieDomain() ?? '',
            'secure' => $this->configuration->getCookieSecure(),
            'httponly' => true,
            'samesite' => $this->configuration->getResponseMode() === 'form_post' ? 'None' : 'Lax',
        ];

        if ($chunks === null) {
            return;
        }

        unset($_COOKIE[$cookieName]);
        setcookie($cookieName, '', $cookieOptions);

        if ($chunks !== 0) {
            unset($_COOKIE[join(self::KEY_SEPARATOR, [ $cookieName, '0'])]);
            setcookie(join(self::KEY_SEPARATOR, [ $cookieName, '0']), '', $cookieOptions);

            for ($chunk = 1; $chunk <= $chunks; $chunk++) {
                unset($_COOKIE[join(self::KEY_SEPARATOR, [ $cookieName, $chunk])]);
                setcookie(join(self::KEY_SEPARATOR, [ $cookieName, $chunk]), '', $cookieOptions);
            }
        }
    }

    /**
     * Constructs a cookie name.
     *
     * @param string $key Cookie name to prefix and return.
     */
    public function getCookieName(
        string $key
    ): string {
        return hash(self::KEY_HASHING_ALGO, $this->cookiePrefix . '_' . trim($key));
    }

    /**
     * Determine the chunkiness of a cookie. Returns the number of chunks, or 0 if unchunked. If the cookie wasn't found, returns null.
     *
     * @param string $key Cookie name to lookup.
     */
    private function isCookieChunked(
        string $key
    ): ?int {
        Validate::string($key, 'key');

        $cookieName = $this->getCookieName($key);

        if (isset($_COOKIE[join(self::KEY_SEPARATOR, [ $cookieName, '0'])])) {
            return (int) $_COOKIE[join(self::KEY_SEPARATOR, [ $cookieName, '0'])];
        }

        if (isset($_COOKIE[$cookieName])) {
            return 0;
        }

        return null;
    }

    /**
     * Encrypt data for safe storage format for a cookie.
     *
     * @param mixed $data Data to encrypt.
     */
    private function encrypt(
        $data
    ): string {
        $secret = $this->configuration->getCookieSecret();
        $ivLen = openssl_cipher_iv_length(self::VAL_CRYPTO_ALGO);

        if ($secret === null) {
            throw \Auth0\SDK\Exception\ConfigurationException::requiresCookieSecret();
        }

        if ($ivLen === false) {
            return '';
        }

        $iv = openssl_random_pseudo_bytes($ivLen);

        // @phpstan-ignore-next-line
        if ($iv === false) {
            return '';
        }

        $encrypted = openssl_encrypt(serialize($data), self::VAL_CRYPTO_ALGO, $secret, 0, $iv, $tag);
        return base64_encode(json_encode(serialize(['tag' => base64_encode($tag), 'iv' => base64_encode($iv), 'data' => $encrypted]), JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt data from a stored cookie string.
     *
     * @param string $data String representing an encrypted data structure.
     *
     * @return mixed
     */
    private function decrypt(
        string $data
    ) {
        Validate::string($data, 'data');

        $secret = $this->configuration->getCookieSecret();

        if ($secret === null) {
            throw \Auth0\SDK\Exception\ConfigurationException::requiresCookieSecret();
        }

        $data = base64_decode($data, true);

        if ($data === false) {
            return null;
        }

        $data = json_decode($data, true);

        if ($data === null) {
            return null;
        }

        $data = unserialize($data);
        $iv = base64_decode($data['iv'], true);
        $tag = base64_decode($data['tag'], true);

        if ($iv === false || $tag === false) {
            return null;
        }

        $data = openssl_decrypt($data['data'], self::VAL_CRYPTO_ALGO, $secret, 0, $iv, $tag);

        if ($data === false) {
            return null;
        }

        return unserialize($data);
    }
}
