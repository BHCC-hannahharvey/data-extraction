<?php

namespace Drupal\bhcc_data_extraction\Controller;

use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for DataExtractionController.
 */
class HTMLDataExtractionController extends ControllerBase {


  /**
   * Entity Type Manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The contrustor for the citizenIdController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Data extraction.
   */
  public function extractionData() {

    // Query for the nodes of content types....
    $node_ids = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'html_page')
      ->accessCheck(TRUE)
      ->execute();

    // Do a query for the x number of content type...
    $node_count = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'html_page')
      ->accessCheck(TRUE)
      ->execute();

    // If no content types found display a message.
    if ($node_count == 0) {
      return new Response('<h1>NO universal content nodes found</h1>');
    }

    // If no nodes are found display a message.
    if (empty($node_ids)) {
      return new Response('<h1>No nodes found</h1>');
    }

    // Initialize the HTML table output.
    $html_output = '<p>Node count: ' . $node_count . '</p>';
    $html_output .= '<p>HTML Page Data Extraction<p>';
    $html_output .= '<table border="1" cellpadding="5" cellspacing="0">';
    $html_output .= '<thead>';
    $html_output .= '<tr>';
    $html_output .= '<th>Node ID</th>';
    $html_output .= '<th>Title</th>';
    $html_output .= '<th>Page URL</th>';
    $html_output .= '<th>Content Type</th>';
    $html_output .= '<th>Phone Number</th>';
    $html_output .= '<th>Status</th>';
    $html_output .= '</tr>';
    $html_output .= '</thead>';
    $html_output .= '<tbody>';

    // Loop through the nodes.
    foreach ($node_ids as $node_id) {

      // Get the node ID.
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);

      // Get the node title and the bundle type.
      $node_title = $node->getTitle();
      $content_type = $node->bundle();

      // Check if the node has the bhcc_components field.
      if ($node->hasField('field_section')) {

        $page_sections = $node->get('field_section')->getValue();
        if (!empty($page_sections)) {

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
          $all_matches = [];

          // Loop through the sections of the page
          // to get the fields available.
          foreach ($page_sections as $page_section) {

            $target_id = $page_section['target_id'];

            $paragraph = Paragraph::load($target_id);

            if (empty($paragraph)) {
              continue;
            }

            $paragraph_fields = $paragraph->getFields();

            // List of fields array.
            $wysiwyg_field_types = [
              'text_long',
              'text_with_summary',
            ];

            foreach ($paragraph_fields as $paragraph_field) {

              // Get field type.
              $field_type = $paragraph_field->getFieldDefinition()->getType();

              // If field type is not one of the text types
              // Move onto the next field.
              if (!in_array($field_type, $wysiwyg_field_types)) {
                continue;
              }

              // Check whether the field is empty before continuing.
              if ($paragraph_field->isEmpty()) {
                continue;
              }

              // If it is, continue with evaluation.
              $field_value = $paragraph_field->value;

              // Loop through the regexes array to find matches in the fields.
              foreach ($regexes as $regex) {

                $matches_found = preg_match_all($regex, $field_value, $matches, PREG_SET_ORDER);

                if ($matches_found !== 0) {
                  $all_matches = array_merge($all_matches, $matches);
                }
              }
            }
          }

          // Print out results.
          if (!empty($all_matches)) {
            // Check the URL and convert to string.
            $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id])->setAbsolute()->toString();

            // Print out if the node is published or not.
            $node_status = $node->isPublished() ? 'Published' : 'Unpublished';

            // Print out results into a table.
            $html_output .= '<tr>';
            $html_output .= '<td>' . $node_id . '</td>';
            $html_output .= '<td>' . $node_title . '</td>';
            $html_output .= '<td>' . $url . '</td>';
            $html_output .= '<td>' . $content_type . '</td>';
            $html_output .= '<td>';

            // Loop through the matches and print them out.
            foreach ($all_matches as $match) {
              $html_output .= $match[0];
              $html_output .= '<br />';
            }

            // Print out the node status e.g Published/Unpublished.
            $html_output .= '<td>' . $node_status . '</td>';

            $html_output .= '</td>';
            $html_output .= '</tr>';
          }

        }
      }
    }
    // Close off the table display.
    $html_output .= '</tbody>';
    $html_output .= '</table>';

    // Return the response to the browser.
    return new Response($html_output);
  }

}
