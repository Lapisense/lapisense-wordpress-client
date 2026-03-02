<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Brain\Monkey\Functions;
use Lapisense\WordPressClient\OptionStorage;

/**
 * @covers \Lapisense\WordPressClient\OptionStorage
 */
class OptionStorageTest extends TestCase
{
    /** @var string */
    private $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    /** @var string */
    private $expectedPrefix = 'lapisense_f47ac10b_58cc_4372_a567_0e02b2c3d479_';

    public function testPrefixBuiltFromUuid(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('get_option')
            ->once()
            ->with($this->expectedPrefix . 'test_key', null)
            ->andReturn('value');

        $storage->get('test_key');
    }

    public function testGetReturnsStringValue(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('get_option')
            ->once()
            ->with($this->expectedPrefix . 'some_key', null)
            ->andReturn(42);

        $result = $storage->get('some_key');
        $this->assertSame('42', $result);
    }

    public function testGetReturnsNullWhenNotSet(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('get_option')
            ->once()
            ->with($this->expectedPrefix . 'missing_key', null)
            ->andReturn(null);

        $result = $storage->get('missing_key');
        $this->assertNull($result);
    }

    public function testSetCallsUpdateOptionWithAutoloadFalse(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('update_option')
            ->once()
            ->with($this->expectedPrefix . 'my_key', 'my_value', false);

        $storage->set('my_key', 'my_value');
    }

    public function testDeleteCallsDeleteOption(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('delete_option')
            ->once()
            ->with($this->expectedPrefix . 'some_key');

        $storage->delete('some_key');
    }

    public function testGetLicenseKeyUsesCorrectKey(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('get_option')
            ->once()
            ->with($this->expectedPrefix . 'license_key', null)
            ->andReturn('ABCD-1234');

        $result = $storage->getLicenseKey();
        $this->assertSame('ABCD-1234', $result);
    }

    public function testGetActivationUuidUsesCorrectKey(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('get_option')
            ->once()
            ->with($this->expectedPrefix . 'activation_uuid', null)
            ->andReturn('act-uuid-123');

        $result = $storage->getActivationUuid();
        $this->assertSame('act-uuid-123', $result);
    }

    public function testStoreSetsBothKeys(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('update_option')
            ->once()
            ->with($this->expectedPrefix . 'license_key', 'LIC-KEY', false);

        Functions\expect('update_option')
            ->once()
            ->with($this->expectedPrefix . 'activation_uuid', 'act-uuid', false);

        $storage->store('LIC-KEY', 'act-uuid');
    }

    public function testClearDeletesBothKeys(): void
    {
        $storage = new OptionStorage($this->uuid);

        Functions\expect('delete_option')
            ->once()
            ->with($this->expectedPrefix . 'license_key');

        Functions\expect('delete_option')
            ->once()
            ->with($this->expectedPrefix . 'activation_uuid');

        $storage->clear();
    }
}
