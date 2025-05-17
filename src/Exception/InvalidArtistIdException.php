<?php

namespace Drupal\spotify_artists\Exception;

/**
 * Exception thrown when an artist ID format is invalid.
 */
class InvalidArtistIdException extends \Exception {

  /**
   * Constructs a new InvalidArtistIdException.
   *
   * @param string $artist_id
   *   The invalid Spotify artist ID.
   * @param string $message
   *   The exception message.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable.
   */
  public function __construct(string $artist_id, string $message = '', int $code = 0, ?\Throwable $previous = NULL) {
    if (empty($message)) {
      $message = sprintf('Spotify artist ID "%s" has an invalid format.', $artist_id);
    }
    parent::__construct($message, $code, $previous);
  }

}
