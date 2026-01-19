<?php
namespace Experimental\App\Foundation\Http\Websocket_v2;

/**
 * WebSocket opcodes for frame control.
 */
enum Opcode: int
{
    case CONTINUATION = 0x0;
    case TEXT = 0x1;
    case BINARY = 0x2;
    case CLOSE = 0x8;
    case PING = 0x9;
    case PONG = 0xA;
}

/**
 * WebSocket close codes for connection termination.
 */
enum CloseCode: int
{
    const NORMAL = 1000;
    const GOING_AWAY = 1001;
    const PROTOCOL_ERROR = 1002;
    const UNSUPPORTED_DATA = 1003;
    const NO_STATUS = 1005;
    const ABNORMAL_CLOSE = 1006;
    const INVALID_PAYLOAD = 1007;
    const POLICY_VIOLATION = 1008;
    const MESSAGE_TOO_BIG = 1009;
    const INTERNAL_ERROR = 1011;
}


class WebSocketServer_v2
{
    private $socket;
    private $clients = [];
    private $isRunning = false;
    private $eventHandlers = [];
    private $clientData = [];

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

    /**
     * Register an event handler
     * 
     * @param string $event Event name (connection, message, disconnect, custom_event)
     * @param callable $callback Callback function
     * @return void
     */
    public function on(string $event, callable $callback): void
    {
        $this->eventHandlers[$event][] = $callback;
        echo "Registered handler for event: {$event}\n";
    }

    /**
     * Emit an event with data to registered handlers
     * 
     * @param string $event Event name
     * @param mixed $data Data to pass to handlers
     * @return void
     */
    private function emit(string $event, $data = null): void
    {
        if (isset($this->eventHandlers[$event])) {
            foreach ($this->eventHandlers[$event] as $handler) {
                try {
                    call_user_func($handler, $data);
                } catch (Exception $e) {
                    echo "Error in event handler for '{$event}': " . $e->getMessage() . "\n";
                }
            }
        }
    }

    /**
     * Emit an event to a specific client
     * 
     * @param string $clientId Client ID
     * @param string $event Event name
     * @param mixed $data Data to send
     * @return bool
     */
    public function emitTo(string $clientId, string $event, $data): bool
    {
        if (isset($this->clients[$clientId])) {
            $message = [
                'type' => 'event',
                'event' => $event,
                'timestamp' => time(),
                ...$data
            ];

            $this->send($this->clients[$clientId]['socket'], json_encode($message));
            return true;
        }
        return false;
    }

    /**
     * Broadcast an event to all connected clients
     * 
     * @param string $event Event name
     * @param mixed $data Data to broadcast
     * @param string|null $excludeClientId Client ID to exclude
     * @return void
     */
    public function broadcastEvent(string $event, $data, ?string $excludeClientId = null): void
    {
        $message = [
            'type' => 'event',
            'event' => $event,
            'data' => $data,
            'timestamp' => time()
        ];

        $this->broadcast(json_encode($message), $excludeClientId);
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
        echo "Registered events: " . implode(', ', array_keys($this->eventHandlers)) . "\n";
        echo "Press Ctrl+C to stop\n\n";

        // Emit server start event
        $this->emit('server_start', [
            'host' => $this->host,
            'port' => $this->port,
            'timestamp' => time()
        ]);

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
                    'connected' => time(),
                    'last_activity' => time()
                ];

                echo "New client connected: $clientId\n";

                // Store additional client data
                $this->clientData[$clientId] = [
                    'id' => $clientId,
                    'ip' => socket_getpeername($clientSocket, $address) ? $address : 'unknown',
                    'connected_at' => date('Y-m-d H:i:s'),
                    'user_agent' => $this->extractUserAgent($request)
                ];

                // Send welcome message
                $this->send($clientSocket, json_encode([
                    'type' => 'welcome',
                    'from' => 'server',
                    'clientId' => $clientId,
                    'message' => 'Connected to server',
                    'timestamp' => time()
                ]));

                // Emit connection event
                $this->emit('connection', [
                    'clientId' => $clientId,
                    'clientData' => $this->clientData[$clientId],
                    'timestamp' => time()
                ]);

