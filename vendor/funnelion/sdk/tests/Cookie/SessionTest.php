<?php

declare(strict_types=1);

namespace Funnelion\Tests\Cookie;

use Funnelion\Cookie\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testHeaderValueDefaults(): void
    {
        $value = Session::headerValue('abc-123');

        $this->assertStringStartsWith('funnelion_session=abc-123', $value);
        $this->assertStringContainsString('Path=/', $value);
        $this->assertStringContainsString('Max-Age=2592000', $value);
        $this->assertStringContainsString('HttpOnly', $value);
        $this->assertStringContainsString('Secure', $value);
        $this->assertStringContainsString('SameSite=Lax', $value);
        $this->assertStringNotContainsString('Domain=', $value);
    }

    public function testHeaderValueUrlEncodesVisitorId(): void
    {
        $value = Session::headerValue('with space');
        $this->assertStringStartsWith('funnelion_session=with%20space', $value);
    }

    public function testHeaderValueWithCustomDomainAndShortMaxAge(): void
    {
        $value = Session::headerValue('v', maxAgeSeconds: 60, domain: 'example.com');
        $this->assertStringContainsString('Domain=example.com', $value);
        $this->assertStringContainsString('Max-Age=60', $value);
    }

    public function testHeaderValueWithSecureDisabled(): void
    {
        $value = Session::headerValue('v', secure: false);
        $this->assertStringNotContainsString('Secure', $value);
    }

    public function testReadFromCookiesArray(): void
    {
        $this->assertSame('abc', Session::readFromGlobals(['funnelion_session' => 'abc']));
    }

    public function testReadFromCookiesReturnsNullWhenAbsent(): void
    {
        $this->assertNull(Session::readFromGlobals([]));
    }

    public function testReadFromCookiesReturnsNullWhenEmpty(): void
    {
        $this->assertNull(Session::readFromGlobals(['funnelion_session' => '']));
    }
}
