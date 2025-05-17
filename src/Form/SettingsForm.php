<?php

declare(strict_types=1);

namespace Drupal\spotify_artists\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Spotify Artists module.
 */
final class SettingsForm extends ConfigFormBase {

  private const MAX_ARTISTS = 20;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'spotify_artists_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['spotify_artists.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('spotify_artists.settings');
    $stored_artists = $config->get('artists') ?? [];

    if (!$form_state->has('artist_values')) {
      $artist_values = [];
      foreach ($stored_artists as $artist) {
        $artist_values[] = $artist['id'] ?? NULL;
      }
      $form_state->set('artist_values', $artist_values);
    }

    $artist_values = $form_state->get('artist_values');
    $num_artists = count($artist_values);
    $max_reached = ($num_artists >= self::MAX_ARTISTS);

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Enter Spotify artist IDs. You can find an artist ID from their Spotify URL or URI.') . '</p>',
    ];

    $form['artists_container'] = [
      '#type' => 'container',
      '#prefix' => '<div id="artists-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['artists_container']['artists'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Artist IDs'),
      '#tree' => TRUE,
    ];

    foreach ($artist_values as $delta => $artist_id) {
      $form['artists_container']['artists'][$delta] = [
        '#type' => 'textfield',
        '#title' => $this->t('Artist ID @num', ['@num' => $delta + 1]),
        '#default_value' => $artist_id,
        '#description' => $this->t('Enter a valid Spotify artist ID (22 characters, alphanumeric).'),
        '#maxlength' => 22,
        '#size' => 30,
      ];
    }

    if ($max_reached) {
      $form['artists_container']['max_warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' .
        $this->t('Maximum of @max artists reached.', ['@max' => self::MAX_ARTISTS]) .
        '</div>',
        '#weight' => 5,
      ];
    }

    $form['artists_container']['actions'] = [
      '#type' => 'actions',
      '#weight' => 10,
    ];

    $form['artists_container']['actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another artist'),
      '#submit' => ['::addArtist'],
      '#ajax' => [
        'callback' => '::updateArtistsWrapper',
        'wrapper' => 'artists-wrapper',
      ],
      '#disabled' => $max_reached,
      '#limit_validation_errors' => [],
    ];

    if ($num_artists > 1) {
      $form['artists_container']['actions']['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove last artist'),
        '#submit' => ['::removeArtist'],
        '#ajax' => [
          'callback' => '::updateArtistsWrapper',
          'wrapper' => 'artists-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for adding another artist field.
   */
  public function addArtist(array &$form, FormStateInterface $form_state): void {
    $artist_values = $form_state->get('artist_values');

    if (count($artist_values) < self::MAX_ARTISTS) {
      $artist_values[] = NULL;
      $form_state->set('artist_values', $artist_values);
    }

    $form_state->setRebuild();
  }

  /**
   * Callback for removing the last artist field.
   */
  public function removeArtist(array &$form, FormStateInterface $form_state): void {
    $artist_values = $form_state->get('artist_values');

    if (count($artist_values) > 1) {
      array_pop($artist_values);
      $form_state->set('artist_values', $artist_values);
    }

    $form_state->setRebuild();
  }

  /**
   * AJAX callback to update the artists wrapper.
   */
  public function updateArtistsWrapper(array &$form, FormStateInterface $form_state): array {
    return $form['artists_container'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if ($form_state->getTriggeringElement()['#id'] === 'edit-submit') {
      $artist_values = $form_state->getValue('artists', []);

      if (count($artist_values) > self::MAX_ARTISTS) {
        $form_state->setErrorByName(
          'artists',
          $this->t('You cannot have more than @max artists.', [
            '@max' => self::MAX_ARTISTS,
          ])
        );
        return;
      }

      foreach ($artist_values as $delta => $artist_id) {
        $artist_id = trim($artist_id ?? '');

        if ($artist_id === '') {
          continue;
        }

        if (!preg_match('/^[a-zA-Z0-9]{22}$/', $artist_id)) {
          $form_state->setErrorByName(
            "artists][$delta",
            $this->t('The artist ID must be 22 characters and contain only letters and numbers.')
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $artist_values = $form_state->getValue('artists', []);
    $artists = [];

    foreach ($artist_values as $weight => $artist_id) {
      $artist_id = trim($artist_id ?? '');

      if ($artist_id !== '') {
        $artists[] = [
          'id' => $artist_id,
          'weight' => $weight,
        ];
      }
    }

    $this->config('spotify_artists.settings')
      ->set('artists', $artists)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
