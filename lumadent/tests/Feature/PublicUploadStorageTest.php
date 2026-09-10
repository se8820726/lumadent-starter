<?php

namespace Tests\Feature;

use Tests\TestCase;

final class PublicUploadStorageTest extends TestCase
{
    public function test_public_disk_writes_directly_to_the_public_uploads_directory(): void
    {
        self::assertSame(public_path('uploads'), config('filesystems.disks.public.root'));
        self::assertSame(rtrim((string) config('app.url'), '/').'/uploads', config('filesystems.disks.public.url'));
        self::assertSame([], config('filesystems.links'));
    }
}
