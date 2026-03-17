<?php

namespace Openplain\FlowField\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Openplain\FlowField\FlowFieldServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FlowFieldServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('flowfield.cache.store', 'array');
        $app['config']->set('flowfield.cache.ttl', 3600);
        $app['config']->set('flowfield.tag_based', false);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('test_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('test_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('test_customers');
            $table->string('type')->default('invoice');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
