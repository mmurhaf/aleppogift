<?php

class ProductImageResolver
{
    public static function resolveAsLocalPath(string $imagePath, array $basePaths = [], ?string $fallbackPath = null): ?string
    {
        $candidates = [];
        $trimmedImagePath = str_replace('\\', '/', trim($imagePath));
        while (str_starts_with($trimmedImagePath, '../')) {
            $trimmedImagePath = substr($trimmedImagePath, 3);
        }
        $trimmedImagePath = ltrim($trimmedImagePath, '/');

        if ($trimmedImagePath !== '') {
            if (self::isAbsolutePath($trimmedImagePath) && is_file($trimmedImagePath) && is_readable($trimmedImagePath)) {
                return $trimmedImagePath;
            }

            foreach ($basePaths as $basePath) {
                $basePath = rtrim((string)$basePath, '/\\');
                if ($basePath === '') {
                    continue;
                }

                $candidates[] = $basePath . DIRECTORY_SEPARATOR . ltrim($trimmedImagePath, '/\\');
                $candidates[] = $basePath . DIRECTORY_SEPARATOR . basename($trimmedImagePath);
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        if (!empty($fallbackPath) && is_file($fallbackPath) && is_readable($fallbackPath)) {
            return $fallbackPath;
        }

        return null;
    }

    public static function resolveAsDataUri(string $imagePath, array $basePaths = [], ?string $fallbackPath = null): ?string
    {
        $localPath = self::resolveAsLocalPath($imagePath, $basePaths, $fallbackPath);
        if ($localPath === null) {
            return null;
        }

        $imageData = @file_get_contents($localPath);
        if ($imageData === false) {
            return null;
        }

        $mimeType = function_exists('mime_content_type') ? @mime_content_type($localPath) : false;
        if (!$mimeType) {
            $mimeType = 'image/jpeg';
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return (bool)preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) || str_starts_with($path, '/') || str_starts_with($path, '\\\\');
    }
}
