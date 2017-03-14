[![Build Status](https://travis-ci.org/hotrush/scrapoxy-react-client.svg?branch=master)](https://travis-ci.org/hotrush/scrapoxy-react-client)
[![Coverage Status](https://coveralls.io/repos/github/hotrush/scrapoxy-react-client/badge.svg?branch=master)](https://coveralls.io/github/hotrush/scrapoxy-react-client?branch=master)
[![Packagist Downloads](https://img.shields.io/packagist/dt/hotrush/scrapoxy-react-client.svg)](https://packagist.org/packages/hotrush/scrapoxy-react-client)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/fb9ada42-1ed7-456e-aef0-d475a9a7227a/mini.png)](https://insight.sensiolabs.com/projects/fb9ada42-1ed7-456e-aef0-d475a9a7227a)

# Scrapoxy-React-Client
Async client for [Scrapoxy](https://github.com/fabienvauchelles/scrapoxy) and [ReactPHP](https://github.com/reactphp/react).

# Installation

```
composer require hotrush/scrapoxy-react-client
```

# Usage

```
use Hotrush\ScrapoxyClient\Client;
use React\EventLoop\Factory as LoopFactory;

$loop = LoopFactory::create();
$client = new Client('http://scrapoxy-host.com/api/', 'password', $loop);
$loop->run();
```

### Get scaling

```
$client->getScaling()
    ->then(
        function($scaling) {
            var_dump($scaling);
        },
        function($exception) {
            echo (string) $exception;
        }
    );
```

Will output your current scaling info:

```
[
    "min" => 0,
    "required" => 2,
    "max" => 5,
]
```

### Scaling up and down

```
$client->upScale()->then(...);
$client->downScale()->then(...);
```

Scaling up will update required instances number to maximum. Scaling down will update it to minimum.

### Custom scaling

You can define your custom instances number:

```
$client->scale([
    'min' => 0,
    'max' => 10,
    'required' => 5,
]);
```

### Get and update config

```
$client->getConfig()->then(...);
$client->updateConfig([
    'any_key' => 'any_value',
])->then(...);
```

### Get instances

```
$client->getInstances()->then(...);
```

### Stop instance by name

```
$client->stopInstance($name)->then(...);
```

Will throw `NotFoundException` if instance name not found.

# Contribution

You are welcome to create any pull requests or write some tests!
