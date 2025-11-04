<?php

namespace App\Helpers;

use Exception;
use ZipArchive;

class IpusnasDecryptor
{
    protected $tempDir;

    public function __construct($tempDir)
    {
        $this->tempDir = $tempDir;
    }

    /* ---------------------------
       1. Dekripsi kunci (borrowKey)
    ----------------------------*/
    public function decryptKey($userId, $bookId, $epustakaId, $borrowKey)
    {
        try {
            // Gabungkan ID seperti di Node.js
            $formatted = $userId.$bookId.$epustakaId;

            // Hash SHA256 → ambil HEX → slice(7,23)
            $shaHex = hash('sha256', $formatted); // hasil hex (64 karakter)
            $key = substr($shaHex, 7, 16); // ambil 16 karakter mulai dari index 7

            // Decode base64
            $decoded = base64_decode($borrowKey);
            if ($decoded === false) {
                throw new Exception('Invalid base64 borrowKey');
            }

            // Pisahkan IV dan ciphertext
            $iv = substr($decoded, 0, 16);
            $ciphertext = substr($decoded, 16);

            // Dekripsi AES-128-CBC
            $decrypted = openssl_decrypt($ciphertext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }

            return $decrypted;

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /* ---------------------------
       2. Generate password PDF
    ----------------------------*/
    public function generatePasswordPDF(string $decryptedKey)
    {
        try {
            $hash = hash('sha384', $decryptedKey);

            return substr($hash, 9, 64);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /* ---------------------------
       3. Generate password ZIP
    ----------------------------*/
    public function generatePasswordZip(string $decryptedKey, $useSha512 = false)
    {
        try {
            $algorithm = $useSha512 ? 'sha512' : 'sha1';
            $hash = hash($algorithm, $decryptedKey);

            return substr($hash, 59, 46);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /* ---------------------------
       4. Ekstraksi ZIP / MOCO
    ----------------------------*/
    public function extractZip(string $inputPath, string $passwordZip, $ipusnasBookId)
    {
        $bookId = md5($ipusnasBookId);
        try {
            $zip = new ZipArchive;
            if ($zip->open($inputPath) !== true) {
                return "Cannot open zip: {$inputPath}";
            }

            $entryName = $bookId.'.moco';
            $index = $zip->locateName($entryName);

            if ($index !== false) {
                $zip->setPassword($passwordZip);
                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    $zip->close();

                    return "Wrong password or cannot open entry: {$entryName}";
                }

                $outputPdfPath = $this->tempDir.DIRECTORY_SEPARATOR.$bookId.'.pdf';
                $out = fopen($outputPdfPath, 'w');
                while (! feof($stream)) {
                    fwrite($out, fread($stream, 1024 * 64));
                }
                fclose($out);
                fclose($stream);
                $zip->close();

                @unlink($inputPath);

                return $outputPdfPath;
            }

            // kalau tidak ada .moco → repack ke EPUB
            $newZipPath = $this->tempDir.DIRECTORY_SEPARATOR.$bookId.'.epub';
            $newZip = new ZipArchive;
            if ($newZip->open($newZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $zip->close();

                return "Cannot create epub: {$newZipPath}";
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $zip->setPassword($passwordZip);
                $stream = $zip->getStream($name);
                if ($stream !== false) {
                    $buff = stream_get_contents($stream);
                    fclose($stream);
                    $newZip->addFromString($name, $buff);
                }
            }

            $newZip->close();
            $zip->close();
            @unlink($inputPath);

            return $newZipPath;

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
