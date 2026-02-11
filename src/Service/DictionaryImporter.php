<?php

namespace Drupal\dictionary_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for importing dictionary entries as nodes.
 */
class DictionaryImporter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The dictionary API client.
   *
   * @var \Drupal\dictionary_import\Service\DictionaryApiClient
   */
  protected $apiClient;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a DictionaryImporter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\dictionary_import\Service\DictionaryApiClient $api_client
   *   The dictionary API client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    DictionaryApiClient $api_client,
    LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->apiClient = $api_client;
    $this->logger = $logger;
  }

  /**
   * Imports a word from external API and creates/updates node.
   *
   * @param string $word
   *   The word to import.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Exception
   *   If import fails unexpectedly.
   */
  public function importWord(string $word): bool {
    // Fetch from external API
    $wordData = $this->apiClient->fetchWord($word);

    if ($wordData === NULL) {
      $this->logger->warning('Cannot import word - not found in external API: @word', ['@word' => $word]);
      return FALSE;
    }

    // Transform definitions
    $definitions = $this->apiClient->transformDefinitions($wordData);

    if (empty($definitions)) {
      $this->logger->warning('No definitions found for word: @word', ['@word' => $word]);
      return FALSE;
    }

    // Check if entry already exists
    $existingNode = $this->findExistingEntry($word);

    if ($existingNode) {
      // Update existing
      $existingNode->set('field_definitions', $definitions);
      $existingNode->save();
      $this->logger->info('Updated existing dictionary entry: @word', ['@word' => $word]);
    }
    else {
      // Create new
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $node = $nodeStorage->create([
        'type' => 'dictionary_entry',
        'title' => $word,
        'field_word' => $word,
        'field_definitions' => $definitions,
        'status' => 1,
      ]);
      $node->save();
      $this->logger->info('Created new dictionary entry: @word', ['@word' => $word]);
    }

    return TRUE;
  }

  /**
   * Finds existing dictionary entry by word.
   *
   * @param string $word
   *   The word to search for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Existing node or NULL if not found.
   */
  protected function findExistingEntry(string $word): ?\Drupal\node\NodeInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    $query = $nodeStorage->getQuery()
      ->condition('type', 'dictionary_entry')
      ->condition('field_word', $word)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $nids = $query->execute();

    if (empty($nids)) {
      return NULL;
    }

    return $nodeStorage->load(reset($nids));
  }

}
