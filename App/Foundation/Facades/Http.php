<?php

namespace App\Support\Facades;

use App\Foundation\Http\HttpClient;
use App\Foundation\Http\HttpResponse;
use App\Support\Facades\Facade;
use Dep;

/**
 * @method HttpResponse get(string $url, array $params = [], array $headers = []): HttpResponse
 * @method HttpResponse delete(string $url, array $params = [], array $headers = []): HttpResponse
 * @method HttpResponse post(string $url, array $params = [], array $headers = [], ?array $data): HttpResponse
 * @method HttpResponse put(string $url, array $params = [], array $headers = [], ?array $data): HttpResponse
 * @method HttpResponse patch(string $url, array $params = [], array $headers = [], ?array $data): HttpResponse
 */
#[Dep(Facade::class)]
class Http extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return new HttpClient(['timeout' => 30]);
    }

    /**
     * Set setting for HttpCLient instance
     * 
     * @param array{
     *  user_agent: string,
     *  ca_file: string,
     *  ca_path: string,
     *  json: 'object'|'array', 
     *  timeout: int|30, 
     *  follow_redirects: bool, 
     *  max_redirects: integer, 
     *  verify_peer: bool, 
     *  verify_peer_name: bool, 
     *  allow_self_signed: bool, 
     *  binary_download: bool
     * } $options
     * 
     * ___
     * Time out has default of 30 seconds
     */
    public static function option(array $options)
    {
        return new HttpClient(['timeout' => 30,...$options]);
    }
}
