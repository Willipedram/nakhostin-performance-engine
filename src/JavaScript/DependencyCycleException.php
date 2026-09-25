<?php
/**
 * Dependency cycle exception.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

use RuntimeException;

final class DependencyCycleException extends RuntimeException {}
