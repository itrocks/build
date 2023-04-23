<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
	include __DIR__ . '/../vendor/autoload.php';
}
elseif (file_exists(__DIR__ . '/../../../../vendor/autoload.php')) {
	include __DIR__ . '/../../../../vendor/autoload.php';
}

spl_autoload_register(function(string $class_name) {
	if ($class_name === 'ITRocks\\Build') {
		include __DIR__ . '/../src/Build.php';
	}
	elseif (str_starts_with($class_name, 'ITRocks\\Build\\')) {
		include __DIR__ . '/../src/' . str_replace('\\', '/', substr($class_name, 18)) . '.php';
	}
});
