<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ImageStorage
{
    private const EXTENSIONS = [
        'png' => 'png',
        'jpeg' => 'jpg',
        'jpg' => 'jpg',
        'gif' => 'gif',
        'webp' => 'webp',
    ];

    public static function isDataUri(string $value): bool
    {
        return self::decodeDataUri($value) !== null;
    }

    public static function isExternalUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * Lưu ảnh dạng data URI thành file trong disk public và trả về đường dẫn tương đối.
     */
    public static function storeDataUri(string $value, string $directory): string
    {
        $decoded = self::decodeDataUri($value);

        if ($decoded === null) {
            throw new RuntimeException('Dữ liệu ảnh không hợp lệ.');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$decoded['extension'];

        if (! Storage::disk('public')->put($path, $decoded['contents'])) {
            throw new RuntimeException("Không thể lưu ảnh tại [{$path}].");
        }

        return $path;
    }

    public static function storeUpload(UploadedFile $file, string $directory): string
    {
        $path = $file->store(trim($directory, '/'), 'public');

        if ($path === false) {
            throw new RuntimeException('Không thể lưu ảnh tải lên.');
        }

        return $path;
    }

    /**
     * @return array{extension: string, contents: string}|null
     */
    private static function decodeDataUri(string $value): ?array
    {
        if (! str_starts_with($value, 'data:image/')) {
            return null;
        }

        $separator = strpos($value, ';base64,');

        if ($separator === false) {
            return null;
        }

        $subtype = strtolower(substr($value, strlen('data:image/'), $separator - strlen('data:image/')));
        $extension = self::EXTENSIONS[$subtype] ?? null;
        $contents = base64_decode(substr($value, $separator + strlen(';base64,')), true);

        if ($extension === null || $contents === false || $contents === '') {
            return null;
        }

        return ['extension' => $extension, 'contents' => $contents];
    }
}
