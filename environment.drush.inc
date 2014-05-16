<?php
/**
 * @file
 */

/**
 * Implementation of hook_drush_command().
 */
function environment_drush_command() {
  $items['environment'] = array(
    'description' => 'Enable settings for a specific environment.',
    'arguments' => array(
      'environment-name' => 'Predefined environment names created in your env.drushrc.php files.  Combine multiple environment definitions using the plus(+) sign.',
    ),
    'examples' => array(
      'drush env dev' => 'Run the dev environment settings.',
      'drush env dev+chris' => 'Run the dev settings then run chris\'s settings.',
    ),
    'aliases' => array('env'),
  );

  $items['environment-list'] = array(
    'description' => 'List all available environments.',
    'arguments' => array(
      'name' => '(optional) The name of an environment definition.',
    ),
    'examples' => array(
      'drush env-list' => 'Show all available environments.',
      'drush env-list dev' => 'Show the detials of the \'dev\' environment definition.',
    ),
    'aliases' => array('env-list'),
  );

  $items['environment-dir'] = array(
    'description' => 'Show the directory where env definitions should be placed for this site.',
    'options' => array(
      'make' => 'Create the directory if it does not already exist.',
    ),
    'examples' => array(
      'drush env-dir' => 'Print the directory path.',
    ),
    'aliases' => array('env-dir'),
  );

  return $items;
}

/**
 * Drush command callback function.
 */
function drush_environment($env = NULL) {
  $environments = array();

  // Load all available environments for the site.
  _drush_environment_load_env_site($environments);

  // Make sure we found at least one env.
  if (empty($environments)) {
    return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('Unable to find any environment definitions.'));
  }

  // The user needs to tell us which one they want to employ.
  if (!$env) {
    return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('You need to specify at lease one environment definition to employ.'));
  }

  // Separate any combined envs.
  $envs = explode('+', $env);

  // Make sure the ones they specified exists.  Bail out if any don't exist.
  foreach ($envs as $_env) {
    if (!isset($environments[$_env])) {
      return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('Unable to locate the environment definition for \'@env.\'', array('@env' => $_env)));
    }
  }

  // Merge multiple definitions
  $exec = array();
  if (count($envs) == 1) {
    $exec = $environments[$envs[0]];
  }
  else {
    foreach ($envs as $_env) {
      $exec = array_merge_recursive_distinct($exec, $environments[$_env]);
    }
  }

  // Execute the env settings.
  foreach ($exec as $type => $options) {
    $function = "_drush_environment_{$type}_management";
    if (function_exists($function)) {
      $function($options);
    }
  }
}

/**
 * Command callback.
 */
function drush_environment_list($env = NULL) {
  $environments = array();
  _drush_environment_load_env_site($environments);

  // Print the details of a given env definition if specified.
  if ($env && isset($environments[$env])) {
    drush_print_r($environments[$env]);
  }
  // Print a list of the available env names
  else {
    foreach (array_keys($environments) as $name) {
      drush_print($name);
    }
  }
}

/**
 * Command callback.
 */
function drush_environment_dir() {
  $site_path = _drush_environment_site_dir();
  drush_print(dt('Add your env definition files here: @path', array('@path' => $site_path)));

  // Optionally create the directory
  if (drush_get_option('make')) {
    drush_mkdir($site_path);
  }
}

/**
 * Helper function to load all the available site environments.
 */
function _drush_environment_load_env_site(&$environments) {
  $site_path = _drush_environment_site_dir();

  // Scan for environment files
  foreach (drush_scan_directory($site_path, '/env\.drushrc\.php$/') as $file) {
    _drush_environment_load_env_file($environments, $file);
  }
}

/**
 * Get the path to the site dir.
 */
