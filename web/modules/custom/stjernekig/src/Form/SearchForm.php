<?php

declare(strict_types=1);

namespace Drupal\stjernekig\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * A GET form so search results have shareable URLs.
 */
final class SearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'stjernekig_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('stjernekig.search')->toString();
    $form['#attributes']['class'][] = 'apod-search-form';
    $form['#attributes']['role'] = 'search';
    $form['#token'] = FALSE;
    $form['#after_build'][] = [static::class, 'removeInternals'];

    $form['q'] = [
      '#type' => 'search',
      '#title' => $this->t('Search the archive'),
      '#title_display' => 'invisible',
      '#default_value' => $this->getRequest()->query->get('q', ''),
      '#placeholder' => $this->t('Andromeda, aurora, Hubble, comet…'),
      '#size' => 40,
      '#maxlength' => 128,
      '#attributes' => ['autofocus' => 'autofocus'],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];
    return $form;
  }

  /**
   * Keep form_build_id and friends out of the query string.
   */
  public static function removeInternals(array $form): array {
    foreach (['form_build_id', 'form_id', 'form_token'] as $key) {
      if (isset($form[$key])) {
        $form[$key]['#access'] = FALSE;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Handled by the GET request itself.
  }

}
