<?php

namespace Drutiny\NewRelic\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\AuditResponse\AuditResponseException;
use Drutiny\NewRelic\Client;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Credential\Manager;
use Drutiny\Annotation\Param;
use Drutiny\Annotation\Token;

/**
 * @Param(
 *  name = "expression",
 *  type = "string",
 *  description = "An ExpressionLanguage expression to evaluate the outcome of a page rule.",
 * )
 * @Param(
 *  name = "not_applicable",
 *  type = "string",
 *  default = "false",
 *  description = "The expression language to evaludate if the analysis is not applicable. See https://symfony.com/doc/current/components/expression_language/syntax.html"
 * )
 * @Token(
 *   name = "apdex_score",
 *   type = "float",
 *   description = "Apdex score"
 * )
 * @Token(
 *   name = "apdex_threshold",
 *   type = "float",
 *   description = "Apdex threshold"
 * )
 */
class ApdexScoreAudit extends AbstractAnalysis {

  protected function requireApiCredentials()
  {
    return Manager::load('newrelic') ? TRUE : FALSE;
  }

  protected function api()
  {
    $creds = Manager::load('newrelic');
    return new Client($creds['app_id'], $creds['api_key']);
  }

  /**
   * {@inheritdoc}
   */
  public function gather(Sandbox $sandbox)
  {
    $uri = $sandbox->getTarget()->uri();
    $host = strpos($uri, 'http') === 0 ? parse_url($uri, PHP_URL_HOST) : $uri;
    $sandbox->setParameter('host', $host);

    $options = [
      'names[]' => 'Apdex',
      'summarize' => TRUE,
      'from' => $sandbox->getReportingPeriodStart()->format(\DateTimeInterface::RFC3339),
      'to' => $sandbox->getReportingPeriodEnd()->format(\DateTimeInterface::RFC3339),
    ];

    $query = http_build_query($options);
    try {
      $response = $this->api()->request("GET", 'metrics/data.json?' . $query, $options);
    }
    catch (\Exception $exception) {
      throw new AuditResponseException($exception->getMessage());
    }

    if ($response) {
      $apdex_values = $response['metric_data']['metrics'][0]['timeslices'][0]['values'];
      $sandbox->setParameter('apdex_score', $apdex_values['score']);
      $sandbox->setParameter('apdex_threshold', $apdex_values['threshold']);
    }
  }
}
