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
    'site'     => array(
      'description' => 'identifier of a site definition already defined in the du-sites array in your drushrc',
      'example' => '@d.o',
    ),
    'uri'      => array(
      'description' => 'The site to interact with. Defaults to https://www.drupal.org',
      'example'     => 'https://drupal.org',
    ),
    'username' => array(
      'description' => 'The username to authenticate as.',
    ),
    'password' => array(
      'description' => 'The password to authenticate the username with.',
    ),

  );
  $items['du-info'] = array(
    'description'        => 'Show information about the target site.',
    'examples'           => array(
      'drush du-info'                                 => 'Get info on the currently chosen site.',
      'drush du-info --uri=http://groups.drupal.org/' => 'Get info on the named site.',
    ),
    'arguments'          => array(),
    'required-arguments' => FALSE,
    'options'            => array(
      'pipe' => 'Print the full issue info data structure.',
    ) + $du_global_options,

    'outputformat'     => array(
      'default'     => 'key-value',
      'pipe-format' => 'json',
    ),
    'output-data-type' => 'format-list',

    'aliases'   => array('dui'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'topics'    => array('docs-du-commands'),
  );

  $items['du-get-page'] = array(
    'description'        => 'Get web page from site.',
    'examples'           => array(
      'drush du-get-page /project/issues/user' => 'Get content from the currently connected site',
    ),
    'arguments'          => array(
      'path',
    ),
    'required-arguments' => FALSE,
    'options'            => array(
      'raw' => 'Print the full html source.',
    ) + $du_global_options,

    'outputformat'     => array(
      'default'     => 'key-value',
      'pipe-format' => 'json',
    ),
    'output-data-type' => 'format-list',

    'aliases'   => array('duget'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'topics'    => array('docs-du-commands'),
  );

  $items['du-logout'] = array(
    'description' => 'Logs out of the site, if authenication was being persistent.',
    'options'     => array() + $du_global_options,
    'bootstrap'   => DRUSH_BOOTSTRAP_DRUSH,
    'topics'      => array('docs-du-commands'),
  );

  $items['docs-du'] = array(
    'description'        => 'Connect to and interact with Drupal sites such as drupal.org.',
    'hidden'             => TRUE,
    'topic'              => TRUE,
    'bootstrap'          => DRUSH_BOOTSTRAP_DRUSH,
    'callback'           => 'drush_print_file',
    'callback arguments' => array(dirname(__FILE__) . '/README.md'),
    'aliases'            => array('docs-du-commands'),
  );

  return $items;
}

/**
 * Du-info command callback.
 */
function drush_du_info() {
  $du = drush_du_get_du();
  $info = $du->getSiteInfo();
  return $info;
}

/**
 * Du-info command callback.
 */
function drush_du_logout() {
  $du = drush_du_get_du();
  $info = $du->logout();
  // This also means drop memory of the currently attached site.
  drush_du_remember('@none');
  return $info;
}

/**
 * Du-get-page command callback.
 */
function drush_du_get_page($path) {
  $du = drush_du_get_du();
  $info = $du->getPage($path);
  return $info;
}

/**
 * Initialize a DrupalUser client object.
 *
 * On the drush side, we remember what site you logged in to, and
 * will continue using those details for subsequent transactions until
 * you switch to a new one or logout.
 *
 * On the Client side, the session is remembered via cookies.
 */
function drush_du_get_du() {
  require __DIR__ . '/vendor/autoload.php';
  // Transfer each of the supplied drush options into the site spec.
  // Use introspection to keep all available options in sync always.
  $my_command = drush_get_command();
  $my_options = $my_command['options'];
  $du_options = array();

  // If given a du_site info spec id,
  // load that first (then overwrite if more options are given).
  // Otherwise check for memory of a previous connection.
  $site_id = '';
  if ($default_id = drush_get_option('du-default-site')) {
    // 'du-default-site' is the default arg that may be set in drushrc.
    // This should lose against any other options.
    $site_id = $default_id;
  }
  if ($remembered_site = drush_du_remember()) {
    $site_id = $remembered_site;
  }
  if ($specified_id = drush_get_option('site')) {
    $site_id = $specified_id;
    drush_du_remember($specified_id);
  }

  if ($site_id) {
    // Preload any site connection profiles from drushrc.
    $du_sites = drush_get_option('du-sites');
    if (isset($du_sites[$site_id])) {
      $du_options = $du_sites[$site_id];
    }
  }

  foreach ($my_options as $option => $description) {
    if ($val = drush_get_option($option)) {
      $du_options[$option] = $val;
    }
  }

  $du = new DrupalUser($du_options);
  // If we have old cookies, grab them now.
  if (($cjf = drush_du_cookiejar_file())) {
    $du->readCookieJarFile($cjf);
  }
  return $du;
}

/**
 * Fetch the path to the current cookie storage.
 *
 * @param string $key
 * @return bool|string
 */
function drush_du_cookiejar_file($key = 'default') {
  if ($cache_dir = drush_directory_cache('cookie')) {
    $cache_name = str_replace(array(':', '/', '?', '='), '-', $key);
    return $cache_dir . "/du_cookie-" . $cache_name;
  }
  else {
    return FALSE;
  }
}


/**
 * Set the DU_SITE variable.
 *
 * By writing it out to a temporary file that we
 * then source for persistent site switching.
 *
 * Taken from drush site-set.
 *
 * @param string $site
 *  A valid site specification.
 *  If NULL, just return the one we remember.
 *
 * @return string
 *   Site identifier.
 */
function drush_du_remember($site = NULL) {
  if ($filename = drush_sitealias_get_envar_filename('drush-du-site')) {
    $last_site_filename = drush_sitealias_get_envar_filename('drush-du-prev-site-');
    if ($site == '-') {
      if (file_exists($last_site_filename)) {
        $site = file_get_contents($last_site_filename);
      }
      else {
        $site = '@none';
      }
    }
    $du_sites = drush_get_option('du-sites');

    if (file_exists($filename)) {
      $prev_site = file_get_contents($filename);
    }
    else {
      $prev_site = NULL;
    }

    if ($site === NULL) {
      // When not defined, retrieve the last one we remember.
      $site = $prev_site;
    }
    if (isset($du_sites[$site]) || $site == '@none') {
      // $site is a valid id.
      if (file_exists($filename) && ($site != $prev_site)) {
        @unlink($last_site_filename);
        @rename($filename, $last_site_filename);
      }
      $success_message = dt("Site set to !site", array('!site' => $site));
      if ($site == '@none') {
        if (drush_delete_dir($filename)) {
          drush_print($success_message);
        }
      }
      elseif (drush_mkdir(dirname($filename), TRUE)) {
        if (file_put_contents($filename, $site)) {
          drush_print($success_message);
        }
      }
    }
    else {
      drush_set_error('DRUPAL_SITE_NOT_FOUND', dt("Could not find a site definition for !site.", array('!site' => $site)));
    }
  }
  return $site;
}

