<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 6/15/2018
 * Time: 12:14 AM
 */

namespace TimSDK\Foundation\ServiceProviders;

use GuzzleHttp\Client;
use Pimple\Container;

class HttpClientServiceProvider extends ServiceProvider
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $pimple['httpClient'] = function ($app) {
            return new Client($app['config']->get('http', []));
        };
    }
}