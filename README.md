# Drush Drupal User

A User agent for authenticating and interacting with a Drupal Site.

Uses cookies to create a persistent session between drush actions.

Supports TFA authentication (drupal.org) 2016

Different profiles for different sites can be configured. 
These profiles may be stored locally by adding them to you drushrc.

Examples:

    drush du-login --uri=https://drupal.org --username=AzureDiamond --password=hunter2
    drush du-login --uri=https://drupal.org --username=AzureDiamond --password=hunter2 --tfa=543211
    drush du-info
    drush du-get-page /user --format=json
    drush du-logout

(Use drush help to find more about the options)

## Features

Persistent login. After authenticating once, a session cookie is retained.
This is expected to make subsequent web requests work immediately.
Web requests that require authentication should check for authentication
status and automatically log in if they can.
    
## Status

It doesn't do much *useful* yet, the actual actions:
interacting with the issue Queue etc, are still to come.

The "DrupalUser" component is site-agnostic, so does not contain any actions
that are specific to any one website.
It should be *extended* to create a site-specific user agent that contains
site-specific utility actions.

## Limitations

It's originally intended for use with Drupal.org itself, 
 and other reasonably standard Drupal sites. 
It has some assumptions about the way Drupal works embedded in it.
It's really expected to just be a d.o-specific client, but incidentally
 applies pretty well to other sites as well.

I'm not sure how volatile this is with regards to version requirements
 of drush. It was developed with drush8 in mind, but actually runs
 OK on drush 6.

Unusually for drush, this is a PSR-4 style object, with most of the
 logic packed into an object and using the Goutte Client
 on the Symfony BrowserKit

## Configs 

To avoid having to enter credentials or connection details
all the time, it's recommended to place the commonly needed options
in your drushrc.php file like so:

    $ DrupalUser web client settings.
    # If you want to be able to connect to many sites, use this keyed array.
    # Usage: drush du-info site=d7
    $options['du-sites'] = array(
      '@d.o' => array(
        'uri'      => 'https://www.drupal.org',
        'username' => 'AzureDiamond',
        'password' => 'hunter2',
        'content_selector' => '#content',
      ),
      '@d7' => array(
        'uri'      => 'http://dev.drupal7.dd:8083',
        'username' => 'admin',
        'password' => 'demopass',
      ),
    );
    # Optional: Set the default site to use every time.
    # $options['du-default-site'] = '@d.o';

### Site Options

url:
  required

username: 
  required if doing logins     

password : 
  required if doing logins     

content_selector :
  DOM identifier for extracting the 'content' of a page
  when scraping. This is often theme-specific.
  EG `body > div[@class='main']`
  Default: `#content`,

     
## Credits

Built with parts inspired by or scrounged from 
[Drush Issue Queue Commands](https://www.drupal.org/project/drush_iq)
and the Drupal Test Suite harness.
However, it's been designed to fit better with Drupal8 and Symfony paradigms,
because that's where the coding style is going this year.
