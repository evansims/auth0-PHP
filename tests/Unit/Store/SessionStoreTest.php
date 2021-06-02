<?php

declare(strict_types=1);

namespace Auth0\Tests\Unit\Store;

use Auth0\SDK\Store\SessionStore;
use PHPUnit\Framework\TestCase;

/**
 * Class SessionStoreTest.
 * Tests the SessionStore class.
 */
class SessionStoreTest extends TestCase
{
    /**
     * Session key for test values.
     */
    protected const TEST_KEY = 'never_compromise_on_identity';

    /**
     * Session value to test.
     */
    protected const TEST_VALUE = '__Auth0__';

    /**
     * Expected cookie lifetime of 1 week.
     * 60 s/min * 60 min/h * 24 h/day * 7 days.
     */
    protected const COOKIE_LIFETIME = 604800;

    /**
     * Reusable instance of SessionStore class to be tested.
     */
    public static SessionStore $sessionStore;

    /**
     * Full session array key.
     */
    public static string $sessionKey;

    /**
     * Test fixture for class, runs once before any tests.
     */
    public static function setUpBeforeClass(): void
    {
        self::$sessionStore = new SessionStore();
        self::$sessionKey = 'auth0_' . self::TEST_KEY;
    }

    /**
     * Test that SessionStore::initSession ran and cookie params are stored correctly.
     */
    public function testInitSession(): void
    {
        // Suppressing "headers already sent" warning related to cookies.
        @self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE); // phpcs:ignore

        // Make sure we have a session to check.
        $this->assertNotEmpty(session_id());
    }

    /**
     * Test that SessionStore::getSessionKeyName returns the expected name.
     */
    public function testGetSessionKey(): void
    {
        $test_this_key_name = self::$sessionStore->getSessionKeyName(self::TEST_KEY);
        $this->assertEquals(self::$sessionKey, $test_this_key_name);
    }

    /**
     * Test that SessionStore::set stores the correct value.
     */
    public function testSet(): void
    {
        // Make sure this key does not exist yet so we can test that it was set.
        $_SESSION = [];

        // Suppressing "headers already sent" warning related to cookies.
        self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE);

        $this->assertEquals(self::TEST_VALUE, $_SESSION[self::$sessionKey]);
    }

    /**
     * Test that SessionStore::get stores the correct value.
     */
    public function testGet(): void
    {
        $_SESSION[self::$sessionKey] = self::TEST_VALUE;
        $this->assertEquals(self::TEST_VALUE, self::$sessionStore->get(self::TEST_KEY));
    }

    /**
     * Test that SessionStore::delete trashes the stored value.
     */
    public function testDelete(): void
    {
        $_SESSION[self::$sessionKey] = self::TEST_VALUE;
        $this->assertTrue(isset($_SESSION[self::$sessionKey]));

        self::$sessionStore->delete(self::TEST_KEY);

        $this->assertNull(self::$sessionStore->get(self::TEST_KEY));
        $this->assertFalse(isset($_SESSION[self::$sessionKey]));
    }

    /**
     * Test that custom base names can be set and return the correct value.
     */
    public function testCustomSessionBaseName(): void
    {
        $test_base_name = 'test_base_name';

        self::$sessionStore = new SessionStore($test_base_name);
        $test_this_key_name = self::$sessionStore->getSessionKeyName(self::TEST_KEY);
        $this->assertEquals($test_base_name . '_' . self::TEST_KEY, $test_this_key_name);

        // Suppressing "headers already sent" warning related to cookies.
        @self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE); // phpcs:ignore

        $this->assertEquals(self::TEST_VALUE, self::$sessionStore->get(self::TEST_KEY));
    }
}
