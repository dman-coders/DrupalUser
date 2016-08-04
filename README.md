# Drush Drupal User

A User agent for authenticating and interacting with a Drupal Site.

Built with parts inspired by or scrounged from 
(Drush Issue Queue Commands)[https://www.drupal.org/project/drush_iq]
and the Drupal Test Suite harness.

To avoid having to enter credentials or connection details
all the time, it's recommended to place the commonly needed options
in your drushrc.php file like so

    # du Options
    $command_specific['du-login']['user'] = 'AzureDiamond';
    $command_specific['du-login']['pass'] = 'hunter2';
     