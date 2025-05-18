# Spotify integration Drupal module
A Drupal module that connects to the Spotify Web API to display artist information.

### setup notes:
Ensure you checkout this repo to a folder named spotify_artists/ in your modules/custom folder
(as the repo name is different to the module name).
```
cd /path/to/drupal/web/modules/custom && git clone git@github.com:tomclopez/tom_electronic.git spotify_artists
```
### Configuration:

Add your spotify API details to the settings file.
```
$settings['spotify_artists.client_id'] = 'your_client_id';
$settings['spotify_artists.client_secret'] = 'your_client_secret';
```
To obtain these credentials, visit the Spotify developer dashboard and create a new application.


Configuration page is at: admin/config/media/spotify-artists