function _drush_environment_site_dir() {
  $site_path = '';

  // Check for aliases
  $site_name = drush_sitealias_bootstrapped_site_name();
  $alias     = drush_sitealias_get_record("@{$site_name}");

  if (floatval(DRUSH_VERSION) > 6.2) {
    $site_path = drush_sitealias_local_site_path($alias);
  }
  else {
    $supposed_path = drush_sitealias_local_site_path($alias);
    $hostname      = drush_sitealias_uri_to_site_dir($alias['uri']);

    $site_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
    if (file_exists($site_root . '/sites/sites.php')) {
      $sites = array();

      include($site_root . '/sites/sites.php');

      // If we found a match in sites.php and the supposed path is sites/default
      // then replace 'default' with the matching directory.
      if (isset($sites[$hostname]) && substr($supposed_path, -8) == '/default') {
        $site_path = str_replace('/default', "/{$sites[$hostname]}", $supposed_path);
      }
      else {
        $site_path = $supposed_path;
      }
    }
    else {
      $site_path = $supposed_path;
    }
  }

  // If the site dir is 'default', switch to 'all'
  if (substr($site_path, -8) == '/default') {
    $site_path = str_replace('/default', '/all', $site_path);
  }

  return $site_path . '/drush';
}

/**
 * Drush command helper to load environments from files.
 */
function _drush_environment_load_env_file(&$environments, $file) {
  // Look for a group file
  if (stripos($file->name, 'env') === 0) {
    $environments = array_merge($environments, _drush_environment_extract_env($file->filename));
  }
  // Load a single definition
  else {
    list($name,,) = explode('.', $file->name);
    $environments[$name] = _drush_environment_extract_env($file->filename);
  }
}

/**
 * Drush command helper to extract environemnt definitions from a file.
 */
function _drush_environment_extract_env($filename) {
  include($filename);
  return $env;
}

/**
 * Drush command helper function used to enable/disable modules.
 */
function _drush_environment_modules_management($modules = array()) {
  // Lets build a list of modules to enable and a separate list of modules to disable.
  $enable = $disable = array();
  foreach ($modules as $module_name => $status) {
    if ($status == 1) {
      $enable[] = $module_name;
    }
    else {
      $disable[] = $module_name;
    }
  }
  if (!empty($enable)) {
    drush_invoke('pm-enable', implode(',', $enable));
  }

  if (!empty($disable)) {
    drush_invoke('pm-disable', implode(',', $disable));
  }
}

/**
 * Drush command helper function used to set variables.
 */
function _drush_environment_vars_management($settings = array()) {
  foreach ($settings as $variable => $value) {
    variable_set($variable, $value);
    if (is_scalar($value)) {
      drush_print(dt('The "!var" variable has been set to !val.', array('!var' => $variable, '!val' => $value)));
    }
    else {
      drush_print(dt('The "!var" has been set.', array('!var' => $variable)));
    }
  }
}

/**
 * Drush command helper function used to set permissions.
 */
function _drush_environment_perms_management($settings = array()) {
  foreach ($settings as $role => $perm_settings) {
    // Get the role id based on the role name.
    // $roles = user_roles();
    $roles = array_flip(user_roles());
    if (isset($roles[$role])) {
      $rid = $roles[$role];
    }
    else {
      return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('That does not exist: !role', array('!role' => $role)));
    }

    // Grant any permissions that need granting.
    if (!empty($perm_settings['grant'])) {
      user_role_grant_permissions($rid, $perm_settings['grant']);
      drush_print(dt('The following permissions have been granted to `!role` users: !permissions', array(
        '!role' => $rid,
        '!permissions' => implode(', ', $perm_settings['grant'])
      )));
    }

    // Revoke any permissions that need revoking.
    if (!empty($perm_settings['revoke'])) {
      user_role_revoke_permissions($rid, $perm_settings['revoke']);
      drush_print(dt('The following permissions have been revoked for `!role` users: !permissions', array(
        '!role' => $rid,
        '!permissions' => implode(', ', $perm_settings['grant'])
      )));
    }
  }
}

/**
 * Helper function to recursively merge arrays.
 */
function array_merge_recursive_distinct(array &$array1, array &$array2) {
  $merged = $array1;

  foreach ($array2 as $key => &$value) {
    if (is_array($value) && isset($merged[$key]) && is_array($merged [$key] )) {
      $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
    }
    else {
      $merged[$key] = $value;
    }
  }

  return $merged;
}
