<?php
/**
 * Plugin Name: Wordpress Vite Integration
 * Plugin URI: 
 * Description: Handles Vite integration for WordPress theme development
 * Version: 1.0.0
 * Author: Dieter Peirs
 * License: MIT
 */

namespace ViteIntegration;

class ViteAssets {
    private static $instance = null;
    private $env_vars = [];
    private $config = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->setDefaultConfig();
        $this->loadEnvFile();
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 9);
    }

    private function setDefaultConfig() {
        $this->config = [
            'env_file' => WP_CONTENT_DIR . '/../../.env',
            'manifest_path' => get_template_directory() . '/dist/.vite/manifest.json',
            'manifest_uri' => get_template_directory_uri() . '/dist',
            'entry_point' => '/src/js/index.js',
            'ddev_url' => getenv('DDEV_PRIMARY_URL') ?: 'https://wordpress-theming.ddev.site',
            'vite_port' => '5173'
        ];
    }

    public function setConfig(array $config) {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
    
    private function loadEnvFile() {
        if (file_exists($this->config['env_file'])) {
            $lines = file($this->config['env_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    
                    $this->env_vars[$name] = $value;
                    $_ENV[$name] = $value;
                    putenv(sprintf('%s=%s', $name, $value));
                }
            }
        }
    }
    
    public function enqueueAssets() {
        if (!getenv('IS_DDEV_PROJECT')) {
            $this->enqueueProdAssets();
            return;
        }

        $use_vite_dev = $this->env_vars['USE_VITE_DEV_SERVER'] ?? getenv('USE_VITE_DEV_SERVER') ?? $_ENV['USE_VITE_DEV_SERVER'] ?? '0';

        if ($use_vite_dev !== '1') {
            $this->enqueueProdAssets();
            return;
        }

        $vite_dev_server = $this->config['ddev_url'] . ':' . $this->config['vite_port'];
        $this->enqueueDevAssets($vite_dev_server, $this->config['entry_point']);
    }
    
    private function enqueueDevAssets($vite_dev_server, $entry_point) {
        wp_enqueue_script('vite-client', 
            $vite_dev_server . '/@vite/client',
            [], null, true
        );
        
        wp_enqueue_script('vite-entry', 
            $vite_dev_server . $entry_point,
            ['vite-client'], null, true
        );
    }

    private function enqueueProdAssets() {
        if (!file_exists($this->config['manifest_path'])) {
            return;
        }

        $manifest = json_decode(file_get_contents($this->config['manifest_path']), true);
        
        foreach ($manifest as $key => $entry) {
            if ($key === 'src/js/index.js' || $key === basename($this->config['entry_point'])) {
                wp_enqueue_script('vite', 
                    $this->config['manifest_uri'] . '/' . $entry['file'],
                    [], null, true
                );

                if (isset($entry['css']) && is_array($entry['css'])) {
                    foreach ($entry['css'] as $css_file) {
                        wp_enqueue_style('vite-' . basename($css_file),
                            $this->config['manifest_uri'] . '/' . $css_file
                        );
                    }
                }
                break;
            }
        }
    }
    
    public function getEnv($key, $default = null) {
        return $this->env_vars[$key] ?? $default;
    }
}

$GLOBALS['vite_integration'] = ViteAssets::getInstance();

// Example usage in your theme's functions.php:
/*
add_action('after_setup_theme', function() {
    ViteIntegration\vite()->setConfig([
        'env_file' => get_template_directory() . '/../../../.env',
        'manifest_path' => get_template_directory() . '/dist/.vite/manifest.json',
        'manifest_uri' => get_template_directory_uri() . '/dist',
        'entry_point' => '/src/js/index.js',
        'vite_port' => '5173'
    ]);
});
*/