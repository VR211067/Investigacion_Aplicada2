<?php

declare(strict_types=1);

namespace WyriHaximus\FileDescriptors;

use Tivie\OS\Detector;

use function file_exists;

final class DevFd implements ListerInterface
{
    private const PATH = '/dev/fd';

    /**
     * @return iterable<string>
     */
    public function list(): iterable
    {
        yield from ScanDir::scan(self::PATH);
    }

    public function isSupported(): bool
    {
        return (new Detector())->isOSX() && file_exists(self::PATH);
    }
}
