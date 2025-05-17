<?php

declare(strict_types=1);

namespace Drupal\spotify_artists;

/**
 * Interface for Spotify API client.
 */
interface SpotifyApiClientInterface {

  /**
   * Gets an artist's name by their Spotify ID.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   *
   * @return string
   *   The artist name.
   *
   * @throws \Drupal\spotify_artists\Exception\InvalidArtistIdException
   *   If the artist ID format is invalid.
   * @throws \Drupal\spotify_artists\Exception\ArtistNotFoundException
   *   If the artist cannot be found.
   * @throws \Drupal\spotify_artists\Exception\SpotifyAuthException
   *   If there are authentication issues.
   */
  public function getArtistName(string $artist_id): string;

  /**
   * Gets an artist's details by their Spotify ID.
   *
   * @param string $artist_id
   *
   * @return array
   *   The artist details.
   *
   * @throws \Drupal\spotify_artists\Exception\InvalidArtistIdException
   *   If the artist ID format is invalid.
   * @throws \Drupal\spotify_artists\Exception\ArtistNotFoundException
   *   If the artist cannot be found.
   * @throws \Drupal\spotify_artists\Exception\SpotifyAuthException
   *   If there are authentication issues.
   */
  public function getArtistDetails(string $artist_id): array;

}
