<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

final class Updater
{
    public function updateFromZipUrl(string $zipUrl): string
    {
        if (!filter_var($zipUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid ZIP URL.');
        }

        $zipData = file_get_contents($zipUrl);
        if ($zipData === false) {
            throw new RuntimeException('Could not download update ZIP.');
        }

        return $this->updateFromZipData($zipData);
    }

    public function updateFromUploadedZip(array $uploadedFile): string
    {
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $zipData = file_get_contents($tmpName);
        if ($zipData === false) {
            throw new RuntimeException('Could not read uploaded ZIP file.');
        }

        return $this->updateFromZipData($zipData);
    }

    private function updateFromZipData(string $zipData): string
    {
        $tmpDir = storage_path('tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $token = (string) time() . '_' . bin2hex(random_bytes(4));
        $zipPath = $tmpDir . '/update_' . $token . '.zip';
        file_put_contents($zipPath, $zipData);

        $extractPath = $tmpDir . '/extract_' . $token;
        mkdir($extractPath, 0775, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open ZIP file.');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $sourceRoot = $this->detectRoot($extractPath);
        $this->copyRecursive($sourceRoot, dirname(__DIR__));
        $this->removeSensitiveInstallFiles(dirname(__DIR__));

        return 'Update installed successfully.';
    }

    private function detectRoot(string $extractPath): string
    {
        $entries = array_values(array_filter(scandir($extractPath) ?: [], fn ($v) => !in_array($v, ['.', '..'], true)));
        if (count($entries) === 1 && is_dir($extractPath . '/' . $entries[0])) {
            return $extractPath . '/' . $entries[0];
        }
        return $extractPath;
    }

    private function copyRecursive(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);

            if ($this->shouldSkip($relative)) {
                continue;
            }

            $targetPath = $destination . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0775, true);
                }
                continue;
            }

            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0775, true);
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    private function removeSensitiveInstallFiles(string $projectRoot): void
    {
        $filesToDelete = [
            'public/install.php',
            'README.md',
        ];

        foreach ($filesToDelete as $relativePath) {
            $path = $projectRoot . '/' . $relativePath;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function shouldSkip(string $relativePath): bool
    {
        $protected = [
            '.git',
            '.gitignore',
            'config/config.php',
            'storage',
        ];

        foreach ($protected as $prefix) {
            if ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
