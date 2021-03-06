<?php

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Database\Eloquent\Model;
use Mockery as m;
use Recca0120\LaravelPayum\Storage\EloquentStorage;

class EloquentStorageTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testCreate()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */
        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $eloquentStorage = new EloquentStorage($app, $exceptedModelClass);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $app->shouldReceive('make')->with($exceptedModelClass)->andReturn($exceptedModelClass);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($exceptedModelClass, $eloquentStorage->create());
    }

    public function testDoUpdateModel()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $eloquentStorage = m::mock(new EloquentStorage($app, $exceptedModelClass))
            ->shouldAllowMockingProtectedMethods();
        $model = m::mock(Model::class);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $model->shouldReceive('save');

        $eloquentStorage->doUpdateModel($model);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */
    }

    public function testDoDeleteModel()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $eloquentStorage = m::mock(new EloquentStorage($app, $exceptedModelClass))
            ->shouldAllowMockingProtectedMethods();
        $model = m::mock(Model::class);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $model->shouldReceive('delete');

        $eloquentStorage->doDeleteModel($model);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */
    }

    public function testDoGetIdentity()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $eloquentStorage = m::mock(new EloquentStorage($app, $exceptedModelClass))
            ->shouldAllowMockingProtectedMethods();
        $model = m::mock(Model::class);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $exceptedKey = 'fooKey';
        $model->shouldReceive('getKey')->andReturn($exceptedKey);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($exceptedKey, $eloquentStorage->doGetIdentity($model)->getId());
    }

    public function testDoFind()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $eloquentStorage = m::mock(new EloquentStorage($app, $exceptedModelClass))
            ->shouldAllowMockingProtectedMethods();
        $model = m::mock(Model::class);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $exceptedId = 'fooId';

        $app->shouldReceive('make')->with($exceptedModelClass)->andReturn($model);

        $model->shouldReceive('find')->with($exceptedId)->andReturn($exceptedModelClass);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($exceptedModelClass, $eloquentStorage->doFind($exceptedId));
    }

    public function testFindBy()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $exceptedModelClass = 'fooModelClass';
        $app = m::mock(ApplicationContract::class.','.ArrayAccess::class);
        $eloquentStorage = m::mock(new EloquentStorage($app, $exceptedModelClass))
            ->shouldAllowMockingProtectedMethods();
        $model = m::mock(Model::class);
        $newQuery = m::mock(stdClass::class);
        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $exceptedCriteria = [
            'foo'  => 'bar',
            'fuzz' => 'buzz',
        ];

        $app->shouldReceive('make')->with($exceptedModelClass)->andReturn($model);

        $model->shouldReceive('newQuery')->andReturn($newQuery);

        $newQuery->shouldReceive('where')->times(count($exceptedCriteria))->andReturnSelf()
            ->shouldReceive('get->toArray')->andReturn($exceptedCriteria);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($exceptedCriteria, $eloquentStorage->findBy($exceptedCriteria));
    }
}
