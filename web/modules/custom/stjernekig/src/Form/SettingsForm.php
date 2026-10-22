<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stjernekig\Client\ApodParser;

/**
 * Importer and archive settings.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'stjernekig_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['stjernekig.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('stjernekig.settings');

    $form['source'] = [
      '#type' => 'details',
      '#title' => $this->t('Source'),
      '#open' => TRUE,
    ];
    $form['source']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('WordPress site'),
      '#description' => $this->t('NASA publishes APOD as "image-article" posts on this site. Only change this if NASA moves again.'),
      '#default_value' => $config->get('source.base_url'),
      '#required' => TRUE,
    ];
    $form['source']['category_id'] = [
      '#type' => 'number',
      '#title' => $this->t('APOD category ID'),
      '#default_value' => $config->get('source.category_id'),
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['source']['request_delay_ms'] = [
      '#type' => 'number',
      '#title' => $this->t('Pause between bulk requests (ms)'),
      '#default_value' => $config->get('source.request_delay_ms'),
      '#min' => 0,
    ];
    $form['source']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('HTTP timeout (seconds)'),
      '#default_value' => $config->get('source.timeout'),
      '#min' => 5,
    ];

    $form['media'] = [
      '#type' => 'details',
      '#title' => $this->t('Media and rights'),
      '#open' => TRUE,
    ];
    $form['media']['store_copyrighted'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Download pictures that carry a copyright notice'),
      '#description' => $this->t('Most APOD pictures are copyrighted by the photographer and published by NASA with permission. Untick this to store only the text and a link for those entries; NASA-produced pictures are public domain and are always downloaded.'),
      '#default_value' => $config->get('media.store_copyrighted'),
    ];
    $form['media']['max_bytes'] = [
      '#type' => 'number',
      '#title' => $this->t('Largest file to download (bytes)'),
      '#default_value' => $config->get('media.max_bytes'),
      '#min' => 0,
    ];

    $form['archive'] = [
      '#type' => 'details',
      '#title' => $this->t('Archive'),
      '#open' => TRUE,
    ];
    $form['archive']['start_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First date (YYYY-MM-DD)'),
      '#default_value' => $config->get('archive.start_date'),
      '#size' => 12,
      '#required' => TRUE,
    ];
    $form['archive']['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Cards per page'),
      '#default_value' => $config->get('archive.items_per_page'),
      '#min' => 1,
      '#max' => 200,
    ];
    $form['archive']['feed_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Entries in the RSS feed'),
      '#default_value' => $config->get('archive.feed_items'),
      '#min' => 1,
      '#max' => 100,
    ];
    $form['archive']['site_tagline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tagline'),
      '#default_value' => $config->get('archive.site_tagline'),
    ];

    $form['cron'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron'),
    ];
    $form['cron']['lookback_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to re-check for gaps on each cron run'),
      '#default_value' => $config->get('cron.lookback_days'),
      '#min' => 1,
      '#max' => 365,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!ApodParser::isValidDate((string) $form_state->getValue('start_date'))) {
      $form_state->setErrorByName('start_date', $this->t('Use the YYYY-MM-DD format.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('stjernekig.settings')
      ->set('source.base_url', rtrim((string) $form_state->getValue('base_url'), '/'))
      ->set('source.category_id', (int) $form_state->getValue('category_id'))
      ->set('source.request_delay_ms', (int) $form_state->getValue('request_delay_ms'))
      ->set('source.timeout', (int) $form_state->getValue('timeout'))
      ->set('media.store_copyrighted', (bool) $form_state->getValue('store_copyrighted'))
      ->set('media.max_bytes', (int) $form_state->getValue('max_bytes'))
      ->set('archive.start_date', (string) $form_state->getValue('start_date'))
      ->set('archive.items_per_page', (int) $form_state->getValue('items_per_page'))
      ->set('archive.feed_items', (int) $form_state->getValue('feed_items'))
      ->set('archive.site_tagline', (string) $form_state->getValue('site_tagline'))
      ->set('cron.lookback_days', (int) $form_state->getValue('lookback_days'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
