<?php
/**
 * @file
 */

// Load the Denver object.
require_once __DIR__ . "/Denver.class.php";

/**
 * Implementation of hook_drush_command().
 */
function environment_drush_command() {
  $items['environment'] = array(
    'description' => 'Enable settings for a specific environment.',
    'arguments' => array(
      'environment-name' => 'Predefined environment names created in your env.drushrc.php files.  Combine multiple environment definitions using the plus(+) sign.',
    ),
    'options' => array(
      'info' => 'Show the summary of the environment settings instead of running them.',
      'groups' => 'Run only the certain groups. Separate groups with commas.',
    ),
    'examples' => array(
      'drush env dev' => 'Run the dev environment settings.',
      'drush env dev+chris' => 'Run the dev settings then run chris\'s settings.',
      'drush env dev+chris --info' => "Show the changes that these two environment definitions implement.",
      'drush env dev --groups=variables,permissions' => "Run only the variables and permissions groups from the dev settings.",
    ),
    'aliases' => array('env'),
  );

  $items['environment-list'] = array(
    'description' => 'Show all available environments.',
    'examples' => array(
      'drush env-list' => 'Display all available environments and their location.',
    ),
    'aliases' => array('env-list'),
    'outputformat' => array(
      'default' => 'key-value-list',
      'pipe-format' => 'json',
    ),
  );

  $items['environment-dir'] = array(
    'description' => 'Show and optionally make the directory where env definitions should be placed for this site.',
    'options' => array(
      'make' => 'Create a definition file if it does not already exist.',
    ),
    'examples' => array(
      'drush env-dir' => 'Print the directory path.',
      'drush env-dir --make' => 'Create a starter env.drushrc.php in the proper directory.',
      'drush env-dir --make=dev' => 'Create a starter dev.env.drushrc.php in the proper directory.',
    ),
    'aliases' => array('env-dir', 'envdir'),
  );

  return $items;
}

/**
 * Drush command callback function.
 */
function drush_environment($env = NULL) {
  $denver = new Denver();

  // Make sure we found at least one environment definition.
  if (!$denver->foundEnvironments()) {
    return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('Unable to find any environment definitions.'));
  }

  // If no environment was specified, provide a list.
  if (!$env) {
    $data = drush_environment_list();
    drush_print_format($data, 'key-value-list');

    return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt('You need to specify at least one environment definition.'));
  }

  // Set the definition and prepare the environment instructions.
  if ($denver->setEnvironments($env)) {
    // @todo: Add options to run only certain settings
    // @see: https://github.com/KeyboardCowboy/denver/issues/5

    if (drush_get_option('info')) {
      $denver->printSummary();
    }
    else {
      $denver->exec(drush_get_option('groups'));
    }
  }
}

/**
 * Command callback.
 */
function drush_environment_list() {
  $denver = new Denver();
  $data = [];

  foreach ($denver->getEnvironments() as $name => $env) {
    $data['env'][$name] = $env['filename'];
  }

  return $data;
}

/**
 * Command callback.
 */
function drush_environment_dir() {
  $denver = new Denver();
  $site_path = $denver->getSiteDir();

  // Optionally create the directory
  if ($name = drush_get_option('make')) {
    // Create the directory if it doesn't already exist.
    drush_mkdir($site_path);

    // Create new env definition file name.
    $filename = ($name == 1 ? 'env.drushrc.yml' : "{$name}.env.drushrc.yml");
    $filepath = "{$site_path}/{$filename}";

    // Get the file path relative to the user's current dir.
    $localpath = '.' . substr($filepath, strpos($filepath, drush_cwd()) + strlen(drush_cwd()));

    // Create the file, checking for existence first.
    if (file_exists($filepath)) {
      drush_log(dt('The file @name already exists.', ['@name' => $localpath]), 'warning');
    }
    else {
      $root = dirname(__FILE__);
      $content = ($name == 1 ? file_get_contents("{$root}/templates/group.yml") : file_get_contents("{$root}/templates/single.yml"));
      if (file_put_contents($filepath, $content)) {
        drush_log(dt('The file @name has been created for you!', ['@name' => $localpath]), 'success');
      }
      else {
        drush_log(dt('Unable to create the file @name for you. Sorry!', ['@name' => $localpath]), 'error');
      }
    }
  }
  else {
    drush_print(dt('Add your env definition files here: @path', ['@path' => $site_path]));
  }
}

/**
 * Helper function to recursively merge arrays.
 */
function array_merge_recursive_distinct(array &$array1, array &$array2) {
  $merged = $array1;

  foreach ($array2 as $key => &$value) {
    if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
      $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
    }
    else {
      $merged[$key] = $value;
    }
  }

  return $merged;
}