                // Emit to client
                $this->emitTo($clientId, 'connected', [
                    'message' => 'You are now connected to the server. Server time:'. date('Y-m-d H:i:s'),
                    'clientId' => $clientId,
                    'from' => 'server'
                ]);
            } else {
                socket_close($clientSocket);
            }
        }
    }

    private function extractUserAgent(string $request): string
    {
        if (preg_match('/User-Agent: (.*)\r\n/', $request, $matches)) {
            return trim($matches[1]);
        }
        return 'Unknown';
    }

    private function handleClient($socket)
    {
        $clientId = $this->findClientIdBySocket($socket);

        if ($clientId === null) {
            return;
        }

        // Update last activity
        $this->clients[$clientId]['last_activity'] = time();

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
                try {
                    // Try to parse as JSON for structured messages
                    $messageData = json_decode($decoded, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($messageData)) {
                        // Handle JSON message with event type
                        if (isset($messageData['event'])) {
                            // Emit custom event
                            $this->emit('client_' . $messageData['event'], [
                                'clientId' => $clientId,
                                'data' => $messageData['data'] ?? null,
                                'clientData' => $this->clientData[$clientId],
                                'raw' => $decoded
                            ]);
                        }

                        // Also emit generic message event with parsed data
                        $this->emit('message', [
                            'clientId' => $clientId,
                            'data' => $messageData,
                            'clientData' => $this->clientData[$clientId],
                            'raw' => $decoded,
                            'timestamp' => time()
                        ]);
                    } else {
                        // Plain text message
                        echo "Message from $clientId: $decoded\n";

                        // Emit message event
                        $this->emit('message', [
                            'clientId' => $clientId,
                            'data' => $decoded,
                            'clientData' => $this->clientData[$clientId],
                            'raw' => $decoded,
                            'timestamp' => time()
                        ]);

                        // Broadcast to all clients
                        $this->broadcast(json_encode([
                            'type' => 'message',
                            'from' => $clientId,
                            'message' => $decoded,
                            'timestamp' => time()
                        ]), $clientId);
                    }
                } catch (Exception $e) {
                    echo "Error handling message from $clientId: " . $e->getMessage() . "\n";
                    $this->emit('error', [
                        'clientId' => $clientId,
                        'error' => $e->getMessage(),
                        'timestamp' => time()
                    ]);
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
        @socket_write($socket, $frame, strlen($frame));
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
                @socket_write($client['socket'], $frame, strlen($frame));
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
            // Emit disconnect event before removing
            $this->emit('disconnect', [
                'clientId' => $clientId,
                'clientData' => $this->clientData[$clientId] ?? null,
                'timestamp' => time()
            ]);

            socket_close($socket);
            unset($this->clients[$clientId]);

            if (isset($this->clientData[$clientId])) {
                unset($this->clientData[$clientId]);
            }

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
            if ($now - $client['last_activity'] > 300) {
                $toRemove[] = $client['socket'];
            }
        }

        foreach ($toRemove as $socket) {
            $this->removeClient($socket);
        }
    }

    /**
     * Get all connected clients
     * 
     * @return array
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * Get client data
     * 
     * @param string $clientId
     * @return array|null
     */
    public function getClientData(string $clientId): ?array
    {
        return $this->clientData[$clientId] ?? null;
    }

    /**
     * Update client data
     * 
     * @param string $clientId
     * @param array $data
     * @return bool
     */
    public function updateClientData(string $clientId, array $data): bool
    {
        if (isset($this->clientData[$clientId])) {
            $this->clientData[$clientId] = array_merge($this->clientData[$clientId], $data);
            return true;
        }
        return false;
    }

    public function stop()
    {
        // Emit server stop event
        $this->emit('server_stop', [
            'timestamp' => time(),
            'clients_count' => count($this->clients)
        ]);

        $this->isRunning = false;

        foreach ($this->clients as $client) {
            socket_close($client['socket']);
        }

        socket_close($this->socket);
        echo "\nServer stopped\n";
    }
}

// // Example usage with event handlers
// if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
//     try {
//         $server = new WebSocketServer('0.0.0.0', 8080);

//         // Register event handlers
//         $server->on('server_start', function ($data) {
//             echo "Server started on {$data['host']}:{$data['port']}\n";
//         });

//         $server->on('connection', function ($data) {
//             echo "Client connected: {$data['clientId']}\n";
//             echo "  IP: {$data['clientData']['ip']}\n";
//             echo "  User Agent: {$data['clientData']['user_agent']}\n";
//         });

//         $server->on('message', function ($data) {
//             echo "Message from {$data['clientId']}: " .
//                 (is_string($data['data']) ? $data['data'] : json_encode($data['data'])) . "\n";
//         });

//         $server->on('disconnect', function ($data) {
//             echo "Client disconnected: {$data['clientId']}\n";
//         });

//         $server->on('client_custom_event', function ($data) {
//             echo "Custom event from {$data['clientId']}: " . json_encode($data['data']) . "\n";
//         });

//         $server->on('error', function ($data) {
//             echo "Error with client {$data['clientId']}: {$data['error']}\n";
//         });

//         // Handle Ctrl+C
//         if (function_exists('pcntl_signal')) {

//             declare(ticks=1);
//             pcntl_signal(SIGINT, function () use ($server) {
//                 echo "\nShutting down...\n";
//                 $server->stop();
//                 exit(0);
//             });
//         }

//         $server->start();
//     } catch (Exception $e) {
//         echo "Error: " . $e->getMessage() . "\n";
//         exit(1);
//     }
// }
