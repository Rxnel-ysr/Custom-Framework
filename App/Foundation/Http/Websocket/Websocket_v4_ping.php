<?php
namespace Experimental\App\Foundation\Http\Websocket_v4;
// websocket_server.php
// Run: php websocket_server.php

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

    /**
     * Check if opcode is a control frame
     */
    public function isControl(): bool
    {
        return $this->value >= 0x8;
    }

    /**
     * Check if opcode is a data frame
     */
    public function isData(): bool
    {
        return $this->value <= 0x2;
    }

    /**
     * Get opcode name as string
     */
    public function toString(): string
    {
        return match ($this) {
            self::CONTINUATION => 'CONTINUATION',
            self::TEXT => 'TEXT',
            self::BINARY => 'BINARY',
            self::CLOSE => 'CLOSE',
            self::PING => 'PING',
            self::PONG => 'PONG',
            default => 'UNKNOWN'
        };
    }

    /**
     * Create from integer value
     */
    public static function fromInt(int $value): ?self
    {
        return self::tryFrom($value);
    }
}

/**
 * WebSocket close codes for connection termination.
 */
enum CloseCode: int
{
    case NORMAL = 1000;
    case GOING_AWAY = 1001;
    case PROTOCOL_ERROR = 1002;
    case UNSUPPORTED_DATA = 1003;
    case NO_STATUS = 1005;
    case ABNORMAL_CLOSE = 1006;
    case INVALID_PAYLOAD = 1007;
    case POLICY_VIOLATION = 1008;
    case MESSAGE_TOO_BIG = 1009;
    case INTERNAL_ERROR = 1011;
    

    /**
     * Check if close code is reserved
     */
    public function isReserved(): bool
    {
        return $this->value < 1000 ||
            ($this->value >= 1015 && $this->value <= 2999);
    }

    /**
     * Check if close code is allowed for applications
     */
    public function isApplicationDefined(): bool
    {
        return $this->value >= 3000 && $this->value <= 4999;
    }

    /**
     * Get close reason as string
     */
    public function getReason(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal closure',
            self::GOING_AWAY => 'Going away',
            self::PROTOCOL_ERROR => 'Protocol error',
            self::UNSUPPORTED_DATA => 'Unsupported data',
            self::INVALID_PAYLOAD => 'Invalid payload data',
            self::POLICY_VIOLATION => 'Policy violation',
            self::MESSAGE_TOO_BIG => 'Message too big',
            self::INTERNAL_ERROR => 'Internal server error',
            default => 'Unknown close reason'
        };
    }

    /**
     * Create from integer value with validation
     */
    public static function fromInt(int $value): ?self
    {
        $code = self::tryFrom($value);
        if ($code && !$code->isReserved()) {
            return $code;
        }
        return null;
    }
}

/**
 * WebSocket frame structure
 */
class WebSocketFrame_v4
{
    public function __construct(
        public readonly bool $fin,
        public readonly bool $rsv1,
        public readonly bool $rsv2,
        public readonly bool $rsv3,
        public readonly Opcode $opcode,
        public readonly bool $masked,
        public readonly int $payloadLength,
        public readonly ?string $maskingKey,
        public readonly string $payload
    ) {}
}

class WebSocketServer
{
    private $socket;
    private $clients = [];
    private $isRunning = false;
    private $eventHandlers = [];
    private $clientData = [];
    private $pingInterval = 30; // seconds
    private $maxMessageSize = 1048576; // 1MB
    private $frameHandlers = [];
    private $readBuffers = []; // Buffer for incomplete frames

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

