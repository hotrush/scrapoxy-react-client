<?php

namespace Hotrush\ScrapoxyClient;

use Hotrush\ScrapoxyClient\Exception\ApiErrorException;
use Hotrush\ScrapoxyClient\Exception\NotFoundException;
use Hotrush\ScrapoxyClient\Exception\UnauthorizedException;
use React\Dns\Resolver\Factory;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\HttpClient\Factory as HttpFactory;
use React\HttpClient\Response;
use React\Promise\Deferred;

class Client
{
    /**
     * @var \React\EventLoop\ExtEventLoop|\React\EventLoop\LibEventLoop|\React\EventLoop\LibEvLoop|\React\EventLoop\StreamSelectLoop
     */
    private $loop;

    /**
     * @var \React\HttpClient\Client
     */
    private $client;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $password;

    /**
     * Client constructor.
     *
     * @param $apiUrl
     * @param $password
     * @param LoopInterface|null $loop
     */
    public function __construct($apiUrl, $password, LoopInterface $loop = null)
    {
        $this->apiUrl = $apiUrl;
        $this->password = $password;
        $this->loop = $loop ?: LoopFactory::create();
    }

    /**
     * @return \React\EventLoop\ExtEventLoop|LoopFactory|\React\EventLoop\LibEventLoop|\React\EventLoop\LibEvLoop|\React\EventLoop\StreamSelectLoop
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * @return \React\HttpClient\Client
     */
    private function getClient()
    {
        if (!$this->client) {
            $dnsResolverFactory = new Factory();
            $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);
            $factory = new HttpFactory();
            $this->client = $factory->create($this->loop, $dnsResolver);
        }

        return $this->client;
    }

    /**
     * @param $method
     * @param $endpoint
     * @param array $payload
     *
     * @return \React\Promise\Promise|\React\Promise\PromiseInterface
     */
    private function sendRequest($method, $endpoint, array $payload = [])
    {
        $deferred = new Deferred();

        $payload = $payload ? json_encode($payload) : null;

        $request = $this->getClient()->request($method, $this->apiUrl.$endpoint, [
            'Authorization'  => base64_encode($this->password),
            'Content-Type'   => 'application/json',
            'Content-Length' => $payload ? strlen($payload) : 0,
        ]);

        $request->on('response', function (Response $response) use ($deferred) {
            $responseContent = null;

            $response->on('data', function ($data) use ($response, &$responseContent) {
                $responseContent = $response->getCode() >= 400 ? $data : $this->decodeResponse($data);
            });

            $response->on('end', function () use ($response, $deferred, &$responseContent) {
                if ($response->getCode() >= 400) {
                    switch ($response->getCode()) {
                        case 403:
                            $deferred->reject(new UnauthorizedException($responseContent));
                            break;
                        case 404:
                            $deferred->reject(new NotFoundException($responseContent));
                            break;
                        default:
                            $deferred->reject(new ApiErrorException($responseContent));
                            break;
                    }
                } else {
                    $deferred->resolve($responseContent);
                }
            });

            $response->on('error', function ($reason) use ($deferred) {
                $deferred->reject($reason);
            });

        });

        $request->on('error', function ($reason) use ($deferred) {
            $deferred->reject($reason);
        });

        $request->end($payload ?: null);

        return $deferred->promise();
    }

    /**
     * @param $response
     *
     * @throws ApiErrorException
     *
     * @return mixed
     */
    private function decodeResponse($response)
    {
        $data = json_decode((string) $response, true);

        if ($data === false) {
            throw new ApiErrorException('Can not decode response.');
        }

        return $data;
    }

    /**
     * Get scaling info.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#get-the-scaling
     *
     * @return \React\Promise\PromiseInterface|static
     */
    public function getScaling()
    {
        return $this->sendRequest('GET', 'scaling');
    }

    /**
     * Update required instances number to maximum.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#update-the-scaling
     *
     * @return \React\Promise\PromiseInterface
     */
    public function upScale()
    {
        return $this->getScaling()
            ->then(
                function ($scaling) {
                    return $this->sendRequest('PATCH', 'scaling', [
                        'min'      => $scaling['min'],
                        'required' => $scaling['max'],
                        'max'      => $scaling['max'],
                    ]);
                }
            );
    }

    /**
     * Update required instances number to minimum.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#update-the-scaling
     *
     * @return \React\Promise\PromiseInterface
     */
    public function downScale()
    {
        return $this->getScaling()
            ->then(
                function ($scaling) {
                    return $this->sendRequest('PATCH', 'scaling', [
                        'min'      => $scaling['min'],
                        'required' => $scaling['min'],
                        'max'      => $scaling['max'],
                    ]);
                }
            );
    }

    /**
     * Update scaling to any custom value
     * Payload must be an array like:
     * [
     *     "min" => 1,
     *     "max" => 5,
     *     "required" => 3,
     * ].
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#update-the-scaling
     *
     * @param array $scaling
     *
     * @return \React\Promise\PromiseInterface|static
     */
    public function scale(array $scaling)
    {
        return $this->sendRequest('PATCH', 'scaling', $scaling);
    }

    /**
     * Get scrapoxy config.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#get-the-configuration
     *
     * @return \React\Promise\PromiseInterface|static
     */
    public function getConfig()
    {
        return $this->sendRequest('GET', 'config');
    }

    /**
     * Update scrapoxy config.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#update-the-configuration
     *
     * @param array $config
     *
     * @return \React\Promise\PromiseInterface|static
     */
    public function updateConfig(array $config = [])
    {
        return $this->sendRequest('PATCH', 'config', $config);
    }

    /**
     * Get all instances.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#get-all-instances
     *
     * @return \React\Promise\PromiseInterface|static
     */
    public function getInstances()
    {
        return $this->sendRequest('GET', 'instances');
    }

    /**
     * Stop an instance by name.
     *
     * @doc http://scrapoxy.readthedocs.io/en/master/advanced/api/index.html#stop-an-instance
     *
     * @param $name
     *
     * @return \React\Promise\PromiseInterface
     */
    public function stopInstance($name)
    {
        return $this->sendRequest('POST', 'instances/stop', [
            'name' => $name,
        ]);
    }
}
