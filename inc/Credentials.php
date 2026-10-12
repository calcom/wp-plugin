<?php

namespace CalCom;

defined('ABSPATH') || exit;

/**
 * Credential storage/access for the Cal.com API key.
 * Encrypts at rest with AES-256-CBC using WordPress auth salts.
 */
class Credentials
{
    const OPTION_NAME = 'calcom_api_key';

    public function save($api_key)
    {
        $encrypted = $this->encrypt($api_key);
        if ($encrypted === false) {
            return false;
        }
        return $this->update_option(self::OPTION_NAME, $encrypted);
    }

    public function get()
    {
        $encrypted = $this->get_option(self::OPTION_NAME);
        if (!$encrypted || !is_string($encrypted)) {
            return null;
        }

        $decrypted = $this->decrypt($encrypted);
        if ($decrypted === false) {
            return null;
        }

        return $decrypted;
    }

    public function delete()
    {
        return $this->delete_option(self::OPTION_NAME);
    }

    public function exists()
    {
        $key = $this->get();
        return $key !== null && strlen($key) > 0;
    }

    public function get_api()
    {
        return new Api($this->get());
    }

    private function encrypt($plaintext)
    {
        if (!function_exists('openssl_encrypt')) {
            return false;
        }

        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return false;
        }

        return base64_encode($iv . $ciphertext);
    }

    private function decrypt($encrypted)
    {
        if (!function_exists('openssl_decrypt')) {
            return false;
        }

        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < 32) {
            return false;
        }

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        $key = $this->get_encryption_key();
        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    private function get_encryption_key()
    {
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : 'default-auth-key';
        $secure_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'default-secure-key';
        return hash('sha256', $auth_key . $secure_key, true);
    }

    private function update_option($key, $value)
    {
        if (is_multisite()) {
            return update_site_option($key, $value);
        }
        return update_option($key, $value, false);
    }

    private function get_option($key)
    {
        if (is_multisite()) {
            return get_site_option($key, null);
        }
        return get_option($key, null);
    }

    private function delete_option($key)
    {
        if (is_multisite()) {
            return delete_site_option($key);
        }
        return delete_option($key);
    }
}
