<?php
/**
 * Legacy SKU Resolver Service.
 *
 * Resolves SKUs to their canonical form, supporting both native QSA format
 * and legacy module SKUs via the SKU mapping table.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use Quadica\QSA_Engraving\Database\SKU_Mapping_Repository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service for resolving SKUs to their canonical form.
 *
 * Handles both native QSA SKUs (e.g., STARa-34924) and legacy SKUs
 * (e.g., SP-01) by mapping them to 4-letter canonical design codes.
 *
 * Key behaviors:
 * - Native QSA SKUs are parsed directly without database lookup
 * - Legacy SKUs are resolved via the SKU mapping table
 * - Unmapped legacy SKUs return null (silently ignored by workflow)
 * - Results are cached per request for performance
 *
 * @since 1.1.0
 */
class Legacy_SKU_Resolver {

    /**
     * Regex pattern for native QSA SKUs.
     *
     * Pattern: 4 uppercase letters + optional lowercase revision + hyphen + 5 digits
     * Examples: CORE-91247, STARa-34924, SOLO-12345
     *
     * @var string
     */
    public const QSA_SKU_PATTERN = '/^([A-Z]{4})([a-z])?-(\d{5})$/';

    /**
     * Synthetic config suffix for legacy SKUs.
     *
     * Legacy SKUs don't have a real order-based config number, so we use
     * this synthetic value for config lookup purposes.
     *
     * @var string
     */
    public const LEGACY_CONFIG_SUFFIX = 'LEGAC';

    /**
     * SKU Mapping Repository instance.
     *
     * @var SKU_Mapping_Repository
     */
    private SKU_Mapping_Repository $repository;

    /**
     * Runtime cache for resolved SKUs.
     *
     * Keys are SKU strings, values are resolution arrays or null.
     *
     * @var array<string, array|null>
     */
    private array $cache = array();

    /**
     * Constructor.
     *
     * @param SKU_Mapping_Repository|null $repository Optional repository instance.
     */
    public function __construct( ?SKU_Mapping_Repository $repository = null ) {
        $this->repository = $repository ?? new SKU_Mapping_Repository();
    }

    /**
     * Resolve a SKU to its canonical form.
     *
     * Returns a structured array if the SKU can be resolved:
     * - is_legacy: bool - Whether this is a mapped legacy SKU
     * - original_sku: string - The input SKU exactly as provided
     * - canonical_code: string - 4-letter design code (e.g., STAR, SP01)
     * - revision: string|null - Revision letter (a-z) or null
     * - canonical_sku: string - Synthetic SKU for config lookup
     * - mapping_id: int|null - ID of matching mapping (null for QSA SKUs)
     *
     * Returns null if the SKU cannot be resolved:
     * - Not a valid QSA format AND
     * - No mapping found in the SKU mapping table
     *
     * @param string $sku The SKU to resolve.
     * @return array|null Resolution data or null if unresolvable.
     */
    public function resolve( string $sku ): ?array {
        // Normalize whitespace first to ensure consistent cache keys.
        $sku = trim( $sku );

        // Check cache with normalized key.
        if ( array_key_exists( $sku, $this->cache ) ) {
            return $this->cache[ $sku ];
        }

        if ( '' === $sku ) {
            $this->cache[ $sku ] = null;
            return null;
        }

        // Try native QSA format first.
        if ( $this->is_qsa_sku( $sku ) ) {
            $result = $this->resolve_qsa_sku( $sku );
            $this->cache[ $sku ] = $result;
            return $result;
        }

        // Try legacy mapping lookup.
        $result = $this->resolve_legacy_sku( $sku );
        $this->cache[ $sku ] = $result;
        return $result;
    }

    /**
     * Check if a SKU matches the native QSA format.
     *
     * Pattern: 4 uppercase letters + optional lowercase revision + hyphen + 5 digits
     *
     * @param string $sku The SKU to check.
     * @return bool True if the SKU matches QSA format.
     */
    public function is_qsa_sku( string $sku ): bool {
        return 1 === preg_match( self::QSA_SKU_PATTERN, $sku );
    }

    /**
     * Check if a SKU is a legacy format (not QSA pattern).
     *
     * @param string $sku The SKU to check.
     * @return bool True if the SKU is NOT a QSA format.
     */
    public function is_legacy_sku( string $sku ): bool {
        return ! $this->is_qsa_sku( $sku );
    }

