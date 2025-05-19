<?php

declare(strict_types=1);

namespace Drupal\spotify_artists\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks if a Spotify artist is configured in the system.
 */
class ConfiguredArtistAccessChecker implements AccessInterface {
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a ConfiguredArtistAccessChecker.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Checks access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $artist_id
   *   The artist ID parameter from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $artist_id = '') {
    if (empty($artist_id)) {
      return AccessResult::forbidden()->setCacheMaxAge(0);
    }

    $config = $this->configFactory->get('spotify_artists.settings');
    $configured_artists = $config->get('artists') ?? [];
    $allowed_ids = array_column($configured_artists, 'id');

    if (in_array($artist_id, $allowed_ids)) {
      return AccessResult::allowed()
        ->addCacheTags(['config:spotify_artists.settings']);
    }

    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

}
