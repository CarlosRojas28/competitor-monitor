<?php
/**
 * HTML parsing and normalization.
 *
 * @package WC_Competitor_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts price, stock, and page title from static HTML.
 */
class WC_Competitor_Monitor_Parser {

	/**
	 * Parses HTML.
	 *
	 * @param string $html HTML body.
	 * @param string $price_selector Optional price selector.
	 * @param string $stock_selector Optional stock selector.
	 * @return array<string,mixed>
	 */
	public function parse( string $html, string $price_selector = '', string $stock_selector = '' ): array {
		$title      = $this->extract_title( $html );
		$price_text = '';
		$stock_text = '';
		$price      = $this->extract_amazon_price( $html );

		if ( null === $price && '' !== trim( $price_selector ) ) {
			$price_text = $this->extract_text_by_selector( $html, $price_selector );
			$price      = $this->extract_price_from_text( $price_text );
		}

		if ( '' !== trim( $stock_selector ) ) {
			$stock_text = $this->extract_text_by_selector( $html, $stock_selector );
		}

		if ( null === $price ) {
			$price = $this->extract_price_from_json_ld( $html );
		}

		if ( null === $price ) {
			$price = $this->extract_price_from_meta_tags( $html );
		}

		if ( null === $price ) {
			$price = $this->extract_price_from_text( $html );
		}

		$stock = $this->detect_stock_status( $stock_text ?: $html );

		return array(
			'title'          => $title,
			'price'          => $price,
			'stock_status'   => $stock['status'],
			'raw_stock_text' => $stock['text'],
			'price_text'     => wp_strip_all_tags( html_entity_decode( $price_text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ),
		);
	}

	/**
	 * Extracts page title.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function extract_title( string $html ): string {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			return trim( wp_strip_all_tags( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ) );
		}

		return '';
	}

	/**
	 * Extracts a price using JSON-LD, meta tags, then regex fallback.
	 *
	 * @param string $content Text or HTML.
	 * @return float|null
	 */
	public function extract_price( string $content ): ?float {
		$price = $this->extract_price_from_json_ld( $content );
		if ( null !== $price ) {
			return $price;
		}

		$price = $this->extract_price_from_meta_tags( $content );
		if ( null !== $price ) {
			return $price;
		}

		return $this->extract_price_from_text( $content );
	}

	/**
	 * Extracts price from plain text or static HTML using regex fallbacks.
	 *
	 * @param string $content Text or HTML.
	 * @return float|null
	 */
	public function extract_price_from_text( string $content ): ?float {
		$content = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$content = preg_replace( '/\s+/u', ' ', (string) $content );

		$patterns = array(
			'/"price"\s*:\s*"?([0-9]+(?:[.,][0-9]{1,4})?)"?/i',
			'/(?:USD|EUR|GBP|CAD|AUD|\x{20AC}|\$|\x{00A3})\s*([0-9]{1,3}(?:[.,\s][0-9]{3})*(?:[.,][0-9]{2,4})|[0-9]+(?:[.,][0-9]{2,4})?)/iu',
			'/([0-9]{1,3}(?:[.,\s][0-9]{3})*(?:[.,][0-9]{2,4})|[0-9]+(?:[.,][0-9]{2,4})?)\s*(?:USD|EUR|GBP|CAD|AUD|\x{20AC}|\$|\x{00A3})/iu',
			'/\b([0-9]+[.,][0-9]{2,4})\b/u',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $matches ) ) {
				$price = $this->normalize_price( $matches[1] );
				if ( null !== $price ) {
					return $price;
				}
			}
		}