    /**
     * Clear the resolution cache.
     *
     * Useful for testing or after bulk mapping changes.
     */
    public function clear_cache(): void {
        $this->cache = array();
    }

    /**
     * Get the number of cached resolutions.
     *
     * Primarily for testing/debugging.
     *
     * @return int Number of cached entries.
     */
    public function get_cache_count(): int {
        return count( $this->cache );
    }

    /**
     * Resolve a native QSA format SKU.
     *
     * @param string $sku The QSA-format SKU.
     * @return array Resolution data.
     */
    private function resolve_qsa_sku( string $sku ): array {
        preg_match( self::QSA_SKU_PATTERN, $sku, $matches );

        $design   = $matches[1];
        $revision = isset( $matches[2] ) && '' !== $matches[2] ? $matches[2] : null;
        $config   = $matches[3];

        // Build canonical SKU (same as original for QSA).
        $canonical_sku = $design;
        if ( null !== $revision ) {
            $canonical_sku .= $revision;
        }
        $canonical_sku .= '-' . $config;

        return array(
            'is_legacy'      => false,
            'original_sku'   => $sku,
            'canonical_code' => $design,
            'revision'       => $revision,
            'canonical_sku'  => $canonical_sku,
            'mapping_id'     => null,
            'config_number'  => $config,
        );
    }

    /**
     * Resolve a legacy SKU via mapping table lookup.
     *
     * @param string $sku The legacy SKU.
     * @return array|null Resolution data or null if no mapping found.
     */
    private function resolve_legacy_sku( string $sku ): ?array {
        // Check if mapping table exists.
        if ( ! $this->repository->table_exists() ) {
            return null;
        }

        // Look up mapping.
        $mapping = $this->repository->find_mapping( $sku );

        if ( null === $mapping ) {
            // No mapping found - SKU is unknown.
            return null;
        }

        $canonical_code = $mapping['canonical_code'];
        $revision       = ! empty( $mapping['revision'] ) ? $mapping['revision'] : null;

        // Build synthetic canonical SKU for legacy modules.
        // Format: {CODE}{rev}-LEGAC (e.g., SP01-LEGAC, SP01a-LEGAC)
        $canonical_sku = $canonical_code;
        if ( null !== $revision ) {
            $canonical_sku .= $revision;
        }
        $canonical_sku .= '-' . self::LEGACY_CONFIG_SUFFIX;

        return array(
            'is_legacy'      => true,
            'original_sku'   => $sku,
            'canonical_code' => $canonical_code,
            'revision'       => $revision,
            'canonical_sku'  => $canonical_sku,
            'mapping_id'     => (int) $mapping['id'],
            'config_number'  => self::LEGACY_CONFIG_SUFFIX,
        );
    }

    /**
     * Batch resolve multiple SKUs.
     *
     * Returns an array of resolutions keyed by original SKU.
     * Unresolvable SKUs have null values.
     *
     * @param array $skus Array of SKUs to resolve.
     * @return array Associative array of SKU => resolution|null.
     */
    public function resolve_batch( array $skus ): array {
        $results = array();

        foreach ( $skus as $sku ) {
            $results[ $sku ] = $this->resolve( $sku );
        }

        return $results;
    }

    /**
     * Filter an array of SKUs to only include resolvable ones.
     *
     * @param array $skus Array of SKUs to filter.
     * @return array Array of SKUs that can be resolved.
     */
    public function filter_resolvable( array $skus ): array {
        return array_filter(
            $skus,
            fn( $sku ) => null !== $this->resolve( $sku )
        );
    }

    /**
     * Get the base type string for grouping purposes.
     *
     * Returns the canonical code + revision (if any).
     * Examples: "STAR", "STARa", "SP01", "SP01a"
     *
     * @param string $sku The SKU to get base type for.
     * @return string|null Base type string or null if unresolvable.
     */
    public function get_base_type( string $sku ): ?string {
        $resolution = $this->resolve( $sku );

        if ( null === $resolution ) {
            return null;
        }

        $base = $resolution['canonical_code'];
        if ( ! empty( $resolution['revision'] ) ) {
            $base .= $resolution['revision'];
        }

        return $base;
    }
}
