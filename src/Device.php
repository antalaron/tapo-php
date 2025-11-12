<?php

/*
 * This file is part of Tapo PHP.
 *
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Antalaron\Tapo;

use Antalaron\Tapo\Exception\RuntimeException;
use Antalaron\Tapo\Protocol\AuthProtocol;
use Antalaron\Tapo\Protocol\OldProtocol;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
final class Device
{
    /**
     * Mapping of device models to their supported features.
     */
    private const DEVICE_FEATURES = [
        'L510' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness'],
        'L520' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness'],
        'L610' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness'],
        'L530' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness', 'set_color', 'set_color_temperature', 'set_hue_saturation'],
        'L535' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness', 'set_color', 'set_color_temperature', 'set_hue_saturation'],
        'L630' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness', 'set_color', 'set_color_temperature', 'set_hue_saturation'],
        'L900' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness', 'set_color', 'set_color_temperature', 'set_hue_saturation'],
        'L920' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness', 'set_color', 'set_color_temperature', 'set_hue_saturation', 'set_lighting_effect'],
        'L930' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'set_brightness', 'set_color', 'set_color_temperature', 'set_hue_saturation', 'set_lighting_effect'],
        'P100' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session'],
        'P105' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session'],
        'P110' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'get_current_power', 'get_energy_data', 'get_energy_usage', 'get_power_data'],
        'P110M' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'get_current_power', 'get_energy_data', 'get_energy_usage', 'get_power_data'],
        'P115' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_device_usage', 'off', 'on', 'refresh_session', 'get_current_power', 'get_energy_data', 'get_energy_usage', 'get_power_data'],
        'P300' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_child_device_list', 'refresh_session'],
        'P306' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_child_device_list', 'refresh_session'],
        'P304M' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_child_device_list', 'refresh_session'],
        'P316M' => ['device_reboot', 'device_reset', 'get_device_info', 'get_device_info_json', 'get_child_device_list', 'refresh_session'],
    ];
    private $address;
    private $email;
    private $password;

    /** @var ProtocolInterface|null */
    private $protocol;
    private $preferredProtocol;
    private $kwargs;

    /** @var string|null */
    private $model;

    public function __construct(string $address, string $email, string $password, ?string $preferredProtocol = null, array $kwargs = [])
    {
        $this->address = $address;
        $this->email = $email;
        $this->password = $password;
        $this->preferredProtocol = $preferredProtocol;
        $this->kwargs = $kwargs;
    }

    private function initialize(): void
    {
        $protocolClasses = [
            'new' => AuthProtocol::class,
            'old' => OldProtocol::class,
        ];

        // Set preferred protocol if specified
        if ($this->preferredProtocol && isset($protocolClasses[$this->preferredProtocol])) {
            $protocolsToTry = [$protocolClasses[$this->preferredProtocol]];
        } else {
            $protocolsToTry = array_values($protocolClasses);
        }

        foreach ($protocolsToTry as $protocolClass) {
            if (!$this->protocol) {
                try {
                    $protocol = new $protocolClass($this->address, $this->email, $this->password);
                    $protocol->initialize();

                    $this->protocol = $protocol;
                } catch (RuntimeException $e) {
                    // Continue to next protocol
                }
            }
        }

        if (!$this->protocol) {
            throw new RuntimeException('Failed to initialize protocol');
        }

        $this->model = $this->protocol->request('get_device_info')['model'] ?? null;
    }

    public function getModel(): ?string
    {
        if (!$this->protocol) {
            $this->initialize();
        }

        return $this->model;
    }

    /**
     * Checks if a feature is supported by the current device model.
     *
     * @param string $feature The feature to check
     *
     * @return bool True if the feature is supported, false otherwise
     */
    public function supportsFeature(string $feature): bool
    {
        if (!$this->protocol) {
            $this->initialize();
        }

        if (!$this->model) {
            return false;
        }

        // Extract base model (e.g., "L510" from "L510E")
        foreach (array_keys(self::DEVICE_FEATURES) as $modelKey) {
            if (0 === strpos($this->model, $modelKey)) {
                return \in_array($feature, self::DEVICE_FEATURES[$modelKey], true);
            }
        }

        return false;
    }

    /**
     * Ensures that the feature is supported and throws an exception if not.
     *
     * @param string $feature The feature to check
     *
     * @throws RuntimeException If the feature is not supported
     */
    private function ensureFeatureSupported(string $feature): void
    {
        if (!$this->supportsFeature($feature)) {
            throw new RuntimeException(sprintf('Feature "%s" is not supported by device model "%s"', $feature, $this->model ?? 'unknown'));
        }
    }

    private function request(string $method, ?array $params = null)
    {
        if (!$this->protocol) {
            $this->initialize();
        }

        return $this->protocol->request($method, $params);
    }

    private function handshake(): void
    {
        if (!$this->protocol) {
            $this->initialize();
        }
    }

    private function login(): void
    {
        $this->handshake();
    }

    public function getDeviceInfo(): array
    {
        $this->ensureFeatureSupported('get_device_info');

        return $this->request('get_device_info');
    }

    public function getDeviceInfoJson(): string
    {
        $this->ensureFeatureSupported('get_device_info_json');

        return json_encode($this->request('get_device_info'));
    }

    public function deviceReboot(): void
    {
        $this->ensureFeatureSupported('device_reboot');
        $this->request('device_reboot');
    }

    public function deviceReset(): void
    {
        $this->ensureFeatureSupported('device_reset');
        $this->request('device_reset');
    }

    public function getChildDeviceList(): array
    {
        $this->ensureFeatureSupported('get_child_device_list');

        return $this->request('get_child_device_list');
    }

    public function getCurrentPower(): array
    {
        $this->ensureFeatureSupported('get_current_power');

        return $this->request('get_current_power');
    }

    public function getDeviceUsage(): array
    {
        $this->ensureFeatureSupported('get_device_usage');

        return $this->request('get_device_usage');
    }

    public function getEnergyData(): array
    {
        $this->ensureFeatureSupported('get_energy_data');

        return $this->request('get_energy_data');
    }

    public function getEnergyUsage(): array
    {
        $this->ensureFeatureSupported('get_energy_usage');

        return $this->request('get_energy_usage');
    }

    public function getPowerData(): array
    {
        $this->ensureFeatureSupported('get_power_data');

        return $this->request('get_power_data');
    }

    public function turnOff(): void
    {
        $this->ensureFeatureSupported('off');
        $this->setDeviceInfo(['device_on' => false]);
    }

    public function turnOn(): void
    {
        $this->ensureFeatureSupported('on');
        $this->setDeviceInfo(['device_on' => true]);
    }

    public function refreshSession(): void
    {
        $this->ensureFeatureSupported('refresh_session');
        if ($this->protocol) {
            $this->protocol->initialize();
        } else {
            $this->initialize();
        }
    }

    public function setBrightness(int $brightness): void
    {
        $this->ensureFeatureSupported('set_brightness');
        if (0 > $brightness || 100 < $brightness) {
            throw new RuntimeException('Brightness must be between 0 and 100');
        }
        $this->setDeviceInfo(['brightness' => $brightness]);
    }

    public function setColor(int $hue, int $saturation, ?int $brightness = null): void
    {
        $this->ensureFeatureSupported('set_color');
        if (0 > $hue || 360 < $hue) {
            throw new RuntimeException('Hue must be between 0 and 360');
        }
        if (0 > $saturation || 100 < $saturation) {
            throw new RuntimeException('Saturation must be between 0 and 100');
        }

        $params = [
            'hue' => $hue,
            'saturation' => $saturation,
            'color_temp' => 0,
        ];

        if (null !== $brightness) {
            if (0 > $brightness || 100 < $brightness) {
                throw new RuntimeException('Brightness must be between 0 and 100');
            }
            $params['brightness'] = $brightness;
        }

        $this->setDeviceInfo($params);
    }

    public function setColorTemperature(int $colorTemp, ?int $brightness = null): void
    {
        $this->ensureFeatureSupported('set_color_temperature');
        if (2500 > $colorTemp || 6500 < $colorTemp) {
            throw new RuntimeException('Color temperature must be between 2500 and 6500');
        }

        $params = ['color_temp' => $colorTemp];

        if (null !== $brightness) {
            if (0 > $brightness || 100 < $brightness) {
                throw new RuntimeException('Brightness must be between 0 and 100');
            }
            $params['brightness'] = $brightness;
        }

        $this->setDeviceInfo($params);
    }

    public function setHueSaturation(int $hue, int $saturation): void
    {
        $this->ensureFeatureSupported('set_hue_saturation');
        if (0 > $hue || 360 < $hue) {
            throw new RuntimeException('Hue must be between 0 and 360');
        }
        if (0 > $saturation || 100 < $saturation) {
            throw new RuntimeException('Saturation must be between 0 and 100');
        }

        $this->setDeviceInfo([
            'hue' => $hue,
            'saturation' => $saturation,
        ]);
    }

    public function setLightingEffect(string $effect, array $params = []): void
    {
        $this->ensureFeatureSupported('set_lighting_effect');
        $this->request('set_lighting_effect', array_merge(['id' => $effect], $params));
    }

    public function setDeviceInfo(array $params)
    {
        return $this->request('set_device_info', $params);
    }

    public function getCountDownRules()
    {
        return $this->request('get_countdown_rules');
    }

    public function getDeviceName(): string
    {
        $data = $this->getDeviceInfo();
        $encodedName = $data['nickname'];

        return base64_decode($encodedName, true);
    }

    public function switchWithDelay(bool $state, int $delay)
    {
        return $this->request('add_countdown_rule', [
            'delay' => $delay,
            'desired_states' => ['on' => $state],
            'enable' => true,
            'remain' => $delay,
        ]);
    }
}
