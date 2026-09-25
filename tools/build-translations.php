<?php
/** Compile text PO catalogs into release-only binary MO files. @package NakhostinPerformanceEngine */

declare(strict_types=1);

$root     = dirname(__DIR__);
$catalogs = glob($root . '/languages/*.po') ?: array();
$msgfmt   = trim((string) shell_exec('command -v msgfmt 2>/dev/null'));
$output   = $root . '/build/languages';

if ('' === $msgfmt) {
	fwrite(STDERR, "GNU msgfmt is required to build release translation catalogs.\n");
	exit(1);
}

if (!is_dir($output) && !mkdir($output, 0755, true) && !is_dir($output)) {
	fwrite(STDERR, "Unable to create the translation build directory.\n");
	exit(1);
}

foreach ($catalogs as $catalog) {
	$target  = $output . '/' . basename(substr($catalog, 0, -3)) . '.mo';
	$command = escapeshellarg($msgfmt) . ' --check -o ' . escapeshellarg($target) . ' ' . escapeshellarg($catalog);
	passthru($command, $status);
	if (0 !== $status) {
		fwrite(STDERR, sprintf("Failed to compile %s.\n", basename($catalog)));
		exit($status);
	}
	echo sprintf("Built %s\n", substr($target, strlen($root) + 1));
}
