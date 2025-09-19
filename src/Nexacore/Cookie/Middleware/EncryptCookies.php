<?php
// src/Nexacore/Http/Middleware/EncryptCookies.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Encrypt Cookies Middleware
 *
 * Encrypts and decrypts cookies for enhanced security.
 *
 * @package Nexacore\Http\Middleware
 */
class EncryptCookies implements MiddlewareInterface
{
    /**
     * The encryption key.
     *
     * @var string
     */
    protected $key;

    /**
     * The cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Process the request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->key = $request->getAttribute('config')['app']['key'] ?? '';

        $request = $this->decryptCookies($request);
        $response = $handler->handle($request);
        
        return $this->encryptCookies($response);
    }

    /**
     * Decrypt incoming cookies.
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    protected function decryptCookies(ServerRequestInterface $request): ServerRequestInterface
    {
        $cookies = $request->getCookieParams();

        foreach ($cookies as $name => $value) {
            if ($this->shouldDecrypt($name)) {
                $cookies[$name] = $this->decrypt($value);
            }
        }

        return $request->withCookieParams($cookies);
    }

    /**
     * Encrypt outgoing cookies.
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function encryptCookies(ResponseInterface $response): ResponseInterface
    {
        $cookies = $response->getHeader('Set-Cookie');

        foreach ($cookies as $index => $cookie) {
            if (preg_match('/^([^=]+)=([^;]+)/', $cookie, $matches)) {
                $name = $matches[1];
                $value = $matches[2];

                if ($this->shouldEncrypt($name)) {
                    $encryptedValue = $this->encrypt($value);
                    $cookies[$index] = str_replace($value, $encryptedValue, $cookie);
                }
            }
        }

        return $response->withHeader('Set-Cookie', $cookies);
    }

    /**
     * Determine if the cookie should be decrypted.
     *
     * @param string $name
     * @return bool
     */
    protected function shouldDecrypt(string $name): bool
    {
        return !in_array($name, $this->except);
    }

    /**
     * Determine if the cookie should be encrypted.
     *
     * @param string $name
     * @return bool
     */
    protected function shouldEncrypt(string $name): bool
    {
        return !in_array($name, $this->except);
    }

    /**
     * Encrypt a value.
     *
     * @param string $value
     * @return string
     */
    protected function encrypt(string $value): string
    {
        if (empty($this->key)) {
            return $value;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $this->key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value.
     *
     * @param string $value
     * @return string
     */
    protected function decrypt(string $value): string
    {
        if (empty($this->key)) {
            return $value;
        }

        $decoded = base64_decode($value);
        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, 0, $iv);
    }
}