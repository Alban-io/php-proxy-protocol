<?php

namespace SamIT\Proxy;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\RejectedPromise;
use React\Socket\Connection;
use React\SocketClient\TcpConnector;
use React\Stream\Stream;

/**
 * Class Connection
 * Implements a connection where the remote end thinks it is talking to another server.
 * @package SamIT\Proxy
 */
class Forwarder
{
    protected $connector;
    protected $loop;
    public function __construct(
        LoopInterface $loop,
        Resolver $resolver
    )
    {
        $this->loop = $loop;
        $this->connector = new TcpConnector($loop);
    }

    /**
     * Forwards a connection to the specified host / port using the proxy protocol.
     * @param ProxyConnection $connection
     * @param string $forwardAddress The host to forward to
     * @param int $forwardPort The port to forward to
     * @return Promise
     */
    public function forward(Connection $connection, $forwardAddress, $forwardPort)
    {
        list($sourceAddress, $sourcePort) = explode(':', stream_socket_get_name($connection->stream, true));
        list($targetAddress, $targetPort) = explode(':', stream_socket_get_name($connection->stream, false));
        $header = Header::createForward4($sourceAddress, $sourcePort, $targetAddress, $targetPort);
        return $this->connector->create($forwardAddress, $forwardPort)
            ->then(function(Stream $forwardedConnection) use (
                $connection, $header,
                $sourceAddress, $sourcePort,
                $targetAddress, $targetPort
            ) {
                    $forwardedConnection->getBuffer()->once('full-drain', function() use ($connection, $forwardedConnection) {
                        $connection->pipe($forwardedConnection);
                        $forwardedConnection->pipe($connection);
                        $forwardedConnection->emit('init', [$forwardedConnection]);
                    });
                    $forwardedConnection->write($header);
                return $forwardedConnection;
        });
    }

}