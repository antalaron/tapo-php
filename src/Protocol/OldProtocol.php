<?php

/*
 * This file is part of Tapo PHP.
 *
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Antalaron\Tapo\Protocol;

use Antalaron\Tapo\Exception\RuntimeException;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
final class OldProtocol implements ProtocolInterface
{
    private $address;
    private $username;
    private $password;
    private $terminalUuid;
    private $keypairFile;
    private $privateKey;
    private $publicKey;
    private $key;
    private $iv;
    private $token;
    private $cookie;

    public function __construct(string $address, string $username, string $password, ?string $keypairFile = null)
    {
        $this->address = $address;
        $this->username = $username;
        $this->password = $password;
        $this->terminalUuid = $this->generateUuid();
        $this->keypairFile = $keypairFile ?? sys_get_temp_dir().'/tapo.key';
        $this->createKeypair();
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    private function createKeypair(): void
    {
        if ($this->keypairFile && file_exists($this->keypairFile)) {
            $this->privateKey = openssl_pkey_get_private(file_get_contents($this->keypairFile));
        } else {
            $config = [
                'private_key_bits' => 1024,
                'private_key_type' => \OPENSSL_KEYTYPE_RSA,
            ];
            $this->privateKey = openssl_pkey_new($config);

            if ($this->keypairFile) {
                openssl_pkey_export_to_file($this->privateKey, $this->keypairFile);
            }
        }

        $keyDetails = openssl_pkey_get_details($this->privateKey);
        $this->publicKey = $keyDetails['key'];
    }

    private function requestRaw(string $method, ?array $params = null)
    {
        $url = "http://{$this->address}/app";
        if ($this->token) {
            $url .= "?token={$this->token}";
        }

        $payload = [
            'method' => $method,
            'requestTimeMils' => (int) round(microtime(true) * 1000),
            'terminalUUID' => $this->terminalUuid,
        ];

        if ($params) {
            $payload['params'] = $params;
        }

        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, \CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, \CURLOPT_HEADER, true);

        if ($this->cookie) {
            curl_setopt($ch, \CURLOPT_COOKIE, $this->cookie);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new RuntimeException(sprintf('Curl error: %s', curl_error($ch)));
        }

        $header_size = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        // Extract cookie
        if (preg_match('/Set-Cookie: ([^;]+)/', $header, $matches)) {
            $this->cookie = $matches[1];
        }

        curl_close($ch);

        $data = json_decode($body, true);

        if (0 !== $data['error_code']) {
            $this->key = null;

            throw new RuntimeException(sprintf('Error code: %s', $data['error_code']));
        }

        return $data['result'] ?? null;
    }

    public function request(string $method, ?array $params = null)
    {
        if (!$this->key) {
            $this->initialize();
        }

        $payload = [
            'method' => $method,
            'requestTimeMils' => (int) round(microtime(true) * 1000),
            'terminalUUID' => $this->terminalUuid,
        ];

        if ($params) {
            $payload['params'] = $params;
        }

        // Encrypt payload and execute call
        $encrypted = $this->encrypt(json_encode($payload));
        $result = $this->requestRaw('securePassthrough', ['request' => $encrypted]);

        // Unwrap and decrypt result
        $data = json_decode($this->decrypt($result['response']), true);

        if (0 !== $data['error_code']) {
            $this->key = null;

            throw new RuntimeException(sprintf('Error code: %s', $data['error_code']));
        }

        return $data['result'] ?? null;
    }

    private function encrypt(string $data): string
    {
        // Add PKCS#7 padding
        $padLength = 16 - (\strlen($data) % 16);
        $data .= str_repeat(\chr($padLength), $padLength);

        // Encrypt data with key
        $encrypted = openssl_encrypt(
            $data,
            'AES-128-CBC',
            $this->key,
            \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING,
            $this->iv
        );

        // Base64 encode
        return base64_encode($encrypted);
    }

    private function decrypt(string $data): string
    {
        // Base64 decode data
        $data = base64_decode($data, true);

        // Decrypt data with key
        $decrypted = openssl_decrypt(
            $data,
            'AES-128-CBC',
            $this->key,
            \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING,
            $this->iv
        );

        // Remove PKCS#7 padding
        $padLength = \ord($decrypted[\strlen($decrypted) - 1]);

        return substr($decrypted, 0, -$padLength);
    }

    public function initialize(): void
    {
        $this->key = null;
        $this->token = null;

        // Send public key and receive encrypted symmetric key
        $result = $this->requestRaw('handshake', ['key' => $this->publicKey]);
        $encrypted = base64_decode($result['key'], true);

        // Decrypt symmetric key
        $decrypted = '';
        openssl_private_decrypt($encrypted, $decrypted, $this->privateKey, \OPENSSL_PKCS1_PADDING);

        $this->key = substr($decrypted, 0, 16);
        $this->iv = substr($decrypted, 16);

        // Base64 encode password and hashed username
        $digest = sha1($this->username);
        $username = base64_encode($digest);
        $password = base64_encode($this->password);

        // Send login info and receive session token
        $result = $this->request('login_device', [
            'username' => $username,
            'password' => $password,
        ]);

        $this->token = $result['token'];
    }
}
