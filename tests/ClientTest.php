<?php

namespace Hotrush\ScrapoxyClient\Tests;

use Hotrush\ScrapoxyClient\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $client = new Client('apiUrl', 'password');
        $this->assertInstanceOf(Client::class, $client);
        $this->assertAttributeEquals('apiUrl', 'apiUrl', $client);
        $this->assertAttributeEquals('password', 'password', $client);
        $this->assertAttributeInstanceOf('React\EventLoop\LoopInterface', 'loop', $client);
        $this->assertAttributeEquals(null, 'client', $client);
    }
}