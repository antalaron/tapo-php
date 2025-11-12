# Tapo PHP

A PHP library for controlling TP-Link Tapo smart devices.

[![CI](https://github.com/antalaron/tapo-php/workflows/CI/badge.svg)](https://github.com/antalaron/tapo-php/actions)

## Acknowledgements

The implementation of this library was inspired by:
- [almottier/TapoP100](https://github.com/almottier/TapoP100) - Original PHP implementation concept
- [mihai-dinculescu/tapo](https://github.com/mihai-dinculescu/tapo) - Device support reference

## Requirements

- PHP 7.1 or higher

## Installation

Install via Composer:

```bash
composer require antalaron/tapo
```

## Usage

### Basic Example

```php
<?php

require 'vendor/autoload.php';

use Antalaron\Tapo\Device;

// Create a device instance
$device = new Device('192.168.1.100', 'your-email@example.com', 'your-password');

// Get device information
echo 'Model: '.$device->getModel()."\n";
echo 'Name: '.$device->getDeviceName()."\n";

// Turn the device on
$device->turnOn();

// Turn the device off
$device->turnOff();
```

## Supported Devices

The library supports a wide range of Tapo devices:

### Smart Plugs
- **P100, P105** - Basic smart plugs (on/off control)
- **P110, P110M, P115** - Smart plugs with energy monitoring
- **P300, P306, P304M, P316M** - Power strips with multiple outlets

### Smart Bulbs
- **L510, L520, L610** - Dimmable white bulbs
- **L530, L535, L630** - Color bulbs with temperature control
- **L900, L920, L930** - LED light strips (L920, L930 support lighting effects)

## Available Methods

### Common Methods (All Devices)

```php
// Get device information
$info = $device->getDeviceInfo();
$jsonInfo = $device->getDeviceInfoJson();

// Device control
$device->getDeviceName();
$device->getDeviceUsage();
$device->refreshSession();

// Device management
$device->deviceReboot();
$device->deviceReset();
```

### Smart Plug Methods

```php
// Basic control
$device->turnOn();
$device->turnOff();

// Delayed switching (countdown timer)
$device->switchWithDelay(true, 60); // Turn on after 60 seconds

// Get countdown rules
$rules = $device->getCountDownRules();
```

### Energy Monitoring (P110, P110M, P115)

```php
// Get current power consumption
$power = $device->getCurrentPower();

// Get energy data
$energyData = $device->getEnergyData();
$energyUsage = $device->getEnergyUsage();
$powerData = $device->getPowerData();
```

### Smart Bulb Methods

```php
// Brightness control (all bulbs)
$device->setBrightness(50); // 0-100

// Color control (L530, L535, L630, L900, L920, L930)
$device->setColor(120, 80); // hue (0-360), saturation (0-100)
$device->setColor(120, 80, 75); // with brightness

$device->setHueSaturation(180, 90);

// Color temperature (L530, L535, L630, L900, L920, L930)
$device->setColorTemperature(4000); // 2500-6500K
$device->setColorTemperature(4000, 80); // with brightness

// Lighting effects (L920, L930)
$device->setLightingEffect('Aurora', ['brightness' => 80]);
```

### Power Strips (P300, P306, P304M, P316M)

```php
// Get child devices (individual outlets)
$children = $device->getChildDeviceList();
```

## Feature Detection

You can check if a device supports a specific feature:

```php
if ($device->supportsFeature('set_color')) {
    $device->setColor(240, 100);
}
```

## Protocol Support

The library automatically detects and uses the appropriate protocol for your device. You can also specify a preferred protocol:

```php
// Force new protocol
$device = new Device('192.168.1.100', 'email', 'password', 'new');

// Force old protocol
$device = new Device('192.168.1.100', 'email', 'password', 'old');
```

## Error Handling

The library throws `Antalaron\Tapo\Exception\RuntimeException` when errors occur:

```php
use Antalaron\Tapo\Exception\RuntimeException;

try {
    $device->turnOn();
} catch (RuntimeException $e) {
    echo sprintf('Error: %s', $e->getMessage());
}
```

## License

This library is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
