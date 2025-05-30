<?php

namespace App\Foundation\Http;

class ReqRequest
{
    private $curl;

    public function __construct(string $url, ?array $params = null)
    {
        if (! empty($params)) {
            $query = http_build_query($params);
            $url .= '?' . $query;
        }

        $this->curl = curl_init(trim($url));
    }

    public function setHeader(array $headers)
    {
        curl_setopt($this->curl, CURLOPT_HEADER, $headers);
        return $this;
    }

    public function setCertInfo(bool $bool)
    {
        curl_setopt($this->curl, CURLOPT_CERTINFO, $bool);
        return $this;
    }

    public function setOptions(array $options)
    {
        curl_setopt_array($this->curl, $options);
        return $this;
    }
}
