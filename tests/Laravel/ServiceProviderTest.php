<?php

namespace Nava\MyInvois\Tests\Laravel;

use Illuminate\Support\Facades\Config;
use Nava\MyInvois\Contracts\MyInvoisClientFactory;
use Nava\MyInvois\Laravel\MyInvoisServiceProvider;
use Nava\MyInvois\MyInvoisClient;
use Nava\MyInvois\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MyInvoisServiceProvider::class];
    }

    /** @test */
    public function it_registers_the_client_factory()
    {
        $this->assertInstanceOf(
            MyInvoisClientFactory::class,
            $this->app->make(MyInvoisClientFactory::class)
        );
    }

    /** @test */
    public function it_registers_the_client_instance()
    {
        $this->assertInstanceOf(
            MyInvoisClient::class,
            $this->app->make(MyInvoisClient::class)
        );
    }

    /** @test */
    public function it_publishes_the_config_file()
    {
        $this->artisan('vendor:publish', ['--provider' => MyInvoisServiceProvider::class]);

        $this->assertFileExists(config_path('myinvois.php'));
        $this->assertEquals(
            Config::get('myinvois.client_id'),
            'your_client_id'
        );
    }
}
