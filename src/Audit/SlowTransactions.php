<?php

namespace Drutiny\NewRelic\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\AuditResponse\AuditResponseException;
use Drutiny\NewRelic\Client;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Credential\Manager;
use Drutiny\Annotation\Param;

/**
 * @Param(
 *  name = "from",
 *  description = "The reporting date to start from. e.g. -24 hours.",
 *  default = false,
 *  type = "string"
 * )
 * @Param(
 *  name = "to",
 *  description = "The reporting date to end on. e.g. now.",
 *  default = false,
 *  type = "string"
 * )
 */
class SlowTransactions extends AbstractAnalysis {

  /**
   * API base URL for Cloudflare.
   */
  const API_BASE = 'https://api.newrelic.com/v2/applications/';

  /**
   * Email used for API authentication.
   */
  protected $account_id;

  /**
   * API key used for authentication.
   */
  protected $api_key;

  protected function requireApiCredentials()
  {
    return Manager::load('newrelic') ? TRUE : FALSE;
  }

  protected function api()
  {
    $creds = Manager::load('newrelic');
    return new Client($creds['account_id'], $creds['api_key']);
  }

  /**
   * {@inheritdoc}
   */
  public function gather(Sandbox $sandbox)
  {
    $creds = Manager::load('newrelic');
    $this->setApiCreds($creds);
    $uri = $sandbox->getTarget()->uri();
    $host = strpos($uri, 'http') === 0 ? parse_url($uri, PHP_URL_HOST) : $uri;
    $sandbox->setParameter('host', $host);

    $transactions = $this->getNewRelicTransactions($sandbox);
    usort($transactions, function($a, $b) {
      return $b['average_response_time'] <=> $a['average_response_time'];
    });
    $result['transaction'] = array_slice($transactions, 0, 9);
    $sandbox->setParameter('results', $result['transaction']);
    return TRUE;
  }

  public function getNewRelicMetricNames(Sandbox $sandbox) {
    $uri = $this->getNewRelicMetricUrl();
    $options = [
      'query' => [
        'name' => 'WebTransaction/Action/Drupal',
      ],
      'headers' => [
        'x-api-key' => $this->api_key
      ],
    ];

    // Set start and end date for the metric data.
    $options['query']['from'] = $sandbox->getReportingPeriodStart()->format(\DateTime::RFC3339);
    $options['query']['to'] = $sandbox->getReportingPeriodStart()->format(\DateTime::RFC3339);

    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', $uri, $options);

    return json_decode($response->getBody()->getContents(), true);
  }

  public function getNewRelicTransactions(Sandbox $sandbox) {
    $metricNames = $this->getNewRelicMetricNames($sandbox);

    $transactions = [];
    $i = 0;
    foreach ($metricNames['metrics'] as $item) {
      $uri = $this->getNewRelicDataUrl();
      $options = [
        'headers' => [
          'x-api-key' => $this->api_key
        ],
      ];

      // Set start and end date for the metric data.
      $params = [
        'names[]' => $item['name'],
        'summarize' => TRUE,
        'from' => $sandbox->getReportingPeriodStart()->format(\DateTime::RFC3339),
        'to' => $sandbox->getReportingPeriodEnd()->format(\DateTime::RFC3339),
      ];
      $query = http_build_query($params);
      $uri .= '?summarize=true&' . $query;
      $client = new \GuzzleHttp\Client();
      $request = $client->request('GET', $uri, $options);
      $data = json_decode($request->getBody()->getContents(), true);

      // Collect metric data from the response and add metric name.
      if (!empty($data['metric_data']['metrics'][0]['timeslices'][0]['values']['average_response_time'])) {
        $transactions[$i] = $data['metric_data']['metrics'][0]['timeslices'][0]['values'];
        $transactions[$i]['name'] = $item['name'];
        $lastvalue = end($transactions[$i]);
        $lastkey = key($transactions[$i]);
        $transactions_sort[$i] = array($lastkey=>$lastvalue);
        $transactions[$i] = array_merge($transactions_sort[$i],$transactions[$i]);
      }

      $i++;
    }

    return $transactions;
  }

  public function getNewRelicDataUrl() {
    return self::API_BASE . $this->account_id . '/metrics/data.json';
  }

  public function getNewRelicMetricUrl() {
    return self::API_BASE . $this->account_id . '/metrics.json';
  }

  public function setApiCreds($creds) {
    $this->account_id = $creds['account_id'];
    $this->api_key = $creds['api_key'];
  }
}
