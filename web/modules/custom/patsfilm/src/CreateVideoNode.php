<?php

namespace Drupal\patsfilm;


use Drupal\node\Entity\Node;

class CreateVideoNode {
  public static function createNode($video, $fields, &$context) {
    $results = [];

    // Create the node.
    $node = Node::create([
      'type' => 'video',
      'title' => $video['title'],
      'field_video_link' => $video['url'],
      'field_unit' => $fields['unit'] ?: NULL,
      'field_play_type' => $fields['play_type'] ?: NULL,
      'field_game' => $fields['game'][0]['target_id'] ?: NULL,
    ]);

    $results[] = $node->save();
    $context['results'] = $results;
  }
}
