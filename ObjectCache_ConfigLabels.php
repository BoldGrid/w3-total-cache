<?php
/**
 * File: ObjectCache_ConfigLabels.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ObjectCache_ConfigLabels
 */
class ObjectCache_ConfigLabels {
	/**
	 * Merges the provided configuration labels with default object cache labels.
	 *
	 * @param array $config_labels Array of configuration labels to be merged.
	 *
	 * @return array Merged configuration labels with default object cache labels.
	 */
	public function config_labels( $config_labels ) {
		return array_merge(
			$config_labels,
			array(
				'objectcache.engine'               => __( 'Object Cache Method:', 'w3-total-cache' ),
				'objectcache.enabled'              => __( 'Object Cache:', 'w3-total-cache' ),
				'objectcache.debug'                => __( 'Object Cache', 'w3-total-cache' ),
				'objectcache.lifetime'             => __( 'Default lifetime of cache objects:', 'w3-total-cache' ),
				'objectcache.file.gc'              => __( 'Garbage collection interval:', 'w3-total-cache' ),
				'objectcache.groups.global'        => __( 'Global groups:', 'w3-total-cache' ),
				'objectcache.groups.nonpersistent' => __( 'Non-persistent groups:', 'w3-total-cache' ),
				'objectcache.purge.all'            => __( 'Flush all cache on post, comment etc changes.', 'w3-total-cache' ),
			)
		);
	}
}
