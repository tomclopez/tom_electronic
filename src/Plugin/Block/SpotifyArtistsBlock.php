<?php

declare(strict_types=1);

namespace Drupal\spotify_artists\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block displaying Spotify artists.
 *
 * @Block(
 *   id = "spotify_artists_list",
 *   admin_label = @Translation("Spotify Artists List"),
 *   category = @Translation("Spotify"),
 * )
 */
final class SpotifyArtistsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new SpotifyArtistsBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('spotify_artists.settings');
    $artists = $config->get('artists') ?: [];

    usort($artists, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    $items = [];

    foreach ($artists as $artist) {
      $artist_id = $artist['id'] ?? '';

      if (empty($artist_id)) {
        continue;
      }

      // Note: In a future implementation, this will fetch the real name
      // via API integration, but for now we just use the ID.
      $artist_name = $artist_id;

      $item = [
        'name' => $artist_name,
        'id' => $artist_id,
      ];

      $items[] = $item;
    }

    return [
      '#theme' => 'spotify_artists_block',
      '#artists' => $items,
      '#attached' => [
        'library' => ['spotify_artists/artists-block'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['config:spotify_artists.settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user.roles', 'route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

}
