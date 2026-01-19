<?php
namespace Experimental\App\Foundation\Http\Websocket_beta;
// websocket_server.php
// Run from command line: php websocket_server.php

class WebSocketServer_beta
{
    private $clients = [];
    private $socket;
    public const OPCODE = [
        'CONTINUATION' => 0x0,
        'TEXT' => 0x1,
        'BINARY' => 0x2,
        'CLOSE' => 0x8,
        'PING' => 0x9,
        'PONG' => 0xA
    ];
    public const CLOSE_CODE = [
        'NORMAL' => 1000,
        'GOING_AWAY' => 1001,
        'PROTOCOL_ERROR' => 1002,
        'UNSUPPORTED_DATA' => 1003,
        'NO_STATUS' => 1005,
        'ABNORMAL_CLOSE' => 1006,
        'INVALID_PAYLOAD' => 1007,
        'POLICY_VIOLATION' => 1008,
        'MESSAGE_TOO_BIG' => 1009,
        'INTERNAL_ERROR' => 1011
    ];

    public function __construct($host = '0.0.0.0', $port = 8080)
    {
        // Create TCP/IP socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $host, $port);
        socket_listen($this->socket);

        echo "WebSocket server listening on ws://$host:$port\n";
    }

    public function run()
    {
        $null = null;
        $this->clients = [$this->socket];

        while (true) {
            $read = $this->clients;

            // Wait for activity on sockets (with 0.1s timeout)
            if (socket_select($read, $null, $null, 0, 100000) < 1) {
                continue;
            }

            // New connection
            if (in_array($this->socket, $read)) {
                $newClient = socket_accept($this->socket);
                $header = socket_read($newClient, 1024);

                if ($this->handshake($header, $newClient)) {
                    $this->clients[] = $newClient;
                    echo "New client connected\n";
                }

                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }

            // Handle messages from clients
            foreach ($read as $client) {
                $data = $this->unmask(socket_read($client, 1024));

                if ($data === false || strlen($data) === 0) {
                    // Client disconnected
                    $key = array_search($client, $this->clients);
                    socket_close($client);
                    unset($this->clients[$key]);
                    echo "Client disconnected\n";
                    continue;
                }

                echo "Received: $data\n";

                // Echo back to all clients
                foreach ($this->clients as $eachClient) {
                    if ($eachClient != $this->socket) {
                        $this->send($eachClient, "Echo: $data");
                    }
                }
            }
        }
    }

    private function handshake($header, $client)
    {
        $headers = [];
        $lines = explode("\n", $header);

        foreach ($lines as $line) {
            if (strpos($line, ":") !== false) {
                $parts = explode(":", $line, 2);
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        if (isset($headers['Sec-WebSocket-Key'])) {
            $key = $headers['Sec-WebSocket-Key'];
            $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";

            socket_write($client, $response);
            return true;
        }

        return false;
    }

    private function unmask($payload)
    {
        if (strlen($payload) < 2) return '';

        $length = ord($payload[1]) & 127;

        if ($length == 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        } elseif ($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }

        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }

        return $text;
    }

    private function send($client, $text)
    {
        $header = chr(0x81); // FIN + text frame
        $length = strlen($text);

        if ($length <= 125) {
            $header .= chr($length);
        } elseif ($length <= 65535) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('J', $length);
        }

        socket_write($client, $header . $text);
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }
}