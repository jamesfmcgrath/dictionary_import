<?php

namespace Drupal\Tests\dictionary_import\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the DictionaryImporter service.
 *
 * @group dictionary_import
 */
class DictionaryImporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'dictionary_import',
  ];

  /**
   * The dictionary importer service.
   *
   * @var \Drupal\dictionary_import\Service\DictionaryImporter
   */
  protected $importer;

  /**
   * Mock API client.
   *
   * @var \Drupal\dictionary_import\Service\DictionaryApiClient
   */
  protected $mockApiClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install necessary schema.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'field']);

    // Create Dictionary Entry content type manually.
    $node_type = NodeType::create([
      'type' => 'dictionary_entry',
      'name' => 'Dictionary Entry',
    ]);
    $node_type->save();

    // Create field_word field.
    \Drupal\field\Entity\FieldStorageConfig::create([
      'field_name' => 'field_word',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();

    \Drupal\field\Entity\FieldConfig::create([
      'field_name' => 'field_word',
      'entity_type' => 'node',
      'bundle' => 'dictionary_entry',
      'label' => 'Word',
      'required' => TRUE,
    ])->save();

    // Create field_definitions field.
    \Drupal\field\Entity\FieldStorageConfig::create([
      'field_name' => 'field_definitions',
      'entity_type' => 'node',
      'type' => 'string_long',
    ])->save();

    \Drupal\field\Entity\FieldConfig::create([
      'field_name' => 'field_definitions',
      'entity_type' => 'node',
      'bundle' => 'dictionary_entry',
      'label' => 'Definitions',
    ])->save();

    // Create mock API client.
    $this->mockApiClient = $this->createMock(\Drupal\dictionary_import\Service\DictionaryApiClient::class);

    // Instantiate importer with mocked API client.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $logger = $this->container->get('logger.factory')->get('dictionary_import');

    $this->importer = new \Drupal\dictionary_import\Service\DictionaryImporter(
      $entity_type_manager,
      $this->mockApiClient,
      $logger
    );
  }

  /**
   * Tests importing a new word creates a node.
   */
  public function testImportCreatesNewNode() {
    // Mock API response for "hello" - return raw API structure.
    $mockApiData = [
      'word' => 'hello',
      'meanings' => [
        [
          'partOfSpeech' => 'noun',
          'definitions' => [
            ['definition' => 'A greeting'],
          ],
        ],
        [
          'partOfSpeech' => 'verb',
          'definitions' => [
            ['definition' => 'To greet someone'],
          ],
        ],
      ],
    ];

    $this->mockApiClient
      ->expects($this->once())
      ->method('fetchWord')
      ->with('hello')
      ->willReturn($mockApiData);

    $this->mockApiClient
      ->expects($this->once())
      ->method('transformDefinitions')
      ->with($mockApiData)
      ->willReturn("noun: A greeting\n\nverb: To greet someone");

    // Import the word.
    $result = $this->importer->importWord('hello');

    // Assert success.
    $this->assertTrue($result, 'Import should return TRUE on success.');

    // Verify node was created.
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'dictionary_entry',
        'field_word' => 'hello',
      ]);

    $this->assertCount(1, $nodes, 'One node should be created.');
    $node = reset($nodes);
    $this->assertEquals('hello', $node->get('field_word')->value);
    $this->assertStringContainsString('A greeting', $node->get('field_definitions')->value);
  }

  /**
   * Tests importing existing word updates the node.
   */
  public function testImportUpdatesExistingNode() {
    // Create existing node.
    $node = Node::create([
      'type' => 'dictionary_entry',
      'title' => 'computer',
      'field_word' => 'computer',
      'field_definitions' => 'Old definition',
    ]);
    $node->save();
    $original_nid = $node->id();

    // Mock API response with new definitions.
    $mockApiData = [
      'word' => 'computer',
      'meanings' => [
        [
          'partOfSpeech' => 'noun',
          'definitions' => [
            ['definition' => 'Electronic device'],
            ['definition' => 'Person who computes'],
          ],
        ],
      ],
    ];

    $this->mockApiClient
      ->expects($this->once())
      ->method('fetchWord')
      ->with('computer')
      ->willReturn($mockApiData);

    $this->mockApiClient
      ->expects($this->once())
      ->method('transformDefinitions')
      ->with($mockApiData)
      ->willReturn("noun: Electronic device\n\nnoun: Person who computes");

    // Import the word again.
    $result = $this->importer->importWord('computer');

    // Assert success.
    $this->assertTrue($result);

    // Verify only one node exists (updated, not duplicated).
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'dictionary_entry',
        'field_word' => 'computer',
      ]);

    $this->assertCount(1, $nodes, 'Should still be only one node.');
    $node = reset($nodes);
    $this->assertEquals($original_nid, $node->id(), 'Should be same node (updated).');
    $this->assertEquals('computer', $node->get('field_word')->value);
    $this->assertStringContainsString('Electronic device', $node->get('field_definitions')->value);
    $this->assertStringNotContainsString('Old definition', $node->get('field_definitions')->value);
  }

  /**
   * Tests handling when word is not found in API.
   */
  public function testImportHandlesWordNotFound() {
    // Mock API returning NULL (word not found).
    $this->mockApiClient
      ->expects($this->once())
      ->method('fetchWord')
      ->with('notarealword')
      ->willReturn(NULL);

    // transformDefinitions should not be called when fetchWord returns NULL.
    $this->mockApiClient
      ->expects($this->never())
      ->method('transformDefinitions');

    // Import non-existent word.
    $result = $this->importer->importWord('notarealword');

    // Assert failure.
    $this->assertFalse($result, 'Import should return FALSE when word not found.');

    // Verify no node was created.
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'dictionary_entry',
        'field_word' => 'notarealword',
      ]);

    $this->assertCount(0, $nodes, 'No node should be created for non-existent word.');
  }

}
