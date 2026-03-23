<?php
/**
 * PSR-4 Autoloader
 * 
 * Simple namespaced class loader for ReuseIT.
 * Maps ReuseIT\* namespaces to src/* directories.
 * 
 * Usage:
 *   require_once 'vendor/autoload.php';
 *   use ReuseIT\Router;
 *   $router = new Router();
 */

spl_autoload_register(function ($class) {
    // PSR-4: ReuseIT\Foo\Bar → src/Foo/Bar.php
    $prefix = 'ReuseIT\\';
    $base_dir = __DIR__ . '/../src/';
    
    // Check if class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
