<?php
/**
 * Renders the WooCommerce PDF Catalog settings page.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Settings_Page
 *
 * Outputs the complete HTML form for the plugin configuration under
 * WooCommerce > Settings > PDF Catalog.
 */
class WPC_Settings_Page {

	/** @var WPC_Settings */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WPC_Settings $settings Plugin settings instance.
	 */
	public function __construct( WPC_Settings $settings ) {
		$this->settings = $settings;
	}

	// ─── Main render ────────────────────────────────────────────────────────

	/**
	 * Render the full settings page.
	 *
	 * Hooked to: woocommerce_settings_wpc_pdf_catalog
	 */
	public function render() {
		$s = $this->settings->get_all();
		?>
		<div class="wpc-settings-wrap">

			<?php $this->render_tcpdf_notice(); ?>

			<?php $this->render_section_header( __( 'Product Fields', 'woo-pdf-catalog' ), __( 'Choose which fields to include in the PDF. Title is always included.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_fields_section( $s ); ?>

			<?php $this->render_section_header( __( 'Custom Fields', 'woo-pdf-catalog' ), __( 'Select which custom/ACF fields to include in the PDF. Only visible when "Custom Fields (meta)" is checked above.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_custom_fields_section( $s ); ?>

			<?php $this->render_section_header( __( 'Publication Status', 'woo-pdf-catalog' ), __( 'Define which products to include based on their publication status.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_status_section( $s ); ?>

			<?php $this->render_section_header( __( 'Ordenación de productos', 'woo-pdf-catalog' ), __( 'Elige cómo se ordenan los productos en el catálogo PDF.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_sort_section( $s ); ?>

			<?php $this->render_section_header( __( 'Forced Products', 'woo-pdf-catalog' ), __( 'These products will always be included regardless of other filters.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_product_selector( 'forced_products', $s['forced_products'] ); ?>

			<?php $this->render_section_header( __( 'Excluded Products', 'woo-pdf-catalog' ), __( 'These products will never be included regardless of other filters.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_product_selector( 'excluded_products', $s['excluded_products'] ); ?>

			<?php $this->render_section_header( __( 'PDF Appearance', 'woo-pdf-catalog' ), __( 'Customise colors, fonts, margins and page decorations.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_appearance_section( $s ); ?>

			<?php $this->render_section_header( __( 'File Options', 'woo-pdf-catalog' ), __( 'Configure the output filename.', 'woo-pdf-catalog' ) ); ?>
			<?php $this->render_file_section( $s ); ?>

			<?php $this->render_download_button(); ?>

		</div><!-- .wpc-settings-wrap -->
		<?php
	}

	// ─── Section helpers ─────────────────────────────────────────────────────

	private function render_section_header( $title, $description = '' ) {
		echo '<h2 class="wpc-section-title">' . esc_html( $title ) . '</h2>';
		if ( $description ) {
			echo '<p class="wpc-section-desc">' . esc_html( $description ) . '</p>';
		}
		echo '<table class="form-table wpc-table">';
	}

	private function render_custom_fields_section( $s ) {
		$meta_fields           = $this->settings->get_public_meta_keys();
		$enabled_custom_fields = (array) $s['enabled_custom_fields'];
		$is_enabled            = in_array( 'custom_fields', (array) $s['enabled_fields'], true );

		if ( ! $is_enabled ) {
			?>
			<tr>
				<td colspan="2">
					<p class="description" style="font-style:italic;color:#999;padding:8px 0;">
						<?php esc_html_e( 'Enable "Custom Fields (meta)" in the Product Fields section above to configure individual fields.', 'woo-pdf-catalog' ); ?>
					</p>
				</td>
			</tr>
			</table>
			<?php
			return;
		}

		if ( empty( $meta_fields ) ) {
			?>
			<tr>
				<td colspan="2">
					<p class="description"><?php esc_html_e( 'No custom fields found in your products yet.', 'woo-pdf-catalog' ); ?></p>
				</td>
			</tr>
			</table>
			<?php
			return;
		}
		?>
		<tr>
			<th><?php esc_html_e( 'Fields to include', 'woo-pdf-catalog' ); ?></th>
			<td>
				<div class="wpc-checkbox-grid">
					<?php foreach ( $meta_fields as $field ) : ?>
						<label class="wpc-checkbox-label">
							<input
								type="checkbox"
								name="enabled_custom_fields[]"
								value="<?php echo esc_attr( $field['key'] ); ?>"
								<?php checked( in_array( $field['key'], $enabled_custom_fields, true ) ); ?>
							/>
							<?php echo esc_html( $field['label'] ); ?>
							<?php if ( $field['label'] !== $field['key'] ) : ?>
								<span style="color:#999;font-size:0.85em;margin-left:4px;">(<?php echo esc_html( $field['key'] ); ?>)</span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description">
					<?php esc_html_e( 'Only the checked fields will appear in the PDF. ACF fields show their label and key name.', 'woo-pdf-catalog' ); ?>
				</p>
			</td>
		</tr>
		</table>
		<?php
	}

	private function render_fields_section( $s ) {
		$available      = $this->settings->get_available_fields();
		$enabled_fields = (array) $s['enabled_fields'];
		?>
		<tr>
			<th><?php esc_html_e( 'Enabled Fields', 'woo-pdf-catalog' ); ?></th>
			<td>
				<div class="wpc-checkbox-grid">
					<?php foreach ( $available as $field ) : ?>
						<label class="wpc-checkbox-label">
							<input
								type="checkbox"
								name="enabled_fields[]"
								value="<?php echo esc_attr( $field['id'] ); ?>"
								<?php checked( in_array( $field['id'], $enabled_fields, true ) ); ?>
							/>
							<?php echo esc_html( $field['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'The product title is always included as the first element.', 'woo-pdf-catalog' ); ?></p>
			</td>
		</tr>
		</table>
		<?php
	}

	private function render_status_section( $s ) {
		$status = $s['publication_status'];
		?>
		<tr>
			<th><?php esc_html_e( 'Include Products', 'woo-pdf-catalog' ); ?></th>
			<td>
				<label>
					<input type="radio" name="publication_status" value="publish" <?php checked( 'publish', $status ); ?> />
					<?php esc_html_e( 'Published products only', 'woo-pdf-catalog' ); ?>
				</label><br/>
				<label>
					<input type="radio" name="publication_status" value="any" <?php checked( 'any', $status ); ?> />
					<?php esc_html_e( 'All products (including drafts, pending, private…)', 'woo-pdf-catalog' ); ?>
				</label>
			</td>
		</tr>
		</table>
		<?php
	}

	private function render_sort_section( $s ) {
		$sort_by        = isset( $s['sort_by'] )         ? $s['sort_by']                  : 'menu_order';
		$sort_prefix    = isset( $s['sort_tag_prefix'] ) ? $s['sort_tag_prefix']           : 'D.O.';
		$saved_order    = isset( $s['sort_tag_order'] )  ? (array) $s['sort_tag_order']    : array();

		// Fetch all product_tag terms that match the prefix.
		$all_tags    = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );
		$match_tags  = array();
		if ( ! is_wp_error( $all_tags ) ) {
			foreach ( $all_tags as $tag ) {
				if ( '' === $sort_prefix || 0 === mb_stripos( $tag->name, $sort_prefix ) ) {
					$match_tags[ $tag->slug ] = $tag->name;
				}
			}
		}

		// Merge: saved order first (preserving position), then any new tags not yet in the list.
		$ordered_slugs = array();
		foreach ( $saved_order as $slug ) {
			if ( isset( $match_tags[ $slug ] ) ) {
				$ordered_slugs[] = $slug;
			}
		}
		foreach ( $match_tags as $slug => $name ) {
			if ( ! in_array( $slug, $ordered_slugs, true ) ) {
				$ordered_slugs[] = $slug;
			}
		}
		?>
		<tr>
			<th><?php esc_html_e( 'Ordenar por', 'woo-pdf-catalog' ); ?></th>
			<td>
				<select name="sort_by" id="wpc-sort-by">
					<option value="menu_order" <?php selected( 'menu_order', $sort_by ); ?>>
						<?php esc_html_e( 'Orden del menú (por defecto)', 'woo-pdf-catalog' ); ?>
					</option>
					<option value="title" <?php selected( 'title', $sort_by ); ?>>
						<?php esc_html_e( 'Título del producto (A→Z)', 'woo-pdf-catalog' ); ?>
					</option>
					<option value="date" <?php selected( 'date', $sort_by ); ?>>
						<?php esc_html_e( 'Fecha (más antiguos primero)', 'woo-pdf-catalog' ); ?>
					</option>
					<option value="tag" <?php selected( 'tag', $sort_by ); ?>>
						<?php esc_html_e( 'Etiqueta de producto (ej. Denominación de Origen)', 'woo-pdf-catalog' ); ?>
					</option>
				</select>
			</td>
		</tr>

		<tr class="wpc-sort-tag-row" <?php echo 'tag' !== $sort_by ? 'style="display:none;"' : ''; ?>>
			<th><?php esc_html_e( 'Prefijo de etiqueta', 'woo-pdf-catalog' ); ?></th>
			<td>
				<input type="text" name="sort_tag_prefix" value="<?php echo esc_attr( $sort_prefix ); ?>" class="regular-text" placeholder="D.O." />
				<p class="description">
					<?php esc_html_e( 'Solo se usarán para agrupar las etiquetas cuyo nombre empiece con este prefijo. Déjalo vacío para usar todas las etiquetas.', 'woo-pdf-catalog' ); ?>
				</p>
			</td>
		</tr>

		<tr class="wpc-sort-tag-row" <?php echo 'tag' !== $sort_by ? 'style="display:none;"' : ''; ?>>
			<th><?php esc_html_e( 'Orden de etiquetas', 'woo-pdf-catalog' ); ?></th>
			<td>
				<?php if ( ! empty( $ordered_slugs ) ) : ?>
					<p class="description" style="margin-bottom:8px;">
						<?php esc_html_e( 'Arrastra y suelta para definir el orden en que aparecen los grupos de D.O. en el PDF.', 'woo-pdf-catalog' ); ?>
					</p>
					<ul id="wpc-tag-sort-list" class="wpc-tag-sort-list">
						<?php foreach ( $ordered_slugs as $slug ) : ?>
							<li data-slug="<?php echo esc_attr( $slug ); ?>">
								<input type="hidden" name="sort_tag_order[]" value="<?php echo esc_attr( $slug ); ?>" />
								<span class="dashicons dashicons-menu wpc-drag-handle"></span>
								<span class="wpc-tag-name"><?php echo esc_html( $match_tags[ $slug ] ); ?></span>
								<span class="wpc-tag-count">(<?php echo esc_html( $this->count_products_with_tag( $slug ) ); ?>)</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="description" style="font-style:italic;color:#999;">
						<?php esc_html_e( 'No se encontraron etiquetas con ese prefijo. Crea etiquetas de producto que empiecen con el prefijo indicado.', 'woo-pdf-catalog' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		</table>

		<style>
			.wpc-tag-sort-list {
				margin: 0;
				padding: 0;
				max-width: 500px;
			}
			.wpc-tag-sort-list li {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px 12px;
				margin-bottom: 4px;
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 4px;
				cursor: grab;
				user-select: none;
				transition: box-shadow 0.15s, border-color 0.15s;
			}
			.wpc-tag-sort-list li:hover {
				border-color: #2271b1;
			}
			.wpc-tag-sort-list li.wpc-dragging {
				opacity: 0.5;
				box-shadow: 0 2px 8px rgba(0,0,0,0.15);
			}
			.wpc-tag-sort-list li.wpc-drag-over {
				border-top: 3px solid #2271b1;
			}
			.wpc-drag-handle {
				color: #999;
				cursor: grab;
			}
			.wpc-tag-name {
				font-weight: 600;
			}
			.wpc-tag-count {
				color: #888;
				font-size: 0.9em;
			}
		</style>

		<script>
		jQuery(function($) {
			// Show/hide tag options based on sort_by selection.
			$('#wpc-sort-by').on('change', function() {
				$('.wpc-sort-tag-row').toggle( $(this).val() === 'tag' );
			});

			// Lightweight drag-and-drop sorting (no jQuery UI dependency).
			var list = document.getElementById('wpc-tag-sort-list');
			if (list) {
				var dragged = null;
				list.addEventListener('dragstart', function(e) {
					dragged = e.target.closest('li');
					if (dragged) dragged.classList.add('wpc-dragging');
				});
				list.addEventListener('dragend', function() {
					if (dragged) dragged.classList.remove('wpc-dragging');
					[].forEach.call(list.querySelectorAll('li'), function(li) {
						li.classList.remove('wpc-drag-over');
					});
					dragged = null;
				});
				list.addEventListener('dragover', function(e) {
					e.preventDefault();
					var target = e.target.closest('li');
					if (target && target !== dragged) {
						[].forEach.call(list.querySelectorAll('li'), function(li) {
							li.classList.remove('wpc-drag-over');
						});
						target.classList.add('wpc-drag-over');
					}
				});
				list.addEventListener('drop', function(e) {
					e.preventDefault();
					var target = e.target.closest('li');
					if (target && target !== dragged) {
						var items = [].slice.call(list.children);
						var dragIdx = items.indexOf(dragged);
						var dropIdx = items.indexOf(target);
						if (dragIdx < dropIdx) {
							target.parentNode.insertBefore(dragged, target.nextSibling);
						} else {
							target.parentNode.insertBefore(dragged, target);
						}
					}
					[].forEach.call(list.querySelectorAll('li'), function(li) {
						li.classList.remove('wpc-drag-over');
					});
				});
				// Make items draggable.
				[].forEach.call(list.querySelectorAll('li'), function(li) {
					li.setAttribute('draggable', 'true');
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Count products associated with a given tag slug.
	 *
	 * @param string $slug Tag slug.
	 * @return int
	 */
	private function count_products_with_tag( $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_tag' );
		return $term ? (int) $term->count : 0;
	}


	private function render_product_selector( $field_name, $selected_ids ) {
		$selected_ids = array_filter( array_map( 'absint', (array) $selected_ids ) );
		?>
		<tr>
			<th><?php echo esc_html( 'forced_products' === $field_name ? __( 'Forced Products', 'woo-pdf-catalog' ) : __( 'Excluded Products', 'woo-pdf-catalog' ) ); ?></th>
			<td>
				<select
					name="<?php echo esc_attr( $field_name ); ?>[]"
					id="wpc-<?php echo esc_attr( $field_name ); ?>"
					class="wpc-product-select"
					multiple="multiple"
					style="width:100%;max-width:600px;"
				>
					<?php
					foreach ( $selected_ids as $id ) {
						$product = wc_get_product( $id );
						if ( $product ) {
							printf(
								'<option value="%d" selected="selected">#%d – %s</option>',
								(int) $id,
								(int) $id,
								esc_html( $product->get_name() )
							);
						}
					}
					?>
				</select>
				<p class="description">
					<?php
					echo esc_html(
						'forced_products' === $field_name
							? __( 'Search and select products that must always appear in the PDF.', 'woo-pdf-catalog' )
							: __( 'Search and select products that must never appear in the PDF.', 'woo-pdf-catalog' )
					);
					?>
				</p>

				<!-- CSV Upload -->
				<div class="wpc-csv-upload" data-target="wpc-<?php echo esc_attr( $field_name ); ?>">
					<div class="wpc-csv-upload-row">
						<label class="wpc-csv-file-label">
							<span class="dashicons dashicons-upload"></span>
							<span class="wpc-csv-file-text"><?php esc_html_e( 'Choose CSV file…', 'woo-pdf-catalog' ); ?></span>
							<input type="file" accept=".csv,text/csv" class="wpc-csv-file-input" />
						</label>
						<button type="button" class="button wpc-csv-upload-btn" disabled>
							<span class="dashicons dashicons-database-import"></span>
							<?php esc_html_e( 'Import IDs', 'woo-pdf-catalog' ); ?>
						</button>
					</div>
					<p class="description wpc-csv-hint">
						<?php esc_html_e( 'Upload a CSV with product IDs (one per line or separated by commas). The first column is used if there are multiple.', 'woo-pdf-catalog' ); ?>
					</p>
					<div class="wpc-csv-feedback" style="display:none;"></div>
				</div>
			</td>
		</tr>
		</table>
		<?php
	}

	private function render_appearance_section( $s ) {
		$fonts = array(
			'dejavusans'  => 'DejaVu Sans (UTF-8, recommended)',
			'helvetica'   => 'Helvetica',
			'times'       => 'Times New Roman',
			'courier'     => 'Courier',
		);
		?>
		<tr>
			<th><?php esc_html_e( 'Primary Color', 'woo-pdf-catalog' ); ?></th>
			<td>
				<input type="color" name="pdf_color_primary" value="<?php echo esc_attr( $s['pdf_color_primary'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Used for headings and section titles.', 'woo-pdf-catalog' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Accent Color', 'woo-pdf-catalog' ); ?></th>
			<td>
				<input type="color" name="pdf_color_secondary" value="<?php echo esc_attr( $s['pdf_color_secondary'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Used for dividers and highlights.', 'woo-pdf-catalog' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Font Family', 'woo-pdf-catalog' ); ?></th>
			<td>
				<select name="pdf_font_family">
					<?php foreach ( $fonts as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['pdf_font_family'], $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Top Margin (mm)', 'woo-pdf-catalog' ); ?></th>
			<td><input type="number" name="pdf_margin_top" value="<?php echo esc_attr( $s['pdf_margin_top'] ); ?>" min="5" max="50" /></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Side Margins (mm)', 'woo-pdf-catalog' ); ?></th>
			<td><input type="number" name="pdf_margin_sides" value="<?php echo esc_attr( $s['pdf_margin_sides'] ); ?>" min="5" max="50" /></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Show Header', 'woo-pdf-catalog' ); ?></th>
			<td>
				<input type="checkbox" name="pdf_show_header" value="1" <?php checked( $s['pdf_show_header'] ); ?> />
				<label><?php esc_html_e( 'Show shop name in the page header', 'woo-pdf-catalog' ); ?></label>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Show Footer', 'woo-pdf-catalog' ); ?></th>
			<td>
				<input type="checkbox" name="pdf_show_footer" value="1" <?php checked( $s['pdf_show_footer'] ); ?> />
				<label><?php esc_html_e( 'Show page number in the footer', 'woo-pdf-catalog' ); ?></label>
			</td>
		</tr>
		</table>
		<?php
	}

	private function render_file_section( $s ) {
		?>
		<tr>
			<th><?php esc_html_e( 'PDF Filename', 'woo-pdf-catalog' ); ?></th>
			<td>
				<input type="text" name="pdf_filename" value="<?php echo esc_attr( $s['pdf_filename'] ); ?>" class="regular-text" />
				<span>.pdf</span>
				<p class="description"><?php esc_html_e( 'Filename without extension.', 'woo-pdf-catalog' ); ?></p>
			</td>
		</tr>
		</table>
		<?php
	}

	// ─── Download button ─────────────────────────────────────────────────────

	private function render_download_button() {
		?>
		<div class="wpc-download-section">
			<h3><?php esc_html_e( 'Download Preview', 'woo-pdf-catalog' ); ?></h3>
			<p><?php esc_html_e( 'Generate and download the PDF immediately using the current settings.', 'woo-pdf-catalog' ); ?></p>
			<a
				href="<?php echo WPC_Download_Handler::get_admin_download_url(); ?>"
				id="wpc-download-btn"
				class="button button-hero wpc-download-btn"
				target="_blank"
				rel="noopener noreferrer"
			>
				<span class="dashicons dashicons-pdf"></span>
				<?php esc_html_e( 'Download PDF Catalog', 'woo-pdf-catalog' ); ?>
			</a>
		</div>
		<?php
	}

	// ─── TCPDF notice ────────────────────────────────────────────────────────

	private function render_tcpdf_notice() {
		$tcpdf_path = WPC_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
		if ( file_exists( $tcpdf_path ) || class_exists( 'TCPDF' ) ) {
			return;
		}
		?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'TCPDF not found!', 'woo-pdf-catalog' ); ?></strong>
				<?php
				printf(
					/* translators: %s: vendor path */
					esc_html__( 'Please install TCPDF in: %s', 'woo-pdf-catalog' ),
					'<code>' . esc_html( WPC_PLUGIN_DIR . 'vendor/tcpdf/' ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
