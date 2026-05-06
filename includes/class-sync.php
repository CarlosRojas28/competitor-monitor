<?php
/**
 * Mapping synchronization with the Pro SaaS.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pushes WooCommerce mapping snapshots to the SaaS.
 */
class WC_Competitor_Monitor_Sync {

	/**
	 * Database layer.
	 *
	 * @var WC_Competitor_Monitor_DB
	 */
	private WC_Competitor_Monitor_DB $db;

	/**
	 * Pro SaaS client.
	 *
	 * @var WC_Competitor_Monitor_Pro_Client
	 */
	private WC_Competitor_Monitor_Pro_Client $pro_client;

	/**
	 * Constructor.
	 *
	 * @param WC_Competitor_Monitor_DB         $db Database layer.
	 * @param WC_Competitor_Monitor_Pro_Client $pro_client Pro client.
	 */
	public function __construct( WC_Competitor_Monitor_DB $db, WC_Competitor_Monitor_Pro_Client $pro_client ) {
		$this->db         = $db;
		$this->pro_client = $pro_client;

		add_action( 'admin_post_wc_competitor_monitor_sync_mappings', array( $this, 'handle_manual_sync' ) );
		add_action( 'wc_competitor_monitor_mapping_changed', array( $this, 'sync_mapping_from_action' ), 10, 2 );
		add_action( 'wc_competitor_monitor_mapping_deleted', array( $this, 'delete_mapping_from_action' ), 10, 1 );
	}

