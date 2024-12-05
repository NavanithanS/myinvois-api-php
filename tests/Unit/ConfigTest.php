<?php

namespace Nava\MyInvois\Tests\Unit;

use Nava\MyInvois\Config;
use Nava\MyInvois\Tests\TestCase;

class ConfigTest extends TestCase
{
    /** @test */
    public function it_validates_supported_versions(): void
    {
        // Test invoice versions
        $this->assertTrue(Config::isVersionSupported('invoice', '1.0'));
        $this->assertTrue(Config::isVersionSupported('invoice', '1.1'));
        $this->assertFalse(Config::isVersionSupported('invoice', '2.0'));

        // Test credit note versions
        $this->assertTrue(Config::isVersionSupported('credit_note', '1.0'));
        $this->assertTrue(Config::isVersionSupported('credit_note', '1.1'));
        $this->assertFalse(Config::isVersionSupported('credit_note', '2.0'));

        // Test debit note versions
        $this->assertTrue(Config::isVersionSupported('debit_note', '1.0'));
        $this->assertTrue(Config::isVersionSupported('debit_note', '1.1'));
        $this->assertFalse(Config::isVersionSupported('debit_note', '2.0'));

        // Test invalid document type
        $this->assertFalse(Config::isVersionSupported('invalid_type', '1.0'));
    }

    /** @test */
    public function it_returns_current_versions(): void
    {
        $this->assertEquals('1.1', Config::getCurrentVersion('invoice'));
        $this->assertEquals('1.1', Config::getCurrentVersion('credit_note'));
        $this->assertEquals('1.1', Config::getCurrentVersion('debit_note'));
    }

    /** @test */
    public function it_throws_exception_for_invalid_document_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid document type. Must be one of: invoice, credit_note, debit_note');

        Config::getCurrentVersion('invalid_type');
    }

    /** @test */
    public function it_maintains_version_consistency(): void
    {
        // Ensure current versions are included in supported versions
        $this->assertContains(
            Config::getCurrentVersion('invoice'),
            Config::INVOICE_SUPPORTED_VERSIONS
        );

        $this->assertContains(
            Config::getCurrentVersion('credit_note'),
            Config::CREDIT_NOTE_SUPPORTED_VERSIONS
        );

        $this->assertContains(
            Config::getCurrentVersion('debit_note'),
            Config::DEBIT_NOTE_SUPPORTED_VERSIONS
        );
    }
}
