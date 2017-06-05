<?php
/**
 * @file
 * Contains \Denver.
 */

/**
 * Manage environment settings and application.
 */
class Denver {
  // Store the paths where we should look for environment settings.
  private $configPaths = [];

  // Store the actual environment definitions found.
  private $environments = [];

  // Store the compiled settings that we need to execute.
  private $exec = [];

  // Store the loaded environment filepaths.
  private $loadedEnvs = [];

  /**
   * Denver constructor.
   */
  public function __construct() {
    // Load the configuration files.
    $this->loadConfig();

    // Load the environment files.
    $this->findEnvironments();
  }

  /**
   * Check if a parameter is an drush option.
   *
   * @param string $item
   *   The parameter to check.
   *
   * @return bool
   *   The option name without the preceding '--' or FALSE if not an option.
   */
  public static function isOption($item) {
    // Special cases.
    if ($item === '-y') {
      return 'yes';
    }

    return (strpos($item, '--') === 0) ? substr($item, 2) : FALSE;
  }

  /**
   * Whether we found any environment definitions.
   *
   * @return bool
   *   TRUE if at least one env def was found.
   */
  public function foundEnvironments() {
    return !empty($this->environments);
  }

  /**
   * Get a list of found environment defs.
   *
   * @return array
   *   The full environment defs.
   */
  public function getEnvironments() {
    return $this->environments;
  }

  /**
   * Load the desired environments.
   *
   * @param string $env
   *   The environment argument.  May be composite (env1+env2).
   *
   * @return bool
   *   Whether the environments were loaded.
   */
  public function setEnvironments($env) {
    $envs = (explode('+', $env));
    while (!empty($envs)) {
      $_env = array_shift($envs);
      if (!$this->setEnvironment($_env)) {
        $msg = dt("Unable to locate an environment definition for '@env'.", ['@env' => $_env]);
        if (empty($envs)) {
          drush_log($msg, 'warning');
          return FALSE;
        }
        else {
          drush_log($msg, 'warning');
          if (!drush_confirm(dt("Do you want to process the other environments?"))) {
            return drush_user_abort(dt("Aborting."));
          }
        }
      }
    }

    return TRUE;
  }

