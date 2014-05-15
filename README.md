# Drush Environment Composer
This is a fork of the sandbox project at https://drupal.org/sandbox/bleen18/1696714
which is based on the code by Eric Duran at http://drupal.org/sandbox/ericduran/1154642

The *D*rush *Env*ironemnt Compos*er* allows you to define blanket settings to apply to
your site in one command, such as enabling and disabling modules and setting
variables.

These can be defined in two different files and only apply to the site directory
in which they are defined.

1. `sites/[default|example.com]/drush/env.drushrc.php`

2. `sites/[default|example.com]/drush/[dev.]env.drushrc.php`


Like aliases, in the first file you can define multiple aliases keyed on the
definition name such as 'dev,' 'stage' or 'chris.' In the second example you can
define each environment in a separate file and prefix the filename with the
definition name.

The definitions are formatted as such:

    $env['dev'] = array(
      // The list of modules to enabled (1) or disable (0).
      'modules' => array(
        'devel'       => 1,
        'securepages' => 0,
      ),

      // The list of variables to configure.
      'vars' => array(
        'preprocess_css' => 0,
        'preprocess_js'  => 0,
      ),

      // The list of roles to grant (1) and revoke (0) on a per role basis.
      'perms' => array(
        'Administrator' => array (
          'administer features' => 1,
          'administer permissions' => 1,
        ),
        'anonymous user' => array (
          'administer features' => 0,
          'administer permissions' => 0,
        )
      ),
    );

OR if using a named file like dev.env.drushrc.php, simply remove the name key
from the $env array.

    $env = array(
      'modules' => array(),
      'vars'    => array(),
      'perms'   => array(),
    );
