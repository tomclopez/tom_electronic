<?php

declare(strict_types=1);

namespace Drupal\spotify_artists;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\spotify_artists\Exception\ArtistNotFoundException;
use Drupal\spotify_artists\Exception\InvalidArtistIdException;
use Drupal\spotify_artists\Exception\SpotifyAuthException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for interacting with the Spotify API.
 */
class SpotifyApiClient implements SpotifyApiClientInterface {
  use StringTranslationTrait;

  protected const API_BASE_URL = "https://api.spotify.com/v1";

  protected const AUTH_URL = "https://accounts.spotify.com/api/token";

  protected const SPOTIFY_CACHE = "spotify_artists";

  protected const SPOTIFY_DETAILS_CACHE = self::SPOTIFY_CACHE . ":details:";

  protected const SPOTIFY_TOKENS_CACHE = self::SPOTIFY_CACHE . ":access_token";

  protected ClientInterface $httpClient;

  protected CacheBackendInterface $cache;

  protected ConfigFactoryInterface $configFactory;

  protected $logger;

  /**
   * Constructs a new SpotifyApiClient.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface $http_client,
    CacheBackendInterface $cache,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get("spotify_artists");
  }

  /**
   * {@inheritdoc}
   */
  public function getArtistName(string $artist_id): string {
    $details = $this->getArtistDetails($artist_id);
    return $details["name"];
  }

  /**
   * {@inheritdoc}
   */
  public function getArtistDetails(string $artist_id): array {
    $cached_data = $this->getCachedArtistDetails($artist_id);
    if ($cached_data !== NULL) {
      return $cached_data;
    }

    $token = $this->getAccessToken();

    $data = $this->fetchArtistFromApi($artist_id, $token);
    $artist_details = $this->processArtistData($data);

    $this->cacheArtistDetails($artist_id, $artist_details);
    return $artist_details;

  }

  /**
   * Fetches artist data from the Spotify API.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   * @param string $token
   *   The API access token.
   *
   * @return array
   *   The raw API response data.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function fetchArtistFromApi(string $artist_id, string $token): array {
    try {
      $response = $this->httpClient->request('GET', static::API_BASE_URL . "/artists/{$artist_id}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
        ],
      ]);

      return Json::decode($response->getBody()->getContents());
    }
    catch (ClientException $e) {
      $this->handleClientException($e, $artist_id);
    }
  }

  /**
   * Handles client exceptions from the Spotify API.
   *
   * @param \GuzzleHttp\Exception\ClientException $e
   *   The client exception.
   * @param string $artist_id
   *   The Spotify artist ID.
   *
   * @throws \Drupal\spotify_artists\Exception\InvalidArtistIdException
   * @throws \Drupal\spotify_artists\Exception\ArtistNotFoundException
   * @throws \Drupal\spotify_artists\Exception\SpotifyAuthException
   */
  protected function handleClientException(ClientException $e, string $artist_id): never {
    $response = $e->getResponse();
    $status_code = $response->getStatusCode();
    $error_content = Json::decode($response->getBody()->getContents());

    $error_message = $error_content['error']['message'] ?? 'Unknown error';

    $this->logger->error('Spotify API error: @status @message for artist ID @id', [
      '@status' => $status_code,
      '@message' => $error_message,
      '@id' => $artist_id,
    ]);

    if ($status_code === 401 || $status_code === 403) {
      throw new SpotifyAuthException('Authentication error: ' . $error_message);
    }

    if ($status_code === 400 && $error_message === 'Invalid base62 id') {
      // Invalid ID format.
      $this->cacheInvalidId($artist_id);
      throw new InvalidArtistIdException($artist_id);
    }

    if ($status_code === 404) {
      // Valid format but non-existent artist.
      $this->cacheArtistNotFound($artist_id);
      throw new ArtistNotFoundException($artist_id);
    }

    throw $e;

  }

