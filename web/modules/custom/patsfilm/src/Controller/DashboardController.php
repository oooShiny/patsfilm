<?php

namespace Drupal\patsfilm\Controller;


use Drupal\Core\Controller\ControllerBase;

class DashboardController extends ControllerBase {
  /**
   * Display the markup.
   *
   * @return array
   */
  public function content() {
    $u = \Drupal::currentUser();
    $user = $u->getDisplayName();

    return [
      '#theme' => 'patsfilm_dashboard',
      '#dashuser' => $user,
    ];
  }
}
