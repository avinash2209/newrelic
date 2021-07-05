<?php

namespace Drutiny\NewRelic;

use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Output\OutputInterface;
use Drutiny\Http\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;

class Client {

  /**
   * API base URL for Cloudflare.
   */
  const API_BASE = 'https://api.newrelic.com/v2/applications/';

  /**
   * Email used for API authentication.
   */
  protected $app_id;

  /**
   * API key used for authentication.
   */
  protected $api_key;

  /**
   * API constructor.
   */
  public function __construct($app_id, $api_key) {
    $this->app_id = $app_id;
    $this->api_key = $api_key;
  }

  /**
   * Perform an API request to Cloudflare.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $endpoint
   *   The API endpoint to hit. The endpoint is prefixed with the API_BASE.
   * @param array $payload
   *
   * @param bool $decodeBody
   *   Whether the body should be JSON decoded.
   * @return array|string
   *   Decoded JSON body of the API request, if the request was successful.
   *
   * @throws \Exception
   */
  public function request($method = 'GET', $endpoint, $payload = [], $decodeBody = TRUE) {
    $url = '';
    $time = 0;

    $client = new HttpClient([
      'base_uri' => 'https://api.newrelic.com/v2/applications/'. $this->app_id . '/',
      'headers' => [
        'x-api-key' => $this->api_key,
      ],
    ]);

    if (!empty($payload)) {
      $response = $client->request($method, $endpoint, [
        RequestOptions::JSON => $payload,
      ]);
    }
    else {
      $response = $client->request($method, $endpoint);
    }

    if (!in_array($response->getStatusCode(), [200, 204])) {
      throw new \Exception('Error: ' . (string) $response->getBody());
    }

    if ($decodeBody) {
      return json_decode($response->getBody(), TRUE);
    }
    return (string) $response->getBody();
  }

}
