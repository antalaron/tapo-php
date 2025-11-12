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
final class AuthProtocol implements ProtocolInterface
{
    private $session;
    private $address;
    private $username;
    private $password;
    private $key;
    private $iv;
    private $seq;
    private $sig;
    private $cookie;

    public function __construct(string $address, string $username, string $password)
    {
        $this->address = $address;
        $this->username = $username;
        $this->password = $password;
    }

    private function sha1(string $data): string
    {
        return sha1($data, true);
    }

    private function sha256(string $data): string
    {
        return hash('sha256', $data, true);
    }

    private function calcAuthHash(string $username, string $password): string
    {
        return $this->sha256($this->sha1($username).$this->sha1($password));
    }

    private function requestRaw(string $path, string $data, ?array $params = null): string
    {
        $url = "http://{$this->address}/app/{$path}";

        if ($params) {
            $url .= '?'.http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_TIMEOUT, 2);
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

        return $body;
    }

    public function request(string $method, ?array $params = null)
    {
        if (!$this->key) {
            $this->initialize();
        }

        $payload = ['method' => $method];
        if ($params) {
            $payload['params'] = $params;
        }

        // Encrypt payload and execute call
        $encrypted = $this->encrypt(json_encode($payload));
        $result = $this->requestRaw('request', $encrypted, ['seq' => $this->seq]);

        // Unwrap and decrypt result
        $data = json_decode($this->decrypt($result), true);

        // Check error code and get result
        if (0 !== $data['error_code']) {
            $this->key = null;

            throw new RuntimeException(sprintf('Error code: %s', $data['error_code']));
        }

        return $data['result'] ?? null;
    }

    private function encrypt(string $data): string
    {
        ++$this->seq;
        $seq = pack('N', $this->seq);

        // Add PKCS#7 padding
        $padLength = 16 - (\strlen($data) % 16);
        $data .= str_repeat(\chr($padLength), $padLength);

        // Encrypt data with key
        $ciphertext = openssl_encrypt(
            $data,
            'AES-128-CBC',
            $this->key,
            \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING,
            $this->iv.$seq
        );

        // Signature
        $sig = $this->sha256($this->sig.$seq.$ciphertext);

        return $sig.$ciphertext;
    }

    private function decrypt(string $data): string
    {
        // Decrypt data with key
        $seq = pack('N', $this->seq);
        $ciphertext = substr($data, 32);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-128-CBC',
            $this->key,
            \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING,
            $this->iv.$seq
        );

        // Remove PKCS#7 padding
        $padLength = \ord($decrypted[\strlen($decrypted) - 1]);
        $decrypted = substr($decrypted, 0, -$padLength);

        return $decrypted;
    }

    public function initialize(): void
    {
        $localSeed = random_bytes(16);
        $response = $this->requestRaw('handshake1', $localSeed);

        $remoteSeed = substr($response, 0, 16);
        $serverHash = substr($response, 16);

        $authHash = null;
        $credentials = [
            [$this->username, $this->password],
            ['', ''],
            ['kasa@tp-link.net', 'kasaSetup'],
        ];

        foreach ($credentials as $creds) {
            $ah = $this->calcAuthHash($creds[0], $creds[1]);
            $localSeedAuthHash = $this->sha256($localSeed.$remoteSeed.$ah);

            if ($localSeedAuthHash === $serverHash) {
                $authHash = $ah;

                break;
            }
        }

        if (!$authHash) {
            throw new RuntimeException('Failed to authenticate');
        }

        $this->requestRaw('handshake2', $this->sha256($remoteSeed.$localSeed.$authHash));

        $this->key = substr($this->sha256('lsk'.$localSeed.$remoteSeed.$authHash), 0, 16);
        $ivseq = $this->sha256('iv'.$localSeed.$remoteSeed.$authHash);
        $this->iv = substr($ivseq, 0, 12);
        $this->seq = unpack('N', substr($ivseq, -4))[1];
        if (0x7FFFFFFF < $this->seq) {
            $this->seq -= 0x100000000;
        }

        $this->sig = substr($this->sha256('ldk'.$localSeed.$remoteSeed.$authHash), 0, 28);
    }
}
