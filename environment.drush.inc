<?php
/**
 * @file
 * This file is based on code found at
 * http://drupal.org/sandbox/ericduran/1154642.
 */

/*
// Add this to your drushrc file and modify as needed.
// The option key MUST be enviroments.
$options['environments'] = array(
  // #key = The enviroment name.
  'development' => array(
    // The list of modules to enabled (1) or disable (0).
    'modules' => array(
      'views_ui' => 1,
      'context_ui' => 1,
      'devel' => 1,
      'theme_developer' => 0,
    ),
    // The list of variables to configure.
    'settings' => array(
      'preprocess_css' => 0,
      'preprocess_js' => 0,
    ),
    // The list of roles to grant (1) and revoke (0) on a per role basis.
    'permissions' => array(
      'Administrator' => array (
        'administer features' => 1,
        'administer permissions' => 1,
      ),
      'anonymous user' => array (
        'administer features' => 0,
        'administer permissions' => 0,
      )
    ),
  ),
);
*/

/**
 * Implementation of hook_drush_command().
 */
function environment_drush_command() {
  $items['environment'] = array(
    'description' => 'Enable settings for a specific environment.',
    'arguments' => array(
      'environment-name' => 'A predefine environment name created in your drushrc.php file.',
    ),
    'examples' => array(
      'drush pub-env development' => 'Enabled the development environment.',
    ),
    'aliases' => array('env'),
  );

  return $items;
}

/**
 * Drush command callback function.
 */
function drush_environment($environment) {
  global $env;
  $environment = is_null($environment) ? $env : $environment;

  if (is_null($environment)) {
    return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('You must specify an environment or set a global $env varibale in drushrc.'));
  }

  $enviroments_options = drush_get_option('environments');
  if (isset($enviroments_options[$environment])) {
    foreach ($enviroments_options[$environment] as $settings => $options) {
      $function = "_drush_environment_{$settings}_management";
      if (function_exists($function)) {
        $function($options);
      }
    }
  }
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
 * Drush command helper function used to set variabled.
 */
function _drush_environment_settings_management($settings = array()) {
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
function _drush_environment_permissions_management($settings = array()) {
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


