# Drush Drupal User

A User agent for authenticating and interacting with a Drupal Site.

Uses cookies to create a persistent session between drush actions.

Supports TFA authentication (drupal.org)

    drush du-login --url=https://drupal.org --username=AzureDiamond --password=hunter2
    drush du-login --url=https://drupal.org --username=AzureDiamond --password=hunter2 --tfa=543211
    drush du-info
    drush du-logout
    
## Status

It doesn't do much *useful* yet, the actual actions:
interacting with the issue Queue etc, are still to come.

## Limitations

It's primarily intended for use with Drupal.org itself, 
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
in your drushrc.php file like so

    # du Options
    $command_specific['du-login']['user'] = 'AzureDiamond';
    $command_specific['du-login']['pass'] = 'hunter2';
     
## Credits

Built with parts inspired by or scrounged from 
[Drush Issue Queue Commands](https://www.drupal.org/project/drush_iq)
and the Drupal Test Suite harness.
However, it's been designed to fit better with Drupal8 and Symfony paradigms,
because that's where the coding style is going this year.
