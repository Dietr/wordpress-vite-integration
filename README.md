# Vite integration for Wordpress

Handles Vite integration for WordPress theme development.
Inspired by https://github.com/nystudio107/craft-vite.

## Installation

Via composer as mu-plugin:

```bash
composer require dietr/wp-vite-integration
```

Add this to your `composer.json`:

```json
{
    "require": {
        "dietr/wp-vite-integration": "^0.1.0"
    }
}
```

Then run:

```bash
composer install
```

## functions.php

```
// Vite integration
add_action('after_setup_theme', function() {
  $GLOBALS['vite_integration']->setConfig([
      'env_file' => get_template_directory() . '/../../../.env',
      'manifest_path' => get_template_directory() . '/dist/.vite/manifest.json',
      'manifest_uri' => get_template_directory_uri() . '/dist',
      'entry_point' => '/src/js/index.js',
      'vite_port' => '5173'
  ]);
});
```

## Vite dev server toggle

Add this script in the root: `viteDevServerToggle.js`

```
/**
 * toggle USE_VITE_DEV_SERVER environment variable based on which npm script is running
 * */
import fs from 'fs';
import os from 'os';

export function setEnvValue(key, value) {
  // read file from hdd & split if from a linebreak to a array
  const ENV_VARS = fs.readFileSync("./.env", "utf8").split(os.EOL);

  // find the env we want based on the key
  const target = ENV_VARS.indexOf(ENV_VARS.find((line) => {
    return line.match(new RegExp(key));
  }));

  // replace the key/value with the new value
  ENV_VARS.splice(target, 1, `${key}=${value}`);

  // write everything back to the file system
  fs.writeFileSync("./.env", ENV_VARS.join(os.EOL));
}

if (process.env.NODE_ENV !== 'test') {  // Avoid running in test environment
    if (process.argv.length > 2) {
        const enable = process.argv[2];
        setEnvValue("USE_VITE_DEV_SERVER", enable);
    }
}
```

Setup your build and dev task in your `package.json`

```
  "build": "node viteDevServerToggle.js 0 && npx vite build",
  "dev": "node viteDevServerToggle.js 1 && npx vite",
```