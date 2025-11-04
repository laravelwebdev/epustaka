<?php

namespace App\Helpers;

use Exception;

class IpusnasDecryptor
{
    private static function decryptKey($userId, $bookId, $epustakaId, $borrowKey)
    {
        try {
            $formatted = $userId.$bookId.$epustakaId;

            $shaHex = hash('sha256', $formatted);
            $key = substr($shaHex, 7, 16);
            $decoded = base64_decode($borrowKey);
            $iv = substr($decoded, 0, 16);
            $ciphertext = substr($decoded, 16);
            $decrypted = openssl_decrypt($ciphertext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

            return $decrypted;

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function generatePasswordPDF($userId, $bookId, $epustakaId, $borrowKey)
    {
        try {
            $decryptedKey = self::decryptKey($userId, $bookId, $epustakaId, $borrowKey);
            $hash = hash('sha384', $decryptedKey);

            return substr($hash, 9, 64);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function generatePasswordZip($userId, $bookId, $epustakaId, $borrowKey)
    {
        try {
            $decryptedKey = self::decryptKey($userId, $bookId, $epustakaId, $borrowKey);
            $algorithm = 'sha512';
            $hash = hash($algorithm, $decryptedKey);

            return substr($hash, 59, 46);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