  /**
   * Load an individual environment definition.
   *
   * @param string $env
   *   The single env def name.
   *
   * @return bool
   *   Whether the environment was loaded.
   */
  private function setEnvironment($env) {
    if (isset($this->environments[$env])) {
      $this->loadEnvironment($env);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get the composite settings to execute.
   *
   * @return array
   *   The composite env definition.
   */
  public function getActiveDefinition() {
    return $this->exec;
  }

  /**
   * Apply the settings.
   *
   * @param string $groups
   *   The groups option as passed in from drush_get_option().
   */
  public function exec($groups = '') {
    $_groups = !empty($groups) ? explode(',', $groups) : array();

    // Print a message that the build is starting.
    $names = implode('+', array_keys($this->loadedEnvs));
    drush_print();
    drush_print($this->formatHeading(dt("--- CONFIGURING @name ENVIRONMENT ---", array('@name' => strtoupper($names))), ''));

    foreach ($this->exec as $type => $options) {
      if (!empty($options) && (empty($_groups) || in_array($type, $_groups))) {
        // Print a nice heading.
        $heading = $this->formatHeading($type);
        drush_print();
        drush_print("{$heading}");

        switch ($type) {
          case 'modules';
            $this->execModules($options);
            break;

          case 'variables':
            $this->execVariables($options);
            break;

          case 'permissions':
            $this->execPermissions($options);
            break;

          case 'commands':
            $this->execCommands($options);
            break;

          default:
            drush_set_error('INVALID_ENV_GROUP', dt("I'm not sure what to do with '!group'.", ['!group' => $type]));
            break;
        }
      }
    }

    drush_print();
    if (drush_get_error()) {
      drush_set_error('DENVER_ENV_SETUP_FAILED', dt("The environment may not have been configured the way you wanted.  Check the logs for more details."));
      drush_print(dt("Use the --groups option to run only certain sections of an environment definition."));
    }
    else {
      return drush_log(dt("Environment setup complete!"), 'success');
    }
  }

  /**
   * Print a summary of the environment definitions.
   */
  public function printSummary() {
    drush_print();

    // Print env files used for this definition.
    drush_print($this->formatHeading('Environment Definitions Found'));
    foreach ($this->loadedEnvs as $filename) {
      drush_print("{$filename}", 1);
    }

    drush_print();
    drush_print($this->formatHeading(dt("CONFIGURATION SUMMARY")));

    // We want to make sure they are printed in the same order as they will run.
    foreach ($this->getActiveDefinition() as $group => $options) {
      // Print a nice heading.
      $heading = $this->formatHeading($group);

      switch (strtolower($group)) {
        case 'modules':
          $this->printModuleSummary($options, $heading);
          break;

        case 'variables':
          $this->printVariableSummary($options, $heading);
          break;

        case 'permissions':
          $this->printPermissionSummary($options, $heading);
          break;

        case 'commands':
          $this->printCommandSummary($options, $heading);
          break;

        default:
          drush_print($this->formatHeading($group));
          drush_log(dt("I'm not sure what to do with '@group'.", ['@group' => $group]), 'warning');
          break;
      }
    }
  }

  /**
   * Print a summary of the module section.
   *
   * @param array $options
   *   The array of module information from the yaml file.
   */
  private function printModuleSummary($options, $heading = '') {
    // Print module info.
    if (!empty($options)) {
      drush_print("{$heading}");

      $values = [];
      foreach ($options as $status => $modules) {
        $key = dt("!action", ['!action' => ucwords($status)]);
        $value = implode(', ', $modules);
        $values[$key] = $value;
      }

      drush_print_format([$values], 'key-value-list');
    }
  }

  /**
   * Print a summary of the variable section.
   *
   * @param array $options
   *   The array of variable information from the yaml file.
   */
  private function printVariableSummary($options, $heading = '') {
    // Print variable info.
    if (!empty($options)) {
      drush_print("{$heading}");

      drush_print_format([$options], 'key-value-list');
    }
  }

  /**
   * Print a summary of the permission section.
   *
   * @param array $options
   *   The array of permission information from the yaml file.
   */
  private function printPermissionSummary($options, $heading = '') {
    // Print permission info.
    if (!empty($options)) {
      drush_print("{$heading}");

      foreach ($options as $role => $perms) {
        foreach ($options[$role] as &$grant) {
          $grant = ($grant == 0) ? dt('revoke') : dt('grant');
        }

        drush_print($role, 1);
        drush_print_format([$options[$role]], 'key-value-list');
      }
    }
  }

  /**
   * Print a summary of the command section.
   *
   * @param array $options
   *   The array of command information from the yaml file.
   */
  private function printCommandSummary($options, $heading = '') {
    // Print command info.
    if (!empty($options)) {
      drush_print("{$heading}");

      foreach ($options as $command => $info) {
        drush_print($this->formatCommand($command, $info), 1);
      }
      drush_print();
    }
  }

  /**
   * Enable/disable modules.
   *
   * @param array $options
   *   The module info from the env definitions.
   */
  private function execModules(array $options) {
    $enable = $disable = [];

    // Build separate lists of modules to enable and disable.
    foreach ($options as $status => $modules) {
      foreach ($modules as $module_name) {
        if (($status === 1 || strtolower($status) === 'enable') && !module_exists($module_name)) {
          $enable[] = $module_name;
        }
        elseif (($status === 0 || strtolower($status) === 'disable') && module_exists($module_name)) {
          $disable[] = $module_name;
        }
      }
    }

    // Enable modules.
    if (!empty($enable)) {
      drush_invoke_process(NULL, 'pm-enable', ['extensions' => implode(',', $enable)], ['yes' => TRUE]);
    }

    // Disable modules.
    if (!empty($disable)) {
      drush_invoke_process(NULL, 'pm-disable', ['extensions' => implode(',', $disable)], ['yes' => TRUE]);
    }

    if (empty($enable) && empty($disable)) {
      drush_print(dt("All modules are in their desired state.  Probably Colorado."));
    }
  }

  /**
   * Set system variables.
   *
   * @todo How does this work in Drupal 8?
   *
   * @param array $options
   *   The variables and their values.
   */
  private function execVariables(array $options) {
    foreach ($options as $variable => $value) {
      if ($value === '[DELETE]') {
        variable_del($variable);
        drush_print(dt("'@var' was deleted.", ['@var' => $variable]));
      }
      else {
        variable_set($variable, $value);

        if (is_scalar($value)) {
          drush_print(dt("'@var' set to @val.", [
            '@var' => $variable,
            '@val' => $value
          ]));
        }
        else {
          drush_print(dt("'@var' has been set.", ['@var' => $variable]));
        }
      }
    }
  }

  /**
   * Grant/revoke permissions.
   *
   * @param array $options
   *   The permissions settings.
   */
  private function execPermissions(array $options) {
    $roles = array_flip(user_roles());

    foreach ($options as $role => $perm_settings) {
      // Get the role id based on the role name.
      if (isset($roles[$role])) {
        $rid = $roles[$role];
      }
      else {
        drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt("Role '!role' does not exist.", ['!role' => $role]));
        continue;
      }

      // Group the grants and revokes.
      $perms = ['grant' => [], 'revoke' => []];
      foreach ($perm_settings as $perm => $status) {
        if ($status === 1 || strtolower($status) === 'grant') {
          $perms['grant'][] = $perm;
        }
        else {
          $perms['revoke'][] = $perm;
        }
      }

      if (!empty($perms['grant'])) {
        user_role_grant_permissions($rid, $perms['grant']);
        drush_print(dt("Granted to '!role': !permissions", [
          '!role' => $role,
          '!permissions' => implode(', ', $perms['grant'])
        ]));
      }

      if (!empty($perms['revoke'])) {
        user_role_revoke_permissions($rid, $perms['revoke']);
        drush_print(dt("Revoked from '!role': !permissions", [
          '!role' => $role,
          '!permissions' => implode(', ', $perms['revoke'])
        ]));
      }
    }
  }

  /**
   * Invoke the desired commands.
   *
   * @param array $commands
   *   The commands to invoke.
   *
   * @return bool
   *   FALSE if a command fails.
   */
  private function execCommands(array $commands) {
    // Pass the drushrc file through to drush_invoke_process.
    $default_options = [];
    if ($config = drush_get_option('config-file')) {
      $default_options['config'] = $config;
    }

    foreach ($commands as $command => &$info) {
      // Prepare the command.
      $this->prepareCommand($info);
      $info['options'] += $default_options;

      // Tell the user we are invoking the command.
      drush_print($this->formatHeading("✗") . ' ' . $this->formatCommand($command, $info));

      // Invoke the command.
      if (!drush_invoke_process($info['alias'], $command, $info['arguments'], $info['options'])) {
        return drush_set_error('COMMAND_FAILED', dt("Failed to execute drush command @command.", ['@command' => $command]));
      }

      drush_print();
    }
  }

  /**
   * Format a nice, readable heading for the user.
   *
   * @param string $text
   *   The raw heading text.
   * @param $line_ending
   *   A string to end the heading with.
   *
   * @return string
   *   The formatted heading text.
   */
  public function formatHeading($text, $line_ending = ":") {
    $green = "\033[1;32;40m\033[1m%s{$line_ending}\033[0m";
    $heading = sprintf($green, ucwords($text));
    return $heading;
  }

  /**
   * Format a command as a single line.
   *
   * @param string $command
   *   The command name.
   * @param array|null $info
   *   The command settings.
   *
   * @return string
   *   The formatted command.
   */
  public function formatCommand($command, $info) {
    $this->prepareCommand($info);

    // Create the base.
    $parts = ["drush"];
    $parts[] = $info['alias'];

    // Check for a '--yes' options and set it early for common formatting.
    if (isset($info['options']['yes'])) {
      $parts[] = '-y';
      unset($info['options']['yes']);
    }

    // Add the command.
    $parts[] = $command;

    // Add arguments.
    if (!empty($info['arguments'])) {
      $parts[] = implode(' ', $info['arguments']);
    }

    // Add options.
    if (!empty($info['options'])) {
      // Skip the config option.
      unset($info['options']['config']);

      foreach ($info['options'] as $option => $value) {
        $part = "--{$option}";
        if ($value !== TRUE && $value !== 1) {
          $part .= "={$value}";
        }

        $parts[] = $part;
      }
    }

    return implode(' ', $parts);
  }

  /**
   * Prepare the command data for output and execution.
   *
   * @param array|null $info
   *    A command definition pulled from the YAML file.
   */
  private function prepareCommand(&$info) {
    $info = (array) $info;

    // Pull alias, arguments and options out of the info.
    $alias = isset($info['alias']) ? $info['alias'] : '@self';
    $args = isset($info['arguments']) ? $info['arguments'] : array();
    $opts = isset($info['options']) ? $info['options'] : array();
    unset($info['alias'], $info['arguments'], $info['options']);

    // Any remaining items in the $info array are shorthand or condensed
    // settings.  First, check for condensed syntax.  The $info array will be
    // keyed numerically.
    foreach ($info as $item => $value) {
      // Unnamed args and boolean options.
      if (is_numeric($item) && !is_array($value)) {
        if ($opt_name = static::isOption($value)) {
          $opts[$opt_name] = TRUE;
        }
        else {
          $args[] = $value;
        }
      }
      // Key:value items.  Arguments shouldn't have keys in this syntax, but
      // the could.  Check to be sure.
      elseif (is_numeric($item) && is_array($value)) {
        foreach ($value as $item_name => $item_value) {
          if ($opt_name = static::isOption($item_name)) {
            $opts[$opt_name] = $item_value;
          }
          else {
            $args[$item_name] = $item_value;
          }
        }
      }

      // Shorthand syntax.
      elseif (!is_numeric($item) && ($opt_name = self::isOption($item))) {
        $opts[$opt_name] = $value;
      }
      else {
        $args[$item] = $value;
      }
    }

    // Convert argument value arrays to comma separated strings.
    foreach ($args as &$value) {
      if (is_array($value)) {
        $value = implode(',', $value);
      }
    }

    // Convert option value arrays to comma separated strings.
    foreach ($opts as &$value) {
      if (is_array($value)) {
        $value = implode(',', $value);
      }
    }

    // Rebuild the $info array.
    $info = array(
      'alias' => $alias,
      'options' => $opts,
      'arguments' => $args,
    );
  }

  /**
   * Load in an environment definition.
   *
   * @param string $env
   *   An environment name.
   */
  private function loadEnvironment($env) {
    $this->exec = array_merge_recursive_distinct($this->exec, $this->environments[$env]);
    $this->loadedEnvs[$env] = $this->exec['filename'];
    unset($this->exec['filename']);
  }

  /**
   * Find the config paths to search for env definitions.
   */
  private function loadConfig() {
    drush_log(dt('Loading config paths.'), 'debug');
    $config_paths = [$this->getSiteDir()];

    // Find directories to scan for environment config files.
    // system, 'home.drush', 'drupal', 'custom'
    foreach (['custom', 'drupal', 'home.drush', 'system'] as $context) {
      if ($files = _drush_config_file($context, 'env')) {
        if (is_array($files)) {
          foreach ($files as $file) {
            $config_paths[] = dirname($file);
          }
        }
        else {
          $config_paths[] = dirname($files);
        }
      }
    }

    // Remove any empty values.
    $config_paths = array_filter($config_paths);

    // Flip the config list for proper hierarchy
    $this->configPaths = array_reverse($config_paths);
    drush_log(dt('Loaded config paths.'), 'debug');
  }

  /**
   * Get this site's appropriate drush directory for env vars.
   *
   * Optionally, the directory can be created by passing in the --make option.
   *
   * @return string
   *   The directory path.
   */
  public function getSiteDir() {
    if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_SITE)) {
      return FALSE;
    }

