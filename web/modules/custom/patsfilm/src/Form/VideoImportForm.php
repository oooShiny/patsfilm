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
      '#markup' => '<p>Import the latest videos from <a href="https://muse.ai/">Muse.ai</a> by clicking the
                    <strong>Save configuration</strong> button below.</p><p>If you want to set defaults for ALL of the
                    new videos, select them below. Otherwise leave them blank.</p>'
    ];
    $form['video_defaults'] = [
      '#type' => 'details',
      '#title' => 'Video Defaults',
      '#open' => TRUE
    ];
    $form['video_defaults']['game'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Game'),
      '#description' => $this->t('Select in which game these videos occurred.'),
      '#default_value' => '',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['game'],
      ],
    ];
    $unit_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('unit');
    $unit_types = [];
    foreach ($unit_terms as $unit_type) {
      $unit_types[$unit_type->tid] = $unit_type->name;
    }
    $form['video_defaults']['unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit'),
      '#description' => $this->t('Select whether these videos are of the offense, defense, or special teams.'),
      '#options' => $unit_types,
      '#empty_value' => ''
    ];
    $play_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('play_type');
    $play_types = [];
    foreach ($play_terms as $play_type) {
      $play_types[$play_type->tid] = $play_type->name;
    }
    $form['video_defaults']['play_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Play Type'),
      '#description' => $this->t('Select whether these videos are of runs, passes, or something else.'),
      '#options' => $play_types,
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

}
