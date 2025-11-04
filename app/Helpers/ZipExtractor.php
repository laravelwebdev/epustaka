<?php

namespace App\Helpers;

use ZipArchive;

class ZipExtractor
{
    /**
     * Ekstrak file .mdrm
     * - Jika berisi .moco → PDF (streaming)
     * - Jika tidak → EPUB
     *
     * @param  string  $path  Path file .mdrm
     * @param  string  $password  Password zip
     * @return string|null Path file hasil (.pdf atau .epub)
     */
    public function extract(string $path, string $password = ''): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $tempRoot = storage_path('app/private/temp');
        $workDir = $tempRoot.'/'.$baseName;
        $destDir = storage_path('app/private/books');

        if (empty($password)) {
            $newPath = $destDir.'/'.basename($path);
            if (! @rename($path, $newPath)) {
                if (! @copy($path, $newPath)) {
                    return null;
                }
                @unlink($path);
            }

            return $this->relativeStoragePath($newPath);
        }

        $this->deleteDir($workDir);
        @mkdir($workDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $zip->setPassword($password);

        // Kasus .moco (hanya satu file di zip)
        if ($zip->numFiles === 1 && strcasecmp(pathinfo($zip->getNameIndex(0), PATHINFO_EXTENSION), 'moco') === 0) {
            $mocoName = $zip->getNameIndex(0);
            $pdfPath = $destDir.'/'.$baseName.'.pdf';

            // Ekstrak langsung ke file PDF (streaming, aman untuk file besar)
            $zip->extractTo($destDir, [$mocoName]);
            @rename($destDir.'/'.$mocoName, $pdfPath);

            $zip->close();
            $this->deleteDir($workDir);
            @unlink($path);

            return $this->relativeStoragePath($pdfPath);
        }

        // ZIP berisi banyak file → ekstrak ke folder temp
        $zip->extractTo($workDir);
        $zip->close();

        $epubPath = $destDir.'/'.$baseName.'.epub';
        $this->makeZip($workDir, $epubPath);

        $this->deleteDir($workDir);
        @unlink($path);

        return $this->relativeStoragePath($epubPath);
    }

    /** Hapus folder rekursif */
    protected function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }

        @rmdir($dir);
    }

    /** Buat zip dari folder */
    protected function makeZip(string $dir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $rootLen = strlen($dir) + 1;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $f) {
            if ($f->isFile()) {
                $localName = substr($f->getPathname(), $rootLen);
                $zip->addFile($f->getPathname(), $localName);
            }
        }

        $zip->close();
    }

    protected function relativeStoragePath(string $fullPath): string
    {
        $storagePath = realpath(storage_path('app/private')) ?: storage_path('app/private');

        return ltrim(str_replace($storagePath, '', $fullPath), DIRECTORY_SEPARATOR);
    }
}