        // Register default frame handlers
        $this->registerDefaultFrameHandlers();
    }

    /**
     * Register a handler for specific opcode frames
     */
    public function onFrame(Opcode $opcode, callable $callback): void
    {
        $this->frameHandlers[$opcode->value][] = $callback;
        echo "Registered frame handler for opcode: {$opcode->toString()}\n";
    }

    /**
     * Register default frame handlers
     */
    private function registerDefaultFrameHandlers(): void
    {
        // Handle PING frames
        $this->onFrame(Opcode::PING, function ($clientId, $frame, $socket) {
            $this->handlePing($clientId, $frame, $socket);
        });

        // Handle CLOSE frames
        $this->onFrame(Opcode::CLOSE, function ($clientId, $frame, $socket) {
            $this->handleClose($clientId, $frame, $socket);
        });

        // Handle PONG frames
        $this->onFrame(Opcode::PONG, function ($clientId, $frame, $socket) {
            $this->handlePong($clientId, $frame, $socket);
        });
    }

    /**
     * Handle PING frame
     */
    private function handlePing(string $clientId, WebSocketFrame $frame, $socket): void
    {
        echo "PING received from {$clientId}, payload length: " . strlen($frame->payload) . "\n";

        // Respond with PONG containing the same payload data
        $this->sendPong($clientId, $frame->payload);
        echo "Sent PONG response to {$clientId}\n";

        // Update client last activity
        if (isset($this->clients[$clientId])) {
            $this->clients[$clientId]['last_activity'] = time();
            $this->clients[$clientId]['last_ping'] = time();
        }

        $this->emit('ping', [
            'clientId' => $clientId,
            'timestamp' => time(),
            'payload' => $frame->payload,
            'payload_hex' => bin2hex($frame->payload)
        ]);
    }


    /**
     * Handle CLOSE frame
     */
    private function handleClose(string $clientId, WebSocketFrame $frame, $socket): void
    {
        echo "CLOSE received from {$clientId}\n";

        $closeCode = CloseCode::NORMAL;
        $closeReason = '';

        if (strlen($frame->payload) >= 2) {
            $codeBytes = substr($frame->payload, 0, 2);
            $code = unpack('n', $codeBytes)[1];
            $closeCode = CloseCode::fromInt($code) ?? CloseCode::NORMAL;

            if (strlen($frame->payload) > 2) {
                $closeReason = substr($frame->payload, 2);
            }
        }

        // Respond with close frame
        $this->sendCloseFrame($socket, $closeCode, $closeReason);

        // Close connection
        $this->removeClient($socket);

        $this->emit('close_received', [
            'clientId' => $clientId,
            'code' => $closeCode->value,
            'reason' => $closeReason,
            'timestamp' => time()
        ]);
    }

    /**
     * Handle PONG frame
     */
    private function handlePong(string $clientId, WebSocketFrame $frame, $socket): void
    {
        echo "PONG received from {$clientId}, payload: " . bin2hex($frame->payload) . "\n";

        if (isset($this->clients[$clientId])) {
            $this->clients[$clientId]['last_activity'] = time();
            $this->clients[$clientId]['last_pong'] = time();

            // Calculate latency if we have a timestamp in the payload
            if (isset($this->clients[$clientId]['last_ping_sent'])) {
                $latency = time() - $this->clients[$clientId]['last_ping_sent'];
                $this->clients[$clientId]['latency'] = $latency;

                $this->emit('pong', [
                    'clientId' => $clientId,
                    'timestamp' => time(),
                    'latency' => $latency,
                    'payload' => $frame->payload,
                    'payload_hex' => bin2hex($frame->payload)
                ]);
            } else {
                // Just acknowledge pong without latency calculation
                $this->emit('pong', [
                    'clientId' => $clientId,
                    'timestamp' => time(),
                    'latency' => null,
                    'payload' => $frame->payload,
                    'payload_hex' => bin2hex($frame->payload)
                ]);
            }
        }
    }

    /**
     * Send a close frame to client
     */
    public function sendClose(string $clientId, CloseCode $code = CloseCode::NORMAL, string $reason = ''): bool
    {
        if (isset($this->clients[$clientId])) {
            $socket = $this->clients[$clientId]['socket'];
            return $this->sendCloseFrame($socket, $code, $reason);
        }
        return false;
    }

    /**
     * Send close frame to socket
     */
    private function sendCloseFrame($socket, CloseCode $code, string $reason): bool
    {
        $payload = pack('n', $code->value) . $reason;
        return $this->sendFrame($socket, $payload, Opcode::CLOSE) !== false;
    }

    /**
     * Send a ping to client
     */
    public function sendPing(string $clientId, string $data = ''): bool
    {
        if (isset($this->clients[$clientId])) {
            $socket = $this->clients[$clientId]['socket'];
            $this->clients[$clientId]['last_ping_sent'] = time();
            return $this->sendFrame($socket, $data, Opcode::PING) !== false;
        }
        return false;
    }

    /**
     * Send a pong to client (usually in response to ping)
     */
    public function sendPong(string $clientId, string $data = ''): bool
    {
        if (isset($this->clients[$clientId])) {
            $socket = $this->clients[$clientId]['socket'];
            return $this->sendFrame($socket, $data, Opcode::PONG) !== false;
        }
        return false;
    }

    /**
     * Send a text message using proper WebSocket frame
     */
    public function sendText(string $clientId, string $message): bool
    {
        if (isset($this->clients[$clientId])) {
            $socket = $this->clients[$clientId]['socket'];
            return $this->sendFrame($socket, $message, Opcode::TEXT) !== false;
        }
        return false;
    }

    /**
     * Send a binary message using proper WebSocket frame
     */
    public function sendBinary(string $clientId, string $data): bool
    {
        if (isset($this->clients[$clientId])) {
            $socket = $this->clients[$clientId]['socket'];
            return $this->sendFrame($socket, $data, Opcode::BINARY) !== false;
        }
        return false;
    }

    /**
     * Send a WebSocket frame
     */
    private function sendFrame($socket, string $payload, Opcode $opcode): bool|int
    {
        $frame = $this->encodeFrame($payload, $opcode);
        return @socket_write($socket, $frame, strlen($frame));
    }

    /**
     * Broadcast text message to all clients
     */
    public function broadcastText(string $message, ?string $excludeClientId = null): void
    {
        $this->broadcastFrame($message, Opcode::TEXT, $excludeClientId);
    }

    /**
     * Broadcast binary data to all clients
     */
    public function broadcastBinary(string $data, ?string $excludeClientId = null): void
    {
        $this->broadcastFrame($data, Opcode::BINARY, $excludeClientId);
    }

    /**
     * Broadcast WebSocket frame to all clients
     */
    private function broadcastFrame(string $payload, Opcode $opcode, ?string $excludeClientId = null): void
    {
        $frame = $this->encodeFrame($payload, $opcode);

        foreach ($this->clients as $clientId => $client) {
            if ($clientId !== $excludeClientId) {
                @socket_write($client['socket'], $frame, strlen($frame));
            }
        }
    }

    /**
     * Parse WebSocket frame from binary data
     */
    private function parseFrame(string $data): ?WebSocketFrame
    {
        if (strlen($data) < 2) {
            return null;
        }

        $bytes = str_split($data);
        $firstByte = ord($bytes[0]);
        $secondByte = ord($bytes[1]);

        // Parse first byte
        $fin = ($firstByte & 0x80) !== 0;
        $rsv1 = ($firstByte & 0x40) !== 0;
        $rsv2 = ($firstByte & 0x20) !== 0;
        $rsv3 = ($firstByte & 0x10) !== 0;
        $opcodeValue = $firstByte & 0x0F;
        $opcode = Opcode::fromInt($opcodeValue);

        if ($opcode === null) {
            echo "Invalid opcode: 0x" . dechex($opcodeValue) . "\n";
            return null;
        }

        // Parse second byte
        $masked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;

        $offset = 2;

        // Handle extended payload length
        if ($payloadLength === 126) {
            if (strlen($data) < $offset + 2) {
                return null;
            }
            $payloadLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            if (strlen($data) < $offset + 8) {
                return null;
            }
            // Read as 64-bit unsigned integer
            $payloadLength = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }

        // Check message size limit
        if ($payloadLength > $this->maxMessageSize) {
            echo "Message too large: {$payloadLength} bytes\n";
            return null;
        }

        // Read masking key if present
        $maskingKey = null;
        if ($masked) {
            if (strlen($data) < $offset + 4) {
                return null;
            }
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
        }

        // Check if we have enough data for payload
        if (strlen($data) < $offset + $payloadLength) {
            return null; // Incomplete frame
        }

        // Read payload
        $payload = substr($data, $offset, $payloadLength);

        // Unmask payload if necessary
        if ($masked && $maskingKey !== null) {
            $unmasked = '';
            for ($i = 0; $i < $payloadLength; $i++) {
                $unmasked .= $payload[$i] ^ $maskingKey[$i % 4];
            }
            $payload = $unmasked;
        }

        return new WebSocketFrame(
            $fin,
            $rsv1,
            $rsv2,
            $rsv3,
            $opcode,
            $masked,
            $payloadLength,
            $maskingKey,
            $payload
        );
    }

    /**
     * Encode WebSocket frame
     */
    private function encodeFrame(string $payload, Opcode $opcode): string
    {
        $len = strlen($payload);
        $frame = [];

        // First byte: FIN=1, RSV=0, Opcode
        $frame[0] = 0x80 | $opcode->value; // FIN=1

        // Second byte and length
        if ($len <= 125) {
            $frame[1] = $len;
            $dataOffset = 2;
        } elseif ($len <= 65535) {
            $frame[1] = 126;
            $frame[2] = ($len >> 8) & 0xFF;
            $frame[3] = $len & 0xFF;
            $dataOffset = 4;
        } else {
            $frame[1] = 127;
            for ($i = 0; $i < 8; $i++) {
                $frame[2 + $i] = ($len >> (8 * (7 - $i))) & 0xFF;
            }
            $dataOffset = 10;
        }

        // Build frame header
        $frameHeader = '';
        for ($i = 0; $i < $dataOffset; $i++) {
            $frameHeader .= chr($frame[$i]);
        }

        return $frameHeader . $payload;
    }

    /**
     * Register an event handler
     */
    public function on(string $event, callable $callback): void
    {
        $this->eventHandlers[$event][] = $callback;
        echo "Registered handler for event: {$event}\n";
    }

    /**
     * Emit an event with data to registered handlers
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

            return $this->sendText($clientId, json_encode($message));
        }
        return false;
    }

    /**
     * Broadcast an event to all connected clients
     */
    public function broadcastEvent(string $event, $data, ?string $excludeClientId = null): void
    {
        $message = [
            'type' => 'event',
            'event' => $event,
            'data' => $data,
            'timestamp' => time()
        ];

        $this->broadcastText(json_encode($message), $excludeClientId);
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

        if ($data === false || strlen($data) === 0) {
            // Client disconnected
            $this->removeClient($socket);
            return;
        }

        // Append to buffer and process
        if (!isset($this->readBuffers[$clientId])) {
            $this->readBuffers[$clientId] = '';
        }
        $this->readBuffers[$clientId] .= $data;

        // Process all complete frames in buffer
        $this->processClientBuffer($clientId, $socket);
    }

    private function processClientBuffer(string $clientId, $socket): void
    {
        $buffer = $this->readBuffers[$clientId] ?? '';

        while (strlen($buffer) > 0) {
            // Try to parse a frame
            $frame = $this->parseFrame($buffer);

            if ($frame === null) {
                // Incomplete frame, keep in buffer
                $this->readBuffers[$clientId] = $buffer;
                return;
            }

            // Remove processed frame from buffer
            $frameLength = $this->calculateFrameLength($frame);
            $buffer = substr($buffer, $frameLength);

            // Handle the frame
            $this->handleWebSocketFrame($clientId, $frame, $socket);
        }

        // Clear buffer if all data processed
        $this->readBuffers[$clientId] = '';
    }

    /**
     * Calculate the total length of a WebSocket frame
     */
    private function calculateFrameLength(WebSocketFrame $frame): int
    {
        $length = 2; // Base header

        if ($frame->payloadLength <= 125) {
            // No additional length bytes
        } elseif ($frame->payloadLength <= 65535) {
            $length += 2; // 16-bit length
        } else {
            $length += 8; // 64-bit length
        }

        if ($frame->masked) {
            $length += 4; // Masking key
        }

        $length += $frame->payloadLength;

        return $length;
    }

    /**
     * Handle a parsed WebSocket frame
     */
    private function handleWebSocketFrame(string $clientId, WebSocketFrame $frame, $socket): void
    {
        // Validate frame
        if ($frame->rsv1 || $frame->rsv2 || $frame->rsv3) {
            echo "Invalid RSV bits set from {$clientId}\n";
            $this->sendClose($clientId, CloseCode::PROTOCOL_ERROR);
            $this->removeClient($socket);
            return;
        }

        // Handle control frames
        if ($frame->opcode->isControl()) {
            if (!$frame->fin) {
                echo "Fragmented control frame from {$clientId}\n";
                $this->sendClose($clientId, CloseCode::PROTOCOL_ERROR);
                $this->removeClient($socket);
                return;
            }

            if ($frame->payloadLength > 125) {
                echo "Control frame too large from {$clientId}\n";
                $this->sendClose($clientId, CloseCode::PROTOCOL_ERROR);
                $this->removeClient($socket);
                return;
            }

            $this->handleControlFrame($clientId, $frame, $socket);
            return;
        }

        // Handle data frames
        $this->handleDataFrame($clientId, $frame, $socket);
    }

    /**
     * Handle control frames
     */
    private function handleControlFrame(string $clientId, WebSocketFrame $frame, $socket): void
    {
        echo "Control frame from {$clientId}: {$frame->opcode->toString()}\n";

        // Check for registered frame handlers
        if (isset($this->frameHandlers[$frame->opcode->value])) {
            foreach ($this->frameHandlers[$frame->opcode->value] as $handler) {
                try {
                    call_user_func($handler, $clientId, $frame, $socket);
                } catch (Exception $e) {
                    echo "Error in frame handler for {$frame->opcode->toString()}: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "No handler for opcode: {$frame->opcode->toString()}\n";

            // Auto-respond to PING if no handler
            if ($frame->opcode === Opcode::PING) {
                $this->handlePing($clientId, $frame, $socket);
            }
        }
    }


    private function removeClient($socket)
    {
        $clientId = $this->findClientIdBySocket($socket);

        if ($clientId !== null) {
            // Clean up read buffer
            if (isset($this->readBuffers[$clientId])) {
                unset($this->readBuffers[$clientId]);
            }

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
            $this->broadcastText(json_encode([
                'type' => 'client_left',
                'clientId' => $clientId,
                'timestamp' => time()
            ]));
        }
    }

    /**
     * Handle data frames
     */
    private function handleDataFrame(string $clientId, WebSocketFrame $frame, $socket): void
    {
        try {
            // Try to parse as JSON for structured messages
            $messageData = json_decode($frame->payload, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($messageData)) {
                // Handle JSON message with event type
                if (isset($messageData['event'])) {
                    // Emit custom event
                    $this->emit('client_' . $messageData['event'], [
                        'clientId' => $clientId,
                        'data' => $messageData['data'] ?? null,
                        'clientData' => $this->clientData[$clientId],
                        'opcode' => $frame->opcode->toString(),
                        'raw' => $frame->payload
                    ]);
                }

                // Also emit generic message event with parsed data
                $this->emit('message', [
                    'clientId' => $clientId,
                    'data' => $messageData,
                    'clientData' => $this->clientData[$clientId],
                    'opcode' => $frame->opcode->toString(),
                    'raw' => $frame->payload,
                    'timestamp' => time()
                ]);
            } else {
                // Plain text message
                echo "Message from {$clientId} (opcode: {$frame->opcode->toString()}): {$frame->payload}\n";

                // Emit message event
                $this->emit('message', [
                    'clientId' => $clientId,
                    'data' => $frame->payload,
                    'clientData' => $this->clientData[$clientId],
                    'opcode' => $frame->opcode->toString(),
                    'raw' => $frame->payload,
                    'timestamp' => time()
                ]);

                // Broadcast to all clients
                $this->broadcastText(json_encode([
                    'type' => 'message',
                    'from' => $clientId,
                    'message' => $frame->payload,
                    'timestamp' => time()
                ]), $clientId);
            }
        } catch (Exception $e) {
            echo "Error handling message from {$clientId}: " . $e->getMessage() . "\n";
            $this->emit('error', [
                'clientId' => $clientId,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]);
        }
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

            // Send periodic pings
            $this->sendPeriodicPings();

            // Clean up disconnected clients
            $this->cleanupClients();
        }
    }

    /**
     * Send periodic pings to clients
     */
    private function sendPeriodicPings(): void
    {
        $now = time();
        foreach ($this->clients as $clientId => $client) {
            $lastActivity = $client['last_activity'] ?? 0;

            // Send ping if no activity for pingInterval
            if ($now - $lastActivity >= $this->pingInterval) {
                $this->sendPing($clientId, 'ping_' . time());
            }
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
                    'last_activity' => time(),
                    'frame_count' => 0
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
                $this->sendText($clientId, json_encode([
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
                    'message' => 'You are now connected to the server. Server time:' . date('Y-m-d H:i:s'),
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

    private function findClientIdBySocket($socket)
    {
        foreach ($this->clients as $clientId => $client) {
            if ($client['socket'] === $socket) {
                return $clientId;
            }
        }
        return null;
    }

    private function cleanupClients()
    {
        $now = time();
        $toRemove = [];

        foreach ($this->clients as $clientId => $client) {
            // Remove clients that haven't sent data in 300 seconds
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
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * Get client data
     */
    public function getClientData(string $clientId): ?array
    {
        return $this->clientData[$clientId] ?? null;
    }

    /**
     * Update client data
     */
    public function updateClientData(string $clientId, array $data): bool
    {
        if (isset($this->clientData[$clientId])) {
            $this->clientData[$clientId] = array_merge($this->clientData[$clientId], $data);
            return true;
        }
        return false;
    }

    /**
     * Set ping interval
     */
    public function setPingInterval(int $seconds): void
    {
        $this->pingInterval = $seconds;
    }

    /**
     * Set maximum message size
     */
    public function setMaxMessageSize(int $bytes): void
    {
        $this->maxMessageSize = $bytes;
    }

    public function stop()
    {
        // Send close frame to all clients
        foreach ($this->clients as $clientId => $client) {
            $this->sendClose($clientId, CloseCode::GOING_AWAY, 'Server shutting down');
        }

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

// // Example usage with enhanced features
// if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
//     try {
//         $server = new WebSocketServer('0.0.0.0', 8080);

//         // Configure server
//         $server->setPingInterval(30);
//         $server->setMaxMessageSize(1048576); // 1MB

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
//             echo "Message from {$data['clientId']} (opcode: {$data['opcode']}): " .
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

//         $server->on('ping', function ($data) {
//             echo "Ping from {$data['clientId']}\n";
//         });

//         $server->on('pong', function ($data) {
//             echo "Pong from {$data['clientId']} (latency: {$data['latency']}s)\n";
//         });

//         $server->on('close_received', function ($data) {
//             echo "Close received from {$data['clientId']} (code: {$data['code']}, reason: {$data['reason']})\n";
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
