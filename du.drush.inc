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
    'du-site' => 'The site to interact with. Defaults to https://drupal.org',
  );
  $items['du-info'] = array(
    'description' => 'Show information about the target site.',
    'examples' => array(
      'drush du-info' => 'Get info on the currently chosen site.',
      'drush du-info --du-site=http://groups.drupal.org/' => 'Get info on the named site.',
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
  return $items;
}

/**
 * Du-info command callback.
 */
function drush_du_info() {
  require __DIR__ . '/vendor/autoload.php';

  $du = new DrupalUser();
  $info = $du->getSiteInfo();

  // Cookie persistance is handled at the drush options/temp level.
  $cookies = $du->getCookieJar();
  return $info;
}
