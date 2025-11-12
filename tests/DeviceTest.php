<?php

/*
 * This file is part of Tapo PHP.
 *
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Antalaron\Tapo\Tests;

use Antalaron\Tapo\Device;
use PHPUnit\Framework\TestCase;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
final class DeviceTest extends TestCase
{
    public function testConstructor(): void
    {
        $device = new Device('192.168.1.100', 'test@example.com', 'password');
        $this->assertInstanceOf(Device::class, $device);
    }

    public function testConstructorWithPreferredProtocolNew(): void
    {
        $device = new Device('192.168.1.100', 'test@example.com', 'password', 'new');
        $this->assertInstanceOf(Device::class, $device);
    }

    public function testConstructorWithPreferredProtocolOld(): void
    {
        $device = new Device('192.168.1.100', 'test@example.com', 'password', 'old');
        $this->assertInstanceOf(Device::class, $device);
    }

    public function testConstructorWithKwargs(): void
    {
        $device = new Device('192.168.1.100', 'test@example.com', 'password', null, ['timeout' => 10]);
        $this->assertInstanceOf(Device::class, $device);
    }

    /**
     * Test that feature support checking works for known device models.
     */
    public function testSupportsFeatureForKnownModels(): void
    {
        // We can't test this without a real device connection,
        // but we can verify the method exists
        $device = new Device('192.168.1.100', 'test@example.com', 'password');
        $this->assertTrue(method_exists($device, 'supportsFeature'));
    }

    /**
     * Test that getModel method exists.
     */
    public function testGetModelMethodExists(): void
    {
        $device = new Device('192.168.1.100', 'test@example.com', 'password');
        $this->assertTrue(method_exists($device, 'getModel'));
    }

    /**
     * Test all public methods exist.
     */
    public function testPublicMethodsExist(): void
    {
        $device = new Device('192.168.1.100', 'test@example.com', 'password');

        $expectedMethods = [
            'getModel',
            'supportsFeature',
            'getDeviceInfo',
            'getDeviceInfoJson',
            'deviceReboot',
            'deviceReset',
            'getChildDeviceList',
            'getCurrentPower',
            'getDeviceUsage',
            'getEnergyData',
            'getEnergyUsage',
            'getPowerData',
            'turnOff',
            'turnOn',
            'refreshSession',
            'setBrightness',
            'setColor',
            'setColorTemperature',
            'setHueSaturation',
            'setLightingEffect',
            'setDeviceInfo',
            'getCountDownRules',
            'getDeviceName',
            'switchWithDelay',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                method_exists($device, $method),
                sprintf('Method %s does not exist', $method)
            );
        }
    }
}
