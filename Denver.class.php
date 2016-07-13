<?php
/**
 * @file
 * Contains \Denver.
 */

/**
 * Manage environment settings and application.
 */
class Denver {
  private $configPaths = [];
  private $environments = [];
  private $requestedEnvs = [];
  private $exec = [
    'modules' => [],
    'variables' => [],
    'permissions' => [],
    'commands' => [],
  ];

  /**
   * Denver constructor.
   */
  public function __construct() {
    // Load the yaml parser.
    $this->yaml = new \Drush\Make\Parser\ParserYaml();

    // Load the configuration files.
    $this->loadConfig();

    // Load the environment files.
    $this->findEnvironments();
  }

  /**
   * @return bool
   */
  public function foundEnvironments() {
    return !empty($this->environments);
  }

  /**
   * @return array
   */
  public function getEnvironments() {
    return $this->environments;
  }

  /**
   * @param $env
   *
   * @return bool
   */
  public function setEnvironments($env) {
    $envs = (explode('+', $env));
    while (!empty($envs)) {
      $_env = array_shift($envs);
      if (!$this->setEnvironment($_env)) {
        $msg = dt("Unable to locate an environment definition for '@env'.", ['@env' => $_env]);
        if (empty($envs)) {
          return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', $msg);
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
   * @param $env
   */
  private function setEnvironment($env) {
    if (isset($this->environments[$env])) {
      $this->requestedEnvs[] = $env;
      $this->loadEnvironment($env);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return array
   */
  public function getActiveDefinition() {
    return $this->exec;
  }

  /**
   *
   */
  public function exec() {
    foreach ($this->exec as $type => $options) {
      if (!empty($options)) {
        // Print a nice heading.
        $heading = $this->formatHeading($type);
        drush_print("\n{$heading}");

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
  }

  /**
   * @param $text
   *
   * @return string
   */
  public function formatHeading($text) {
    $green = "\033[1;32;40m\033[1m%s\033[0m";
    $heading = sprintf($green, ucwords($text));
    return $heading;
  }

  /**
   * @param $options
   */
  private function execModules($options) {
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
      drush_invoke('pm-enable', implode(',', $enable));
    }

    // Disable modules.
    if (!empty($disable)) {
      drush_invoke('pm-disable', implode(',', $disable));
    }

    if (empty($enable) && empty($disable)) {
      drush_print(dt("All modules are in their desired state.  Probably Rhode Island."));
    }
  }

  /**
   * @param $options
   */
  private function execVariables($options) {
    foreach ($options as $variable => $value) {
      variable_set($variable, $value);
      if (is_scalar($value)) {
        drush_print(dt('The "!var" variable has been set to !val.', array(
          '!var' => $variable,
          '!val' => $value
        )));
      }
      else {
        drush_print(dt('The "!var" has been set.', array('!var' => $variable)));
      }
    }
  }

  /**
   * @param $options
   *
   * @return bool
   */
  private function execPermissions($options) {
    $roles = array_flip(user_roles());

    foreach ($options as $role => $perm_settings) {
      // Get the role id based on the role name.
      if (isset($roles[$role])) {
        $rid = $roles[$role];
      }
      else {
        return drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', dt("Role '!role' does not exist.", ['!role' => $role]));
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
   * @param $commands
   */
  private function execCommands($commands) {
    // Pass the drushrc file through to drush_invoke_process.
    $default_options = [];
    if ($config = drush_get_option('config-file')) {
      $default_options['config'] = $config;
    }

    foreach ($commands as $command => $info) {
      // Set some default values for each command.
      $info += array(
        'alias' => '@self',
        'arguments' => [],
        'options' => [],
      );
      $info['options'] += $default_options;

      // Tell the user we are invoking the command.
      drush_print($this->formatCommand($command, $info));

      // Invoke the command.
      if (!drush_invoke_process($info['alias'], $command, $info['arguments'], $info['options'])) {
        return drush_set_error('COMMAND_FAILED', dt("Failed to execute drush command @command.", ['@command' => $command]));
      }
    }
  }

  /**
   * @param $command
   * @param $info
   *
   * @return string
   */
  public function formatCommand($command, $info) {
    $parts = ["drush"];

    $parts[] = !empty($info['alias']) ? $info['alias'] : '@self';
    $parts[] = $command;

    if (!empty($info['arguments'])) {
      $parts[] = implode(' ', $info['arguments']);
    }

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
   * @param $env
   */
  private function loadEnvironment($env) {
    $this->exec = array_merge_recursive_distinct($this->exec, $this->environments[$env]);
    unset($this->exec['filename']);
  }

  /**
   *
   */
  private function loadConfig() {
    $config_paths = [$this->getSiteDir()];

    // Find directories to scan for environment config files.
    // system, 'home.drush', 'drupal', 'custom'
    foreach (['custom', 'site', 'drupal', 'home.drush', 'system'] as $context) {
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

    // Flip the config list for proper hierarchy
    $this->configPaths = array_reverse($config_paths);
  }

  /**
   * @return string
   */
  public function getSiteDir() {
    $site_path = '';

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

    // If the site dir is 'default', switch to 'all'
    if (substr($site_path, -8) == '/default') {
      $site_path = str_replace('/default', '/all', $site_path);
    }

    return $site_path . '/drush';
  }

  /**
   *
   */
  private function findEnvironments() {
    foreach ($this->configPaths as $path) {
      foreach (drush_scan_directory($path, '/env\.drushrc\.y(a)?ml/') as $file) {
        $this->loadEnvFile($file);
      }
    }
  }

  /**
   * @param $file
   */
  private function loadEnvFile($file) {
    // Look for a group file.
    if (stripos($file->name, 'env') === 0) {
      foreach ($this->extractEnv($file->filename) as $name => $env) {
        $env['filename'] = $this->parseFilename($file->filename);
        $this->environments[$name] = $env;
      }
    }
    // Load a single definition.
    else {
      list($name, ,) = explode('.', $file->name);
      $this->environments[$name] = $this->extractEnv($file->filename);
      $this->environments[$name]['filename'] = $this->parseFilename($file->filename);
    }
  }

  /**
   * @param $filename
   *
   * @return mixed
   */
  private function extractEnv($filename) {
    $data = file_get_contents($filename);
    $env = $this->yaml->parse($data);

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
   * @param $filename
   *
   * @return string
   */
  private function parseFilename($filename) {
    if (stripos($filename, DRUPAL_ROOT) === 0) {
      return substr($filename, strpos($filename, DRUPAL_ROOT) + strlen(DRUPAL_ROOT) + 1);
    }
    else {
      return $filename;
    }
  }

  public function dump() {
    drush_print_r($this);
  }
}
