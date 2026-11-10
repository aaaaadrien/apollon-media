<?php
// Simple PSR-4 style autoloader for the Apollon namespace.
spl_autoload_register(function (string $class): void {
  $prefix = 'Apollon\\';
  if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
    return;
  }
  $relative = substr($class, strlen($prefix));
  $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
  if (is_file($file)) {
    require $file;
  }
});
