<?php

/**
 * @file
 * Drupal User Agent, for interacting with Drupal style websites.
 */

use Drupal\du\DrupalUser;

/**
 * Implements hook_drush_command().
 */
function du_drush_command() {
  $du_global_options = array(
    'url' => array(
      'description' => 'The site to interact with. Defaults to https://www.drupal.org',
      'example' => 'https://drupal.org',
    ),
    'user' => array(
      'description' => 'The username to authenticate as.',
    ),
    'pass' => array(
      'description' => 'The password to authenticate the username with.'
    ),

  );
  $items['du-info'] = array(
    'description' => 'Show information about the target site.',
    'examples' => array(
      'drush du-info' => 'Get info on the currently chosen site.',
      'drush du-info --url=http://groups.drupal.org/' => 'Get info on the named site.',
    ),
    'arguments' => array(),
    'required-arguments' => FALSE,
    'options' => array(
      'pipe' => 'Print the full issue info data structure.',
    ) + $du_global_options,

    'outputformat' => array(
      'default' => 'key-value',
      'pipe-format' => 'json',
    ),
    'output-data-type' => 'format-list',

    'aliases' => array('dui'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'topics' => array('docs-du-commands'),
  );

  $items['docs-du'] = array(
    'description' => 'Connect to and interact with Drupal sites such as drupal.org.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(dirname(__FILE__) . '/README.md'),
    'aliases' => array('docs-du-commands'),
  );

  return $items;
}

/**
 * Du-info command callback.
 */
function drush_du_info() {
  $du = drush_du_get_du();
  $info = $du->getSiteInfo();

  // Cookie persistance is handled at the drush options/temp level.
  $cookies = $du->getCookieJar();
  return $info;
}

/**
 * Initialize a DrupalUser client object.
 */
function drush_du_get_du() {
  require __DIR__ . '/vendor/autoload.php';
  // Transfer each of the supplied drush options into the site spec.
  // Use introspection to keep all available options in sync always.
  $my_command = drush_get_command();
  $my_options = $my_command['options'];
  $du_options = array();
  foreach ($my_options as $option => $description) {
    if ($val = drush_get_option($option)) {
      $du_options[$option] = $val;
    }
  }
  $du = new DrupalUser($du_options);
  // If we have old cookies, grab them now.
  return $du;
}