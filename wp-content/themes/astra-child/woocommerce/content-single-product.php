<?php
/**
 * Single product template override for Geeta books.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( post_password_required() ) {
	echo get_the_password_form();
	return;
}
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'gb-single-product', $product ); ?>>

	<div class="gb-single-grid">
		<div class="gb-single-gallery">
			<?php
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<div class="gb-single-summary gb-single-sticky">
			<?php
			do_action( 'woocommerce_single_product_summary' );
			?>
		</div>
	</div>

	<div class="gb-single-tabs">
		<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_single_product' ); ?>
</div>
