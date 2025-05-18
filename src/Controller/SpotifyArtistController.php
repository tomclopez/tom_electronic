<?php

declare(strict_types=1);

namespace Drupal\spotify_artists\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\spotify_artists\SpotifyApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Spotify artist details pages.
 */
final class SpotifyArtistController extends ControllerBase {

  protected SpotifyApiClientInterface $spotifyClient;

  /**
   * Constructs a SpotifyArtistController object.
   *
   * @param \Drupal\spotify_artists\SpotifyApiClientInterface $spotify_client
   *   The Spotify API client.
   */
  public function __construct(SpotifyApiClientInterface $spotify_client) {
    $this->spotifyClient = $spotify_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get("spotify_artists.api_client"));
  }

  /**
   * Displays the artist details page.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   *
   * @return array
   *   A render array for the artist details page.
   */
  public function artistDetails(string $artist_id): array {
    try {
      $artist = $this->spotifyClient->getArtistDetails($artist_id);

      return [
        "#theme" => "spotify_artist_details",
        "#artist" => $artist,
        "#attached" => [
          "library" => ["spotify_artists/artist-details"],
        ],
      ];
    }
    catch (\Exception $e) {
      throw new NotFoundHttpException("Artist not found");
    }
  }

  /**
   * Title callback for the artist details page.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   *
   * @return string
   *   The page title.
   */
  public function getArtistTitle(string $artist_id): string {
    try {
      return $this->spotifyClient->getArtistName($artist_id);
    }
    catch (\Exception $e) {
      return "Spotify Artist";
    }
  }

}
