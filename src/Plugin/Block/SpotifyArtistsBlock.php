<?php

declare(strict_types=1);

namespace Drupal\spotify_artists\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\spotify_artists\SpotifyApiClientInterface;
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
  use LoggerChannelTrait;

  protected $messenger;
  protected ConfigFactoryInterface $configFactory;
  protected SpotifyApiClientInterface $spotifyClient;
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a new SpotifyArtistsBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\spotify_artists\SpotifyApiClientInterface $spotify_client
   *   The Spotify API client service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ConfigFactoryInterface $config_factory,
    SpotifyApiClientInterface $spotify_client,
    MessengerInterface $messenger,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->spotifyClient = $spotify_client;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('spotify_artists.api_client'),
      $container->get('messenger'),
      $container->get('current_user'),
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
    $error_count = 0;

    foreach ($artists as $artist) {
      $artist_id = $artist['id'] ?? NULL;
      if ($artist_id === NULL) {
        continue;
      }
      try {
        $artist_name = $this->spotifyClient->getArtistName($artist_id);
      }
      catch (\Exception $e) {
        // Log failure but keep rendering the block.
        $this->getLogger('spotify_artists')->warning('Failed to fetch artist @id: @message', [
          '@id' => $artist_id,
          '@message' => $e->getMessage(),
        ]);
        $error_count++;
        continue;
      }

      $item = [
        'name' => $artist_name,
        'id' => $artist_id,
      ];

      // Only add URL for logged-in users.
      if ($this->currentUser->isAuthenticated()) {
        $item['url'] = Url::fromRoute('spotify_artists.artist_details', ['artist_id' => $artist_id]);
      }

      $items[] = $item;
    }

    if ($error_count > 0 && $this->currentUser->hasPermission('administer spotify artists')) {
      $this->messenger->addWarning($this->t('@count Spotify artists failed to load.', [
        '@count' => $error_count,
      ]));
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
