<?php

namespace Drupal\bhcc_data_extraction\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;

/**
 * Controller for BodyDataExtractionController.
 */
class BodyDataExtractionController extends ControllerBase {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager; // ✅ Removed type to avoid conflict with ControllerBase.

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Data extraction for the body field.
   */
  public function nodeBodyExtraction(): Response {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $node_ids = $node_storage->getQuery()
      ->condition('type', 'localgov_services_page')
      ->accessCheck(TRUE)
      ->execute();

    // If no nodes are found, return a message.
    if (empty($node_ids)) {
      return new Response('<h1>No nodes found</h1>', 200, ['Content-Type' => 'text/html']);
    }

    // Initialize the HTML output.
    $html_output = '<p>Body Field Data Extraction</p>';
    $html_output .= '<table border="1" cellpadding="5" cellspacing="0">';
    $html_output .= '<thead><tr>';
    $html_output .= '<th>Node ID</th>';
    $html_output .= '<th>Title</th>';
    $html_output .= '<th>Page URL</th>';
    $html_output .= '<th>Content Type</th>';
    $html_output .= '<th>Matched Text</th>';
    $html_output .= '<th>Status</th>';
    $html_output .= '</tr></thead><tbody>';

    // List of regex array to loop through.
          // $regexes = [
          // '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/'
          // ];.
          $regexes = [
            '/\bcity\s+clean\b/i',
            '/\bcityclean\b/i',
          ];
          // $regexes = [
          // '/\bcity\s*clean\b|\bcityClean\b|\bcityclean\b/i',
          // ];
          // $regexes = [
          // '/\(0\d{3}\)\s?\d{3}\s?\d{3}/',
          // '/\(0\d{4}\)\s?\d{3}\s?\d{3}/',
          // '/0\d{3}\s?\d{3}\s?\d{3}/',
          // '/0\d{4}\s?\d{3}\s?\d{3}/',
          // ];

    // Loop through each node.
    foreach ($node_ids as $node_id) {
      /** @var NodeInterface|null $node */
      $node = $node_storage->load($node_id);
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $node_title = $node->label();
      $content_type = $node->bundle();
      $node_status = $node->isPublished() ? 'Published' : 'Unpublished';
      $url = $node->toUrl()->setAbsolute()->toString();

      $all_matches = [];

      if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
        $body_text = $node->get('body')->value;

        // Apply regex.
        foreach ($regexes as $regex) {
          preg_match_all($regex, $body_text, $matches);
          if (!empty($matches[0])) {
            $all_matches = array_merge($all_matches, $matches[0]);
          }
        }

        // Remove duplicate matches.
        $all_matches = array_unique($all_matches);
        $matched_text = !empty($all_matches) ? implode(', ', $all_matches) : 'No matches found';

        // Append row only if there are matches.
        if (!empty($all_matches)) {
          $html_output .= '<tr>';
          $html_output .= '<td>' . $node_id . '</td>';
          $html_output .= '<td>' . $node_title . '</td>';
          $html_output .= '<td><a href="' . $url . '" target="_blank">' . $url . '</a></td>';
          $html_output .= '<td>' . $content_type . '</td>';
          $html_output .= '<td>' . $matched_text . '</td>';
          $html_output .= '<td>' . $node_status . '</td>';
          $html_output .= '</tr>';
        }
      }
    }

    $html_output .= '</tbody></table>';
    return new Response($html_output, 200, ['Content-Type' => 'text/html']);
  }

}