	/**
	 * Handles the admin manual sync button.
	 *
	 * @return void
	 */
	public function handle_manual_sync(): void {
		WC_Competitor_Monitor_Security::require_capability();
		check_admin_referer( 'wc_competitor_monitor_sync_mappings' );

		$result  = $this->sync_all_mappings();
		$type    = empty( $result['success'] ) ? 'error' : 'updated';
		$message = empty( $result['success'] )
			? (string) ( $result['error'] ?? __( 'Mapping sync failed.', 'competitor-price-stock-monitor' ) )
			: sprintf(
				/* translators: %d: number of mappings synchronized. */
				_n( '%d mapping synchronized with SaaS.', '%d mappings synchronized with SaaS.', absint( $result['synced'] ?? 0 ), 'competitor-price-stock-monitor' ),
				absint( $result['synced'] ?? 0 )
			);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'competitor-price-stock-monitor-competitors',
					'wccm_notice'  => $type,
					'wccm_message' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Synchronizes every local mapping to the SaaS.
	 *
	 * @return array<string,mixed>
	 */
	public function sync_all_mappings(): array {
		if ( ! $this->pro_client->is_connected() ) {
			return $this->store_global_result( false, __( 'Pro bridge is not active. Activate Pro before syncing mappings.', 'competitor-price-stock-monitor' ), 0 );
		}

		$this->pro_client->sync_site_profile( true, 'mapping_sync' );

		$mappings = $this->db->get_mappings( array( 'limit' => 5000 ) );
		$payloads = array();
		foreach ( $mappings as $mapping ) {
			$this->db->ensure_mapping_sync_uuid( absint( $mapping->id ) );
			$mapping    = $this->db->get_mapping( absint( $mapping->id ) ) ?: $mapping;
			$payloads[] = $this->mapping_payload( $mapping );
		}

		$result = $this->pro_client->sync_mappings( 'full', $payloads );
		if ( empty( $result['success'] ) ) {
			$error = (string) ( $result['error'] ?? __( 'The SaaS rejected the mapping sync.', 'competitor-price-stock-monitor' ) );
			foreach ( $mappings as $mapping ) {
				$this->db->mark_mapping_sync_error( absint( $mapping->id ), $error );
			}
			return $this->store_global_result( false, $error, 0 );
		}

		foreach ( $payloads as $payload ) {
			$this->db->mark_mapping_sync_success( absint( $payload['id'] ), $this->payload_hash( $payload ) );
		}

		return $this->store_global_result( true, __( 'Mappings synchronized with SaaS.', 'competitor-price-stock-monitor' ), count( $payloads ) );
	}

	/**
	 * Synchronizes one mapping after a local change.
	 *
	 * @param int    $mapping_id Mapping ID.
	 * @param string $reason Change reason.
	 * @return array<string,mixed>
	 */
	public function sync_mapping( int $mapping_id, string $reason = 'changed' ): array {
		$mapping_id = absint( $mapping_id );
		if ( $mapping_id <= 0 || ! $this->pro_client->is_connected() ) {
			return array( 'success' => false );
		}

		$this->db->ensure_mapping_sync_uuid( $mapping_id );
		$mapping = $this->db->get_mapping( $mapping_id );
		if ( ! $mapping ) {
			return array( 'success' => false );
		}

		$payload = $this->mapping_payload( $mapping );
		$result  = $this->pro_client->sync_mappings( 'upsert', array( $payload ), array(), $reason );
		if ( empty( $result['success'] ) ) {
			$this->db->mark_mapping_sync_error( $mapping_id, (string) ( $result['error'] ?? '' ) );
			return $result;
		}

		$this->db->mark_mapping_sync_success( $mapping_id, $this->payload_hash( $payload ) );
		return $result;
	}

	/**
	 * Synchronizes a mapping from an action hook.
	 *
	 * @param int    $mapping_id Mapping ID.
	 * @param string $reason Reason.
	 * @return void
	 */
	public function sync_mapping_from_action( int $mapping_id, string $reason = 'changed' ): void {
		$this->sync_mapping( $mapping_id, $reason );
	}

	/**
	 * Notifies the SaaS that a mapping was deleted locally.
	 *
	 * @param object|array<string,mixed> $mapping Mapping data.
	 * @return array<string,mixed>
	 */
	public function notify_mapping_deleted( object|array $mapping ): array {
		if ( ! $this->pro_client->is_connected() ) {
			return array( 'success' => false );
		}

		$mapping_id = is_object( $mapping ) ? absint( $mapping->id ?? 0 ) : absint( $mapping['id'] ?? 0 );
		$sync_uuid  = is_object( $mapping ) ? sanitize_text_field( (string) ( $mapping->sync_uuid ?? '' ) ) : sanitize_text_field( (string) ( $mapping['sync_uuid'] ?? '' ) );
		if ( '' === $sync_uuid && $mapping_id > 0 ) {
			$sync_uuid = $this->db->ensure_mapping_sync_uuid( $mapping_id );
		}

		if ( '' === $sync_uuid ) {
			return array( 'success' => false );
		}

		return $this->pro_client->sync_mappings( 'delete', array(), array( $sync_uuid ), 'deleted' );
	}

	/**
	 * Handles delete notifications from an action hook.
	 *
	 * @param object|array<string,mixed> $mapping Mapping data.
	 * @return void
	 */
	public function delete_mapping_from_action( object|array $mapping ): void {
		$this->notify_mapping_deleted( $mapping );
	}

	/**
	 * Builds a sync-safe mapping payload.
	 *
	 * @param object $mapping Mapping row.
	 * @return array<string,mixed>
	 */
	private function mapping_payload( object $mapping ): array {
		$product_id = absint( $mapping->product_id );
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		return array(
			'id'                       => absint( $mapping->id ),
			'sync_uuid'                => sanitize_text_field( (string) ( $mapping->sync_uuid ?? '' ) ),
			'product_id'               => $product_id,
			'product_name'             => $product ? $product->get_name() : get_the_title( $product_id ),
			'product_sku'              => $product ? $product->get_sku() : '',
			'product_edit_url'         => admin_url( 'post.php?post=' . $product_id . '&action=edit' ),
			'competitor_name'          => sanitize_text_field( (string) $mapping->competitor_name ),
			'competitor_product_title' => sanitize_text_field( (string) ( $mapping->competitor_product_title ?? '' ) ),
			'competitor_url'           => esc_url_raw( (string) $mapping->competitor_url ),
			'price_selector'           => sanitize_text_field( (string) $mapping->price_selector ),
			'stock_selector'           => sanitize_text_field( (string) $mapping->stock_selector ),
			'currency'                 => sanitize_text_field( (string) $mapping->currency ),
			'min_margin_percentage'    => (float) $mapping->min_margin_percentage,
			'active'                   => ! empty( $mapping->active ),
			'last_price'               => null !== $mapping->last_price ? (float) $mapping->last_price : null,
			'last_stock_status'        => sanitize_text_field( (string) ( $mapping->last_stock_status ?? '' ) ),
			'last_checked_at'          => sanitize_text_field( (string) ( $mapping->last_checked_at ?? '' ) ),
			'updated_at'               => sanitize_text_field( (string) ( $mapping->updated_at ?? '' ) ),
			'sync_status'              => sanitize_key( (string) ( $mapping->sync_status ?? 'pending' ) ),
		);
	}

	/**
	 * Hashes one mapping payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	private function payload_hash( array $payload ): string {
		return hash( 'sha256', (string) wp_json_encode( $payload ) );
	}

	/**
	 * Stores the latest global sync status.
	 *
	 * @param bool   $success Success.
	 * @param string $message Message.
	 * @param int    $synced Synced count.
	 * @return array<string,mixed>
	 */
	private function store_global_result( bool $success, string $message, int $synced ): array {
		$this->db->update_settings(
			array(
				'last_mapping_sync_at'      => current_time( 'mysql' ),
				'last_mapping_sync_status'  => $success ? 'success' : 'error',
				'last_mapping_sync_message' => sanitize_text_field( $message ),
			)
		);

		return array(
			'success' => $success,
			'synced'  => $synced,
			'error'   => $success ? '' : $message,
			'message' => $message,
		);
	}
}
