<?php

namespace Drupal\spotify_artists\Exception;

/**
 * Exception thrown when a Spotify artist cannot be found.
 */
class ArtistNotFoundException extends \Exception {

  /**
   * Constructs a new ArtistNotFoundException.
   *
   * @param string $artist_id
   *   The Spotify artist ID that couldn't be found.
   * @param string $message
   *   The exception message.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable.
   */
  public function __construct(string $artist_id, string $message = '', int $code = 0, ?\Throwable $previous = NULL) {
    if (empty($message)) {
      $message = sprintf('Spotify artist with ID "%s" could not be found.', $artist_id);
    }
    parent::__construct($message, $code, $previous);
  }

}
