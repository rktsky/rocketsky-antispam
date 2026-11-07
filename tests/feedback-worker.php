<?php
/**
 * Changes one native-comment label or removes its link in an independent connection.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */
require __DIR__ . '/bootstrap.php';
$id = $argv[1] ?? '';
$mode = $argv[2] ?? '';
if ( ! preg_match( '/^[1-9][0-9]{0,8}$/D', $id ) || ! in_array( $mode, array( '0', '1', 'forget' ), true ) ) {
	throw new RuntimeException( 'A bounded synthetic feedback case is required.' );
}
wp_set_current_user( 1 );
$feedback = new \Rktsky\Antispam\Feedback();
if ( 'forget' === $mode ) {
	$feedback->forget( 'comments', $id );
} else {
	// A concurrent later label may legitimately win before this method's readback.
	$feedback->review( 'comments', $id, '1' === $mode );
}