  /**
   * Gets cached artist details if available.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   *
   * @return array|null
   *   The cached artist details, or NULL if not cached.
   *
   * @throws \Drupal\spotify_artists\Exception\ArtistNotFoundException
   * @throws \Drupal\spotify_artists\Exception\InvalidArtistIdException
   */
  protected function getCachedArtistDetails(string $artist_id): ?array {
    $cache_id = $this->getArtistCacheId($artist_id);
    $cache = $this->cache->get($cache_id);

    if (!$cache) {
      return NULL;
    }

    $details = $cache->data;

    if (isset($details["invalid"]) && $details["invalid"] === TRUE) {
      $error_type = $details["error_type"] ?? "not_found";

      if ($error_type === "invalid_format") {
        throw new InvalidArtistIdException(
          $artist_id,
          $details["message"] ?? "Invalid artist ID format"
        );
      }
      else {
        throw new ArtistNotFoundException(
          $artist_id,
          $details["message"] ?? "Artist not found"
              );
      }
    }

    return $details;
  }

  /**
   * Caches artist details.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   * @param array $details
   *   The artist details to cache.
   */
  protected function cacheArtistDetails(string $artist_id, array $details): void {
    $cache_id = $this->getArtistCacheId($artist_id);
    $this->cache->set($cache_id, $details, CacheBackendInterface::CACHE_PERMANENT);
  }

  /**
   * Caches valid id with no artist match.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   */
  protected function cacheArtistNotFound(string $artist_id): void {
    $cache_id = $this->getArtistCacheId($artist_id);
    $invalid_data = [
      'invalid' => TRUE,
      'message' => "No Artist found",
      'artist_id' => $artist_id,
    ];

    // Only cache for a day, a new artist might be added with this ID.
    $this->cache->set($cache_id, $invalid_data, time() + 86400);
  }

  /**
   * Caches an invalid artist id.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   */
  protected function cacheInvalidId(string $artist_id): void {
    $cache_id = $this->getArtistCacheId($artist_id);
    $invalid_data = [
      'invalid' => TRUE,
      'message' => "Invalid artist ID format",
      'artist_id' => $artist_id,
    ];

    $this->cache->set($cache_id, $invalid_data, CacheBackendInterface::CACHE_PERMANENT);
  }

  /**
   * Get artist cache id.
   *
   * @param string $artist_id
   *   The Spotify artist ID.
   */
  protected function getArtistCacheId(string $artist_id): string {
    return static::SPOTIFY_DETAILS_CACHE . $artist_id;
  }

  /**
   * Processes raw API data into a structured artist details array.
   *
   * @param array $data
   *   The raw API response data.
   *
   * @return array
   *   The processed artist details.
   */
  protected function processArtistData(array $data): array {
    return [
      'id' => $data['id'] ?? '',
      'name' => $data['name'] ?? 'Unknown artist',
      'genres' => $data['genres'] ?? [],
      'image' => $data['images'][0]['url'] ?? '',
      'popularity' => $data['popularity'] ?? 0,
      'followers' => $data['followers']['total'] ?? 0,
    ];
  }

  /**
   * Gets a valid access token for the Spotify API.
   *
   * @return string
   *   The access token.
   *
   * @throws \Drupal\spotify_artists\Exception\SpotifyAuthException
   *   If authentication fails.
   */
  private function getAccessToken(): ?string {
    $cache_id = static::SPOTIFY_TOKENS_CACHE;
    $cache = $this->cache->get($cache_id);

    if ($cache) {
      return $cache->data;
    }

    $client_id = Settings::get("spotify_artists.client_id");
    $client_secret = Settings::get("spotify_artists.client_secret");

    if (
      $client_id === NULL ||
      $client_id === "" ||
      $client_secret === NULL ||
      $client_secret === ""
    ) {
      $message = "Spotify API credentials not configured.";
      $this->logger->error($message);
      throw new SpotifyAuthException($message);
    }

    try {
      $response = $this->httpClient->request("POST", static::AUTH_URL, [
        "form_params" => [
          "grant_type" => "client_credentials",
        ],
        "headers" => [
          "Authorization" =>
          "Basic " . base64_encode("{$client_id}:{$client_secret}"),
          "Content-Type" => "application/x-www-form-urlencoded",
        ],
      ]);

      $data = Json::decode($response->getBody()->getContents());

      if (!isset($data["access_token"]) || !isset($data["expires_in"])) {
        throw new SpotifyAuthException();
      }

      $token = $data["access_token"];
      $expires = $data["expires_in"];

      // Token expires in an hour, cache for 59 minutes.
      $this->cache->set($cache_id, $token, time() + 3540);

      return $token;
    }
    catch (GuzzleException $e) {
      $this->logger->error("Spotify API request error: @message", [
        "@message" => $e->getMessage(),
      ]);
      throw new SpotifyAuthException();
    }
  }

}
