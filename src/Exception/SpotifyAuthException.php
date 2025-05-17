<?php

namespace Drupal\spotify_artists\Exception;

/**
 * Exception thrown when there are API authentication issues.
 */
class SpotifyAuthException extends \Exception {

  /**
   * Constructs a new SpotifyAuthException.
   *
   * @param string $message
   *   The exception message.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable.
   */
  public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = NULL) {
    if (empty($message)) {
      $message = 'Spotify API authentication failed.';
    }
    parent::__construct($message, $code, $previous);
  }

}
