<?php
namespace Experimental\App\Foundation\Http\Websocket_alpha;
// websocket_server.php
// Run: php websocket_server.php

class WebSocketServer_alpha
{
    private $socket;
    private $clients = [];
    private $isRunning = false;

    public function __construct(
        protected $host = '0.0.0.0',
        protected $port = 8080
    ) {
        // Create socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$this->socket) {
            die("Could not create socket: " . socket_strerror(socket_last_error()) . "\n");
        }

        // Set socket options
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 5, 'usec' => 0));

        echo "Socket created successfully\n";
    }

    public function start()
    {
        // Bind socket
        if (!socket_bind($this->socket, $this->host, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));
            die("Could not bind socket to {$this->host}:{$this->port}: $error\n");
        }

        echo "Socket bound to {$this->host}:{$this->port}\n";

        // Start listening
        if (!socket_listen($this->socket, 10)) {
            die("Could not listen on socket: " . socket_strerror(socket_last_error($this->socket)) . "\n");
        }

        echo "WebSocket server listening on ws://{$this->host}:{$this->port}\n";
        echo "Press Ctrl+C to stop\n\n";

        $this->isRunning = true;
        $this->run();
    }

    private function run()
    {
        while ($this->isRunning) {
            // Prepare array of sockets to monitor
            $read = [$this->socket];
            $write = null;
            $except = null;

            // Add all client sockets
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }

            // Wait for socket activity
            $changed = $read;
            $sockRead = @socket_select($changed, $write, $except, 1);
            if ($sockRead === false) {
                if (socket_last_error() === SOCKET_EINTR) {
                    break;
                }
            } else if ($sockRead > 0) {
                if (socket_last_error() === SOCKET_EINTR) {
                    $this->isRunning = false;
                }

                // Check for new connections
                if (in_array($this->socket, $changed)) {
                    $this->acceptConnection();
                }

                // Check for data from clients
                foreach ($changed as $socket) {
                    if ($socket != $this->socket) {
                        $this->handleClient($socket);
                    }
                }
            } 

            // Clean up disconnected clients
            $this->cleanupClients();
        }
    }

    private function acceptConnection()
    {
        $clientSocket = socket_accept($this->socket);

        if ($clientSocket !== false) {
            // Read handshake
            $request = socket_read($clientSocket, 2048);

            if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches)) {
                $key = $matches[1];

                // Create handshake response
                $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

                $response = "HTTP/1.1 101 Switching Protocols\r\n";
                $response .= "Upgrade: websocket\r\n";
                $response .= "Connection: Upgrade\r\n";
                $response .= "Sec-WebSocket-Accept: $acceptKey\r\n";
                $response .= "\r\n";

                socket_write($clientSocket, $response, strlen($response));

                // Add client
                $clientId = uniqid();
                $this->clients[$clientId] = [
                    'socket' => $clientSocket,
                    'id' => $clientId,
                    'connected' => time()
                ];

                echo "New client connected: $clientId\n";

                $this->send($clientSocket, json_encode([
                    'type' => 'welcome',
                    'from' => 'server',
                    'clientId' => $clientId,
                    'message' => 'Connected to server',
                    'timestamp' => time()
                ]));
            } else {
                socket_close($clientSocket);
            }
        }
    }

    private function handleClient($socket)
    {
        $data = @socket_read($socket, 2048, PHP_BINARY_READ);

        if ($data === false || strlen($data) < 2) {
            // Client disconnected
            $this->removeClient($socket);
            return;
        }

        if (strlen($data) > 0) {
            // Decode WebSocket frame
            $decoded = $this->decodeFrame($data);

            if ($decoded !== false) {
                // Find client ID
                $clientId = $this->findClientIdBySocket($socket);

                if ($clientId !== null) {
                    echo "Message from $clientId: $decoded\n";

                    // Echo back... but I don't want to
                    // $this->send($socket, "$decoded");

                    // Broadcast to all clients
                    $this->broadcast(json_encode([
                        'type' => 'message',
                        'from' => $clientId,
                        'message' => $decoded,
                        'timestamp' => time()
                    ]), $clientId);
                }
            }
        }
    }

    private function decodeFrame($data)
    {
        if (strlen($data) < 2) {
            return false;
        }

        $bytes = str_split($data);
        $secondByte = ord($bytes[1]);
        $masked = ($secondByte & 0x80) !== 0;
        $dataLength = $secondByte & 0x7F;

        $offset = 2;

        if ($dataLength === 126) {
            if (strlen($data) < $offset + 2) {
                return false;
            }
            $dataLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($dataLength === 127) {
            if (strlen($data) < $offset + 8) {
                return false;
            }
            $dataLength = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }

        if ($masked) {
            if (strlen($data) < $offset + 4) {
                return false;
            }
            $mask = substr($data, $offset, 4);
            $offset += 4;

            if (strlen($data) < $offset + $dataLength) {
                return false;
            }

            $message = '';
            $payload = substr($data, $offset, $dataLength);

            for ($i = 0; $i < $dataLength; $i++) {
                $message .= $payload[$i] ^ $mask[$i % 4];
            }

            return $message;
        }

        return false;
    }

    private function send($socket, $message)
    {
        $frame = $this->encodeFrame($message);
        socket_write($socket, $frame, strlen($frame));
    }

    private function encodeFrame($message)
    {
        $frame = [];
        $frame[0] = 0x81; // FIN + Text frame

        $len = strlen($message);

        if ($len <= 125) {
            $frame[1] = $len;
        } elseif ($len <= 65535) {
            $frame[1] = 126;
            $frame[2] = ($len >> 8) & 0xFF;
            $frame[3] = $len & 0xFF;
        } else {
            $frame[1] = 127;
            for ($i = 0; $i < 8; $i++) {
                $frame[2 + $i] = ($len >> (8 * (7 - $i))) & 0xFF;
            }
        }

        $frameHeader = '';
        foreach ($frame as $byte) {
            $frameHeader .= chr($byte);
        }

        return $frameHeader . $message;
    }

    private function broadcast($message, $excludeClientId = null)
    {
        $frame = $this->encodeFrame($message);

        foreach ($this->clients as $clientId => $client) {
            if ($clientId !== $excludeClientId) {
                socket_write($client['socket'], $frame, strlen($frame));
            }
        }
    }

    private function findClientIdBySocket($socket)
    {
        foreach ($this->clients as $clientId => $client) {
            if ($client['socket'] === $socket) {
                return $clientId;
            }
        }
        return null;
    }

    private function removeClient($socket)
    {
        $clientId = $this->findClientIdBySocket($socket);

        if ($clientId !== null) {
            socket_close($socket);
            unset($this->clients[$clientId]);
            echo "Client disconnected: $clientId\n";

            // Notify other clients
            $this->broadcast(json_encode([
                'type' => 'client_left',
                'clientId' => $clientId,
                'timestamp' => time()
            ]));
        }
    }

    private function cleanupClients()
    {
        $now = time();
        $toRemove = [];

        foreach ($this->clients as $clientId => $client) {
            // Remove clients that haven't sent data in 60 seconds
            if ($now - $client['connected'] > 60) {
                $toRemove[] = $client['socket'];
            }
        }

        foreach ($toRemove as $socket) {
            $this->removeClient($socket);
        }
    }

    public function stop()
    {
        $this->isRunning = false;

        foreach ($this->clients as $client) {
            socket_close($client['socket']);
        }

        socket_close($this->socket);
        echo "\nServer stopped\n";
    }
}

// // Start the server
// try {
//     $server = new WebSocketServer('0.0.0.0', 8080);

//     // Handle Ctrl+C
//     if (function_exists('pcntl_signal')) {

//         declare(ticks=1);
//         pcntl_signal(SIGINT, function () use ($server) {
//             echo "\nShutting down...\n";
//             $server->stop();
//             exit(0);
//         });
//     }

//     $server->start();
// } catch (Exception $e) {
//     echo "Error: " . $e->getMessage() . "\n";
//     exit(1);
// }
