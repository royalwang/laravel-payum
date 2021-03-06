<?php

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use Recca0120\LaravelPayum\Storage\FilesystemStorage;

class FilesystemStorageTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testConstruct()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $filesystem = m::mock(Filesystem::class);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $storagePath = 'fooStoragePath';
        $exceptedPath = $storagePath.'/payum/';

        $app->shouldReceive('storagePath')->andReturn($storagePath);

        $filesystem->shouldReceive('isDirectory')->with($exceptedPath)->andReturn(false)
            ->shouldReceive('makeDirectory')->with($exceptedPath, 0777, true);

        $filesystemStorage = new FilesystemStorage($app, $filesystem, $exceptedModelClass);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertAttributeSame($exceptedPath, 'storageDir', $filesystemStorage);
    }
}