    // Check for aliases
    $site_name = drush_sitealias_bootstrapped_site_name();
    $alias = drush_sitealias_get_record("@{$site_name}");

    if (floatval(DRUSH_VERSION) > 6.2) {
      $site_path = drush_sitealias_local_site_path($alias);
    }
    else {
      $supposed_path = drush_sitealias_local_site_path($alias);
      $hostname = drush_sitealias_uri_to_site_dir($alias['uri']);

      $site_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
      if (file_exists($site_root . '/sites/sites.php')) {
        $sites = [];

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

    // If the site dir is 'default', switch to 'all'.
    if (substr($site_path, -8) == '/default') {
      $site_path = str_replace('/default', '/all', $site_path);
    }

    return $site_path . '/drush';
  }

  /**
   * Find and load environment definitions.
   */
  private function findEnvironments() {
    drush_log(dt('Loading environment files.'), 'debug');

    foreach ($this->configPaths as $path) {
      drush_log(dt("Scanning !path.", ['!path' => $path]), 'debug');
      foreach (drush_scan_directory($path, '/env\.drushrc\.y(a)?ml/') as $file) {
        $this->loadEnvFile($file);
      }
    }

    drush_log(dt('Loaded environment files.'), 'debug');
  }

  /**
   * Extract the contents of an environment definition file.
   *
   * @param object $file
   *   A file object.
   */
  private function loadEnvFile($file) {
    list($name, ,) = explode('.', $file->name);
    $this->environments[$name] = $this->extractEnv($file->filename);
    $this->environments[$name]['filename'] = $this->parseFilename($file->filename);
  }

  /**
   * Parse and prepare the information in an environment file.
   *
   * @param string $filename
   *   The filename.
   *
   * @return array
   *   The yaml data parsed into a php array.
   */
  private function extractEnv($filename) {
    // Load the yaml parser.
    $yaml = new \Symfony\Component\Yaml\Yaml();

    $data = file_get_contents($filename);
    $env = (array) $yaml->parse($data);
    
    // Add defaults.
    $env += [
      'modules' => [],
      'variables' => [],
      'permissions' => [],
      'commands' => [],
    ];

    // Process data so it can be appropriately merged if this is a composite
    // environment request.
    foreach ($env['modules'] as $status => $modules) {
      $env['modules'][$status] = array_combine($env['modules'][$status], $env['modules'][$status]);
    }

    return $env;
  }

  /**
   * Resolve the proper filename for an environment file.
   *
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The resolved filename.
   */
  private function parseFilename($filename) {
    if (defined('DRUPAL_ROOT') && stripos($filename, DRUPAL_ROOT) === 0) {
      return substr($filename, strpos($filename, DRUPAL_ROOT) + strlen(DRUPAL_ROOT) + 1);
    }
    else {
      return $filename;
    }
  }

}
