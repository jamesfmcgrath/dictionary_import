<?php

namespace Drupal\dictionary_import\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Service for fetching word definitions from external dictionary API.
 */
class DictionaryApiClient {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * External API base URL.
   *
   * @var string
   */
  protected $apiBaseUrl = 'https://api.dictionaryapi.dev/api/v2/entries/en';

  /**
   * Constructs a DictionaryApiClient object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(ClientInterface $http_client, LoggerInterface $logger) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * Fetches word definition from external API.
   *
   * @param string $word
   *   The word to look up.
   *
   * @return array|null
   *   Array containing word data, or NULL if word not found.
   *
   * @throws \Exception
   *   If API request fails unexpectedly.
   */
  public function fetchWord(string $word): ?array {
    $url = sprintf('%s/%s', $this->apiBaseUrl, urlencode($word));

    try {
      $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (empty($data) || !is_array($data)) {
        return NULL;
      }

      return $data[0] ?? NULL;
    }
    catch (RequestException $e) {
      // 404 means word not found - this is expected, return NULL
      if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
        $this->logger->info('Word not found in external API: @word', ['@word' => $word]);
        return NULL;
      }

      // Other errors are unexpected - log and throw
      $this->logger->error('Failed to fetch word from API: @word. Error: @error', [
        '@word' => $word,
        '@error' => $e->getMessage(),
      ]);
      throw new \Exception('External API request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Transforms API response into simplified definition string.
   *
   * @param array $wordData
   *   Raw word data from API.
   *
   * @return string
   *   Formatted definitions string.
   */
  public function transformDefinitions(array $wordData): string {
    $definitions = [];

    if (empty($wordData['meanings'])) {
      return '';
    }

    foreach ($wordData['meanings'] as $meaning) {
      $partOfSpeech = $meaning['partOfSpeech'] ?? 'unknown';

      if (empty($meaning['definitions'])) {
        continue;
      }

      foreach ($meaning['definitions'] as $def) {
        $definition = $def['definition'] ?? '';
        if ($definition) {
          $definitions[] = sprintf('%s: %s', $partOfSpeech, $definition);
        }
      }
    }

    return implode("\n\n", $definitions);
  }

}
