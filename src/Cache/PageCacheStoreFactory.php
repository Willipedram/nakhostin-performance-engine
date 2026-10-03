<?php
/** Selects a safe runtime page-cache backend. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class PageCacheStoreFactory { private $objects; private $directory; public function __construct( ObjectCacheDetector $objects, string $directory ) { $this->objects = $objects; $this->directory = $directory; } public function create( bool $early_mode = false ): PageCacheStoreInterface { $capability = $this->objects->detect(); if ( ! $early_mode && ! empty( $capability['persistent'] ) && ! empty( $capability['healthy'] ) ) { return new ObjectCachePageCacheStore(); } return new FilesystemCacheStore( $this->directory ); } }
