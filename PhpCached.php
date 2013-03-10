<?php
class PhpCachedException extends Exception {}

class PhpCached
{
    const PORT = 64781;
    const ADDR = '0.0.0.0';

    const GET_CMD = 'GET';
    const PUT_CMD = 'PUT';
    const DEL_CMD = 'DEL';

    const SUCCESS_ANSWER = 'SUCCESS';

    private $sock;
    private $data = array();

    public function log($msg)
    {
        fwrite(STDERR, "$msg\n");
    }

    private function getClientSocket($server)
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$sock) throw new PhpCachedException('Cannot create socket');
        if (!socket_connect($sock, $server, self::PORT)) throw new PhpCachedException('Cannot connect');

        return $sock;
    }

    public function get($server, $key)
    {
        $sock = $this->getClientSocket($server);
        $this->writeAll($sock, sprintf(self::GET_CMD . ' %10d%s', strlen($key), $key));
        $have_key = $this->readAll($sock, 1);
        if (!$have_key) {
            socket_close($sock);
            return '';
        }

        $response_len = intval($this->readAll($sock, 10));
        $response = $this->readAll($sock, $response_len);
        socket_close($sock);
        return $response;
    }

    public function put($server, $key, $data)
    {
        $sock = $this->getClientSocket($server);
        $cmd = sprintf(self::PUT_CMD . ' %10d%10d%s%s', strlen($key), strlen($data), $key, $data);
        $this->writeAll($sock, $cmd);
        $result = $this->readAll($sock, strlen(self::SUCCESS_ANSWER));
        socket_close($sock);
        return $result === self::SUCCESS_ANSWER;
    }

    public function del($server, $key)
    {
        $sock = $this->getClientSocket($server);
        $this->writeAll($sock, sprintf(self::DEL_CMD . ' %10d%s', strlen($key), $key));
        $result = $this->readAll($sock, strlen(self::SUCCESS_ANSWER));
        socket_close($sock);
        return $result === self::SUCCESS_ANSWER;
    }

    public function runDaemon()
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->sock) throw new PhpCachedException('Cannot create socket');

        if (!socket_bind($this->sock, self::ADDR, self::PORT)) {
            throw new PhpCachedException('Cannot bind socket (' . self::ADDR . ':' . self::PORT . ')');
        }

        if (!socket_listen($this->sock)) {
            throw new PhpCachedException('Cannot listen socket');
        }

        while ($client_sock = socket_accept($this->sock)) {
            try {
                $this->serveRequest($client_sock);
            } catch (PhpCachedException $e) {
                $this->log($e->getMessage());
            }
            socket_close($client_sock);
        }
    }

    private function readAll($sock, $read_len)
    {
        if (!$read_len) return '';
        $buf = '';
        $left = $read_len;
        while (true) {
            $len = min(65536, $left);
            if ($len === 0) break;
            $str = socket_read($sock, $len);
            if ($str === false) throw new PhpCachedException("Cannot read from client socket");
            if ($str === "") break;
            $buf .= $str;
            $left -= strlen($str);
        }

        if ($left != 0) throw new PhpCachedException("Cannot read $read_len bytes from client");
        return $buf;
    }

    private function writeAll($sock, $str)
    {
        if (!strlen($str)) return;
        $bytes_sent = 0;
        $chunk_size = 65536;
        while (true) {
            $chunk = substr($str, $bytes_sent, $chunk_size);
            $result = socket_write($sock, $chunk);

            if ($result === false || $result === 0) {
                throw new PhpCachedException("Cannot write to socket");
            }

            $bytes_sent += $result;
            if ($bytes_sent >= strlen($str)) return;
        }
    }

    private function serveRequest($sock)
    {
        $cmd = rtrim($this->readAll($sock, 4));

        if ($cmd === self::GET_CMD)      return $this->serveGet($sock);
        else if ($cmd === self::PUT_CMD) return $this->servePut($sock);
        else if ($cmd === self::DEL_CMD) return $this->serveDel($sock);

        $this->answerError($sock, 'Unknown command ' . $cmd);
        return false;
    }

    private function answerError($sock, $msg)
    {
        $this->writeAll($sock, "ERROR: $msg");
    }

    private function serveGet($sock)
    {
        $key_len = intval($this->readAll($sock, 10));
        $key = $this->readAll($sock, $key_len);
        if (isset($this->data[$key])) {
            $this->writeAll($sock, sprintf('1%10d%s', strlen($this->data[$key]), $this->data[$key]));
        } else {
            $this->writeAll($sock, '0');
        }
        return true;
    }

    private function servePut($sock)
    {
        $key_len = intval($this->readAll($sock, 10));
        $data_len = intval($this->readAll($sock, 10));
        $key = $this->readAll($sock, $key_len);
        $data = $this->readAll($sock, $data_len);
        $this->data[$key] = $data;
        $this->writeAll($sock, self::SUCCESS_ANSWER);
        return true;
    }

    private function serveDel($sock)
    {
        $key_len = intval($this->readAll($sock, 10));
        $key = $this->readAll($sock, $key_len);
        unset($this->data[$key]);
        $this->writeAll($sock, self::SUCCESS_ANSWER);
        return true;
    }
}
