# Drupal Environment Personalizer
The Drupal ENVironemnt personalizER allows you to define macro settings to apply to
your site in one command, such as enabling and disabling modules and setting
variables.

## Installation
Extract this repository into any of Drush's searchable paths for plugins:

1. A `.drush` folder in your HOME folder.

2. Anywhere in a folder tree below an active module on your site.

3. `/usr/share/drush/commands` (configurable)

4. In an arbitrary folder specified with the `--include` option.

5. Drupal's `/drush` or `/sites/all/drush` folders.


*See `drush topic docs-commands` for more details*

## Configuration

These can be defined in two different files and only apply to the site directory
in which they are defined.

1. `sites/[default|example.com]/drush/env.drushrc.yml`

2. `sites/[default|example.com]/drush/[dev.]env.drushrc.yml`


Like aliases, in the first file you can define multiple aliases keyed on the
definition name such as 'dev,' 'stage' or 'chris.' In the second example you can
define each environment in a separate file and prefix the filename with the
definition name.

The definitions are formatted as such:
	# Settings for your local environment.
	modules:
	  enable:
		- module_name
	  disable:
		- module_name
	
	variables:
	  your_var: your_var_value
	  another_var: NULL
	
	permissions:
	  RoleName:
		permission_name: 0
	
	commands:
	  # Using longhand notation.
	  command-name:
		alias: @self
		arguments:
		  arg1: arg1-val
		options:
		  opt1: opt1-val
	  # Using shorthand notation.
	  command-name:
	    - yes
	    - arg1
	    - arg2
	    - --option1

## How to Use It

You must be inside a Drupal site directory or use an alias for these commands to
work.

1. Create a starter file

    `drush env-dir --make`

1. See which environments are available.

    `drush env`
    `drush env-list`

2. Inspect the contents of an environment definition.

    `drush env [en-name] --info`

    Ex. `drush env dev --info`

3. Run the environment settings for a single definition.

    `drush env [env-name]`

    Ex. `drush env dev`

4. Combine multiple environments.  The settings for the latter overriding the former.

    `drush env [env-name1]+[env-name2]`

    Ex. `drush env dev+chris`
