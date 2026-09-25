<?php
/** Reject binary files from the source repository. @package NakhostinPerformanceEngine */

declare(strict_types=1);

$root       = dirname(__DIR__);
$iterator   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$violations = array();

foreach ($iterator as $file) {
	if (!$file->isFile()) {
		continue;
	}
	$path     = $file->getPathname();
	$relative = substr($path, strlen($root) + 1);
	if (0 === strpos($relative, '.git/') || 0 === strpos($relative, 'vendor/') || 0 === strpos($relative, 'build/')) {
		continue;
	}
	$contents = file_get_contents($path);
	if (false === $contents || false !== strpos($contents, "\0") || 1 !== preg_match('//u', $contents)) {
		$violations[] = $relative;
	}
}

if ($violations) {
	fwrite(STDERR, "Binary or non-UTF-8 source files are not permitted:\n - " . implode("\n - ", $violations) . "\n");
	exit(1);
}

echo "Repository contains text/UTF-8 source files only.\n";
