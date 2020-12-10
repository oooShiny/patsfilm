<?php
/**
 * @file
 * Contains Drupal\patsfilm\Form\VideoImportForm.
 */
namespace Drupal\patsfilm\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use GuzzleHttp\Exception\RequestException;

class VideoImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'patsfilm.videoimport',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'video_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['instructions'] = [
      '#type' => 'item',
      '#markup' => '<p class="messages messages--status">Import the latest videos from <a href="https://muse.ai/">Muse.ai</a> by clicking the
                    <strong>Save configuration</strong> button below.</p>'
    ];
    $form['video_defaults'] = [
      '#type' => 'details',
      '#title' => 'Video Defaults',
      '#open' => TRUE
    ];
    $form['video_defaults']['instructions'] = [
      '#type' => 'item',
      '#markup' => '<p class="messages messages--warning">If you want to set defaults for <strong>ALL</strong> of the imported videos, select them below. Otherwise leave them blank.</p>'
    ];
    $form['video_defaults']['game'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Game'),
      '#description' => $this->t('Select in which game these plays occurred.'),
      '#default_value' => '',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['game'],
      ],
    ];

    $form['video_defaults']['unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit'),
      '#description' => $this->t('Select whether the imported plays are of the offense, defense, or special teams.'),
      '#options' => $this->_get_terms('unit'),
      '#empty_value' => ''
    ];

    $form['video_defaults']['play_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Play Type'),
      '#description' => $this->t('Select whether the imported plays are of runs, passes, or something else.'),
      '#options' =>$this->_get_terms('play_type'),
      '#empty_value' => ''
    ];

    $form['video_defaults']['personnel'] = [
      '#type' => 'select',
      '#title' => $this->t('Personnel'),
      '#description' => $this->t('Select the personnel package for the imported plays.'),
      '#options' => $this->_get_terms('personnel'),
      '#empty_value' => ''
    ];

    $form['video_defaults']['formation'] = [
      '#type' => 'select',
      '#title' => $this->t('Formation'),
      '#description' => $this->t('Select the formation for the imported plays.'),
      '#options' => $this->_get_terms('formation'),
      '#empty_value' => ''
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // Get node defaults from form.
    $fields = $form_state->getValues();
    // Get list of videos from muse.ai.
    $muse_vids = $this->get_muse_videos();

    // Get list of videos in Drupal.
    $vid_links = [];
    $nids = \Drupal::entityQuery('node')->condition('type','video')->execute();
    $videos =  Node::loadMultiple($nids);
    foreach ($videos as $video) {
      $vid_links[] = $video->get('field_video_link')->uri;
    }

    // Save the data as new video nodes.
    $operations = [];
    foreach ($muse_vids->videos as $muse_video) {
      $muse_url = 'https://muse.ai/e/' . $muse_video->svid;

      if (!in_array($muse_url, $vid_links)) {
        $url = 'https://muse.ai/e/' . $muse_video->svid;
        $v = [
          'title' => $muse_video->title,
          'url' => $url
        ];
        $operations[] = ['\Drupal\patsfilm\CreateVideoNode::createNode', [$v, $fields]];
      }
    }
    $batch = [
      'title' => 'Creating new Video nodes',
      'operations' => $operations,
      'progress_message' => 'Processed @current out of @total.',
    ];
    batch_set($batch);
  }

  private function get_muse_videos() {
    $url = 'https://muse.ai/api/files/collections/wFQrUwm';
    $client = \Drupal::httpClient();
    $data = NULL;
    try{
      $response = $client->request('GET', $url, [
        'headers' => ['Key' => 'vvjguqSQzGQu9cJXtRg1QfI85f7b9e4f']
      ]);
      $data = $response->getBody()->getContents();
    }
    catch (RequestException $e) {
      watchdog_exception('patsfilm', $e->getMessage());
    }
  return json_decode($data);
  }

  private function _get_terms($taxonomy) {
    $term_array = [];
    $terms =  \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($taxonomy);
    foreach ($terms as $term) {
      $term_array[$term->tid] = $term->name;
    }
    return $term_array;
  }
}
