<?php

use App\Models\Image;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

test('stored image URLs use the configured public storage disk', function () {
    config()->set('filesystems.disks.public.driver', 's3');

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('url')
        ->once()
        ->with('products/item.png')
        ->andReturn('https://images.example.com/products/item.png');

    Storage::shouldReceive('disk')
        ->once()
        ->with('public')
        ->andReturn($disk);

    $image = new Image(['path' => 'products/item.png']);

    expect($image->url)->toBe('https://images.example.com/products/item.png');
});

test('stored image URLs remain relative for the local public disk', function () {
    config()->set('filesystems.disks.public.driver', 'local');

    $image = new Image(['path' => 'products/item.png']);

    expect($image->url)->toBe('/storage/products/item.png');
});