		return null;
	}

	/**
	 * Extracts price from JSON-LD product offers.
	 *
	 * @param string $html HTML.
	 * @return float|null
	 */
	public function extract_price_from_json_ld( string $html ): ?float {
		if ( ! preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches ) ) {
			return null;
		}

		foreach ( $matches[1] as $json ) {
			$json = html_entity_decode( trim( $json ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
			$data = json_decode( $json, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$data = json_decode( (string) preg_replace( '/,\s*([}\]])/', '$1', $json ), true );
			}

			if ( is_array( $data ) ) {
				$price = $this->find_price_in_structured_data( $data );
				if ( null !== $price ) {
					return $price;
				}
			}
		}

		return null;
	}

	/**
	 * Extracts price from common product meta tags.
	 *
	 * @param string $html HTML.
	 * @return float|null
	 */
	public function extract_price_from_meta_tags( string $html ): ?float {
		if ( ! preg_match_all( '/<meta\s+[^>]*>/i', $html, $matches ) ) {
			return null;
		}

		$price_keys = array(
			'product:price:amount',
			'og:price:amount',
			'twitter:data1',
			'price',
		);

		foreach ( $matches[0] as $meta_tag ) {
			$name     = $this->extract_attribute( $meta_tag, 'name' );
			$property = $this->extract_attribute( $meta_tag, 'property' );
			$itemprop = $this->extract_attribute( $meta_tag, 'itemprop' );
			$content  = $this->extract_attribute( $meta_tag, 'content' );
			$key      = strtolower( $name ?: ( $property ?: $itemprop ) );

			if ( '' !== $content && in_array( $key, $price_keys, true ) ) {
				$price = $this->normalize_price( $content );
				if ( null !== $price ) {
					return $price;
				}
			}
		}

		return null;
	}

	/**
	 * Extracts the main Amazon buy-box price before generic coupon/discount text.
	 *
	 * @param string $html HTML.
	 * @return float|null
	 */
	private function extract_amazon_price( string $html ): ?float {
		if ( ! str_contains( $html, 'priceToPay' ) && ! str_contains( $html, 'corePrice' ) && ! str_contains( $html, 'apex_desktop' ) ) {
			return null;
		}

		foreach ( $this->amazon_price_sections( $html ) as $section ) {
			$price = $this->extract_amazon_whole_fraction_price( $section );
			if ( null !== $price ) {
				return $price;
			}

			$price = $this->extract_amazon_offscreen_price( $section );
			if ( null !== $price ) {
				return $price;
			}
		}

		return null;
	}

	/**
	 * Returns bounded Amazon price blocks in priority order.
	 *
	 * @param string $html HTML.
	 * @return array<int,string>
	 */
	private function amazon_price_sections( string $html ): array {
		$tokens = array(
			'id="priceToPay"',
			'id=\'priceToPay\'',
			'id="corePrice_feature_div"',
			'id=\'corePrice_feature_div\'',
			'id="corePriceDisplay_desktop_feature_div"',
			'id=\'corePriceDisplay_desktop_feature_div\'',
			'id="corePrice_desktop"',
			'id=\'corePrice_desktop\'',
			'id="apex_desktop"',
			'id=\'apex_desktop\'',
			'id="tp_price_block_total_price_ww"',
			'id=\'tp_price_block_total_price_ww\'',
			'id="priceblock_ourprice"',
			'id=\'priceblock_ourprice\'',
			'id="priceblock_dealprice"',
			'id=\'priceblock_dealprice\'',
			'id="price_inside_buybox"',
			'id=\'price_inside_buybox\'',
		);
		$sections = array();
		$seen     = array();
		$length   = strlen( $html );

		foreach ( $tokens as $token ) {
			$index = strpos( $html, $token );
			if ( false === $index ) {
				continue;
			}

			$start = max( 0, $index - 1200 );
			$end   = min( $length, $index + 5000 );
			$key   = $start . ':' . $end;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$sections[]  = substr( $html, $start, $end - $start );
			$seen[ $key ] = true;
		}

		return $sections;
	}

	/**
	 * Extracts Amazon price from whole/fraction spans.
	 *
	 * @param string $section HTML section.
	 * @return float|null
	 */
	private function extract_amazon_whole_fraction_price( string $section ): ?float {
		if ( ! preg_match_all( '/<span\b[^>]*class=["\'][^"\']*\ba-price-whole\b[^"\']*["\'][^>]*>([\s\S]{0,260}?)<\/span>/i', $section, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		foreach ( $matches[1] as $index => $match ) {
			if ( $index >= 10 ) {
				break;
			}

			$whole = $this->normalize_amazon_price_part( wp_strip_all_tags( $match[0] ) );
			if ( '' === $whole ) {
				continue;
			}

			$after    = substr( $section, (int) $matches[0][ $index ][1], 1200 );
			$fraction = '';
			$symbol   = "\xE2\x82\xAC";

			if ( preg_match( '/<span\b[^>]*class=["\'][^"\']*\ba-price-fraction\b[^"\']*["\'][^>]*>([\s\S]{0,120}?)<\/span>/i', $after, $fraction_match ) ) {
				$fraction = $this->normalize_amazon_price_part( wp_strip_all_tags( $fraction_match[1] ) );
			}

			if ( preg_match( '/<span\b[^>]*class=["\'][^"\']*\ba-price-symbol\b[^"\']*["\'][^>]*>([\s\S]{0,80}?)<\/span>/i', $after, $symbol_match ) ) {
				$symbol_text = trim( wp_strip_all_tags( html_entity_decode( $symbol_match[1], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ) );
				if ( '' !== $symbol_text ) {
					$symbol = $symbol_text;
				}
			}

			$raw   = '' !== $fraction ? $whole . ',' . $fraction . $symbol : $whole . $symbol;
			$price = $this->normalize_price( $raw );
			if ( null !== $price ) {
				return $price;
			}
		}

		return null;
	}

	/**
	 * Extracts Amazon price from hidden accessible price text.
	 *
	 * @param string $section HTML section.
	 * @return float|null
	 */
	private function extract_amazon_offscreen_price( string $section ): ?float {
		if ( ! preg_match_all( '/<span\b[^>]*class=["\'][^"\']*\ba-offscreen\b[^"\']*["\'][^>]*>([\s\S]{0,160}?)<\/span>/i', $section, $matches ) ) {
			return null;
		}

		foreach ( array_slice( $matches[1], 0, 10 ) as $raw ) {
			$text  = trim( wp_strip_all_tags( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ) );
			$price = $this->normalize_price( $text );
			if ( null !== $price ) {
				return $price;
			}
		}

		return null;
	}

	/**
	 * Keeps only numeric Amazon price span content.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function normalize_amazon_price_part( string $value ): string {
		return trim( (string) preg_replace( '/[^0-9]/', '', $value ) );
	}

	/**
	 * Normalizes localized prices to floats.
	 *
	 * @param string $raw Raw price.
	 * @return float|null
	 */
	public function normalize_price( string $raw ): ?float {
		$value = preg_replace( '/[^0-9,.\-]/u', '', $raw );
		$value = trim( (string) $value );

		if ( '' === $value || '-' === $value ) {
			return null;
		}

		$last_comma = strrpos( $value, ',' );
		$last_dot   = strrpos( $value, '.' );

		if ( false !== $last_comma && false !== $last_dot ) {
			$decimal_separator  = $last_comma > $last_dot ? ',' : '.';
			$thousand_separator = ',' === $decimal_separator ? '.' : ',';
			$value              = str_replace( $thousand_separator, '', $value );
			$value              = str_replace( $decimal_separator, '.', $value );
		} elseif ( false !== $last_comma ) {
			$value = $this->normalize_single_separator_price( $value, ',' );
		} elseif ( false !== $last_dot ) {
			$value = $this->normalize_single_separator_price( $value, '.' );
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$price = (float) $value;
		return $price >= 0 ? $price : null;
	}

	/**
	 * Detects stock status from text.
	 *
	 * @param string $content Text or HTML.
	 * @return array{status:string,text:string}
	 */
	public function detect_stock_status( string $content ): array {
		$text = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', (string) $text ) ) );

		$out_terms = array(
			'out of stock',
			'sold out',
			'unavailable',
			'currently unavailable',
			'not available',
			'agotado',
			'sin stock',
			'no disponible',
			'fuera de stock',
		);

		foreach ( $out_terms as $term ) {
			if ( str_contains( $text, $term ) ) {
				return array(
					'status' => 'out_of_stock',
					'text'   => $term,
				);
			}
		}

		$in_terms = array(
			'in stock',
			'available',
			'add to cart',
			'disponible',
			'en stock',
			'anadir al carrito',
			'comprar ahora',
		);

		foreach ( $in_terms as $term ) {
			if ( str_contains( $text, remove_accents( $term ) ) ) {
				return array(
					'status' => 'in_stock',
					'text'   => $term,
				);
			}
		}

		return array(
			'status' => 'unknown',
			'text'   => '',
		);
	}

	/**
	 * Extracts text by a small safe subset of CSS selectors.
	 *
	 * @param string $html HTML.
	 * @param string $selector Selector.
	 * @return string
	 */
	public function extract_text_by_selector( string $html, string $selector ): string {
		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			return '';
		}

		$selector = trim( explode( ',', $selector )[0] );
		if ( '' === $selector ) {
			return '';
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$xpath_query = $this->css_to_xpath( $selector );
		if ( '' === $xpath_query ) {
			return '';
		}

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( $xpath_query );
		if ( ! $nodes || 0 === $nodes->length ) {
			return '';
		}

		$texts = array();
		foreach ( $nodes as $node ) {
			$text = trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) );
			if ( '' === $text && $node instanceof DOMElement ) {
				foreach ( array( 'content', 'value', 'data-price', 'aria-label' ) as $attribute ) {
					$attribute_value = trim( (string) $node->getAttribute( $attribute ) );
					if ( '' !== $attribute_value ) {
						$text = $attribute_value;
						break;
					}
				}
			}
			if ( '' !== $text ) {
				$texts[] = $text;
			}
			if ( count( $texts ) >= 3 ) {
				break;
			}
		}

		return implode( ' ', $texts );
	}

	/**
	 * Normalizes a price with only one separator type.
	 *
	 * @param string $value Value.
	 * @param string $separator Separator.
	 * @return string
	 */
	private function normalize_single_separator_price( string $value, string $separator ): string {
		$last = strrpos( $value, $separator );
		if ( false === $last ) {
			return $value;
		}

		$decimals = strlen( $value ) - $last - 1;
		if ( $decimals >= 1 && $decimals <= 4 ) {
			$value = str_replace( $separator, '.', $value );
			$first = strpos( $value, '.' );
			$last  = strrpos( $value, '.' );
			if ( false !== $first && false !== $last && $first !== $last ) {
				$value = str_replace( '.', '', substr( $value, 0, $last ) ) . substr( $value, $last );
			}
			return $value;
		}

		return str_replace( $separator, '', $value );
	}

	/**
	 * Recursively finds a price in decoded structured data.
	 *
	 * @param mixed $data Structured data.
	 * @return float|null
	 */
	private function find_price_in_structured_data( mixed $data ): ?float {
		if ( ! is_array( $data ) ) {
			return null;
		}

		foreach ( array( 'price', 'lowPrice', 'highPrice' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$price = $this->normalize_price( (string) $data[ $key ] );
				if ( null !== $price ) {
					return $price;
				}
			}
		}

		foreach ( array( 'offers', '@graph' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$price = $this->find_price_in_structured_data( $data[ $key ] );
				if ( null !== $price ) {
					return $price;
				}
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$price = $this->find_price_in_structured_data( $value );
				if ( null !== $price ) {
					return $price;
				}
			}
		}

		return null;
	}

	/**
	 * Extracts an attribute from an HTML tag.
	 *
	 * @param string $tag HTML tag.
	 * @param string $attribute Attribute name.
	 * @return string
	 */
	private function extract_attribute( string $tag, string $attribute ): string {
		if ( preg_match( '/\s' . preg_quote( $attribute, '/' ) . '\s*=\s*(["\'])(.*?)\1/i', $tag, $matches ) ) {
			return html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		}

		return '';
	}

	/**
	 * Converts a limited CSS selector subset to XPath.
	 *
	 * @param string $selector CSS selector.
	 * @return string
	 */
	private function css_to_xpath( string $selector ): string {
		$selector = preg_replace( '/\s*>\s*/', ' ', trim( $selector ) );
		$parts    = preg_split( '/\s+/', (string) $selector );
		$xpath    = '.';

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}

			$xpath_part = $this->css_part_to_xpath( $part );
			if ( '' === $xpath_part ) {
				return '';
			}
			$xpath .= '//' . $xpath_part;
		}

		return $xpath;
	}

	/**
	 * Converts a single CSS selector component.
	 *
	 * @param string $part Selector component.
	 * @return string
	 */
	private function css_part_to_xpath( string $part ): string {
		$part       = preg_replace( '/:.+$/', '', $part );
		$tag        = '*';
		$conditions = array();

		if ( preg_match( '/^([a-z0-9_-]+)?#([a-z0-9_-]+)$/i', (string) $part, $matches ) ) {
			$tag          = ! empty( $matches[1] ) ? strtolower( $matches[1] ) : '*';
			$conditions[] = '@id=' . $this->xpath_literal( $matches[2] );
		} elseif ( preg_match( '/^([a-z0-9_-]+)?\.([a-z0-9_-]+)$/i', (string) $part, $matches ) ) {
			$tag          = ! empty( $matches[1] ) ? strtolower( $matches[1] ) : '*';
			$conditions[] = 'contains(concat(" ", normalize-space(@class), " "), ' . $this->xpath_literal( ' ' . $matches[2] . ' ' ) . ')';
		} elseif ( preg_match( '/^([a-z0-9_-]+)?\[([a-z0-9_-]+)(?:=["\']?([^"\']+)["\']?)?\]$/i', (string) $part, $matches ) ) {
			$tag  = ! empty( $matches[1] ) ? strtolower( $matches[1] ) : '*';
			$attr = strtolower( $matches[2] );
			if ( isset( $matches[3] ) && '' !== $matches[3] ) {
				$conditions[] = '@' . $attr . '=' . $this->xpath_literal( $matches[3] );
			} else {
				$conditions[] = '@' . $attr;
			}
		} elseif ( preg_match( '/^[a-z0-9_-]+$/i', (string) $part ) ) {
			$tag = strtolower( $part );
		} else {
			return '';
		}

		return $tag . ( $conditions ? '[' . implode( ' and ', $conditions ) . ']' : '' );
	}

	/**
	 * Creates a safe XPath string literal.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function xpath_literal( string $value ): string {
		if ( ! str_contains( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( ! str_contains( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$parts = explode( "'", $value );
		return "concat('" . implode( "', \"'\", '", $parts ) . "')";
	}
}
