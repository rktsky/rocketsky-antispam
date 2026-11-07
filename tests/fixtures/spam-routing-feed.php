<?php
/**
 * Records an owned synthetic feed through the unchanged Gravity Forms framework.
 *
 * It performs no external side effect; native maybe_process_feed and native
 * unspam handling decide whether this process_feed callback is reached.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

/**
 * Supplies a local observable sink to the original feed lifecycle.
 *
 * @since 0.5.0
 */
final class RktskyAntispamRoutingFeed extends GFFeedAddOn {
	/** @var string Fixed synthetic add-on version. @since 0.5.0 */
	protected $_version = '0.5.0';
	/** @var string Owned fixture slug. @since 0.5.0 */
	protected $_slug = 'rktsky-spam-routing-test';
	/** @var string Native minimum for the reviewed feed framework. @since 0.5.0 */
	protected $_min_gravityforms_version = '2.7';
	/** @var string Fixture display title. @since 0.5.0 */
	protected $_title = 'Synthetic spam routing';
	/** @var string Fixture short title. @since 0.5.0 */
	protected $_short_title = 'Synthetic routing';
	/** @var bool Runs the owned sink synchronously for observable native results. @since 0.5.0 */
	protected $_async_feed_processing = false;
	/** @var self|null Own request-local instance. @since 0.5.0 */
	private static $instance = null;

	/**
	 * Returns the fixture instance through the documented add-on contract.
	 *
	 * @since 0.5.0
	 * @return self Synthetic native-framework add-on.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	/**
	 * Counts a genuine native framework invocation without reading field contents.
	 *
	 * @since 0.5.0
	 * @param array $feed Native configured feed, not recorded.
	 * @param array $entry Native entry, not recorded.
	 * @param array $form Native form, not recorded.
	 * @return bool Successful local sink execution.
	 */
	public function process_feed( $feed, $entry, $form ) {
		++$GLOBALS['rktsky_routing_feed_calls'];
		return true;
	}
}
