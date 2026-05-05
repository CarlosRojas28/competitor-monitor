<?php
/**
 * Logger service.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a small logging facade over the plugin logs table.
 */
class WC_Competitor_Monitor_Logger {

	/**
	 * Database layer.
	 *
	 * @var WC_Competitor_Monitor_DB
	 */
	private WC_Competitor_Monitor_DB $db;

	/**
	 * Constructor.
	 *
	 * @param WC_Competitor_Monitor_DB $db Database layer.
	 */
	public function __construct( WC_Competitor_Monitor_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Writes an info log.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return int
	 */
	public function info( string $message, array $context = array() ): int {
		return $this->log( 'info', $message, $context );
	}

	/**
	 * Writes a warning log.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return int
	 */
	public function warning( string $message, array $context = array() ): int {
		return $this->log( 'warning', $message, $context );
	}

	/**
	 * Writes an error log.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return int
	 */
	public function error( string $message, array $context = array() ): int {
		return $this->log( 'error', $message, $context );
	}

	/**
	 * Writes a log entry.
	 *
	 * @param string              $level Level.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return int
	 */
	public function log( string $level, string $message, array $context = array() ): int {
		$allowed_levels = array( 'info', 'warning', 'error' );
		$level          = in_array( $level, $allowed_levels, true ) ? $level : 'info';

		return $this->db->insert_log( $level, $message, $context );
	}
}
