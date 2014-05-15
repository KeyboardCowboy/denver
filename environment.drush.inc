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

  // Make sure the ones they specified exists.
  foreach ($envs as $_env) {
    if (!isset($environments[$_env])) {
      return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('Unable to locate the environment definition for \'@env.\'', array('@env' => $_env)));
    }
  }

  // Execute the env settings.
  foreach ($envs as $_env) {
    foreach ($environments[$_env] as $type => $options) {
      $function = "_drush_environment_{$type}_management";
      if (function_exists($function)) {
        $function($options);
      }
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
 * Helper function to load all the available site environments.
 */
function _drush_environment_load_env_site(&$environments) {
  $env_root = drush_site_path() . '/drush';

  // Scan for environment files
  foreach (drush_scan_directory($env_root, '/env\.drushrc\.php$/') as $file) {
    _drush_environment_load_env_file($environments, $file);
  }
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
