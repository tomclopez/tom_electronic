# Spotify integration Drupal module
A Drupal module that connects to the Spotify Web API to display artist information. 
Admins can enter up to 20 spotify artist IDs in the configuration page.
The block containing these artists will be added to the content region of your theme during the module install.
Logged in users will be shown links to artist details pages.

### setup notes:
Ensure you checkout this repo to a folder named spotify_artists/ in your modules/custom folder
(as the repo name is different to the module name).
```
cd /path/to/drupal/web/modules/custom
git clone git@github.com:tomclopez/tom_electronic.git spotify_artists
```

### Configuration:

Add your spotify API details to your site's settings.php file.
```
$settings['spotify_artists.client_id'] = 'your_client_id';
$settings['spotify_artists.client_secret'] = 'your_client_secret';
```
To obtain these credentials, visit the Spotify developer dashboard and create a new application.

The artist configuration page is located at: admin/config/media/spotify-artists and is also linked from the Configuration page.

### Design decisions:
Data storage as configuration over entities:
  For the requirements, it seemed that simple configuration storage of the artists was all that was required, and resulted in a simpler and more performant solution. It also simplifies import/export and deployment of the feature to other environments. 
  If there had been any suggestion or future possibility of editing information about the artists, or perhaps a much larger number of artists to add, content entities would have been more suitable.
  
  
This content entity alternative solution could have imported the artist data to allow editing, made use of views to generate both the block and the artist details page, added genres to a taxonomy and started to provide it's own related / linked artist information.
  
  
### Next steps:
If I'd had a little more time, I was thinking to implement:
* A search function on the config page, to save finding and pasting artist ids.
* Some more information like "related artists", although it looks like this is not available anymore: https://developer.spotify.com/blog/2024-11-27-changes-to-the-web-api
* An embedded player for the artists top tracks
* Draggable items on the config page to order the block display.
* Unit tests.
* Some code quality tests in a CI Pipeline.
