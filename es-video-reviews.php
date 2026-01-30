<?php
/**
 * Plugin Name: ES Video Reviews
 * Description: Display a review form with 4-star rating and review text. Use shortcode [video_review_form] to place the form. Saves to the video_reviews custom post type.
 * Version: 1.0.0
 * Author: ES Video Reviews
 * Text Domain: es-video-reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ES_VIDEO_REVIEWS_VERSION', '1.1.0' );
define( 'ES_VIDEO_REVIEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ES_VIDEO_REVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue plugin styles and scripts.
 */
function es_video_reviews_enqueue_assets() {
	wp_enqueue_style(
		'es-video-reviews',
		ES_VIDEO_REVIEWS_PLUGIN_URL . 'assets/css/style.css',
		array(),
		ES_VIDEO_REVIEWS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'es_video_reviews_enqueue_assets' );

/**
 * Handle form submission: save review to video_reviews CPT.
 */
function es_video_reviews_handle_submit() {
	if ( ! isset( $_POST['es_video_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['es_video_review_nonce'] ) ), 'es_video_review_submit' ) ) {
		return;
	}
	global $post;
	$rating = isset( $_POST['es_video_review_rating'] ) ? absint( $_POST['es_video_review_rating'] ) : 0;
	$review_text = isset( $_POST['es_video_review_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['es_video_review_text'] ) ) : '';
	$video_id = isset( $_POST['es_video_review_video_id'] ) ? absint( $_POST['es_video_review_video_id'] ) : $post->ID;
	if ( $video_id < 1 ) {
		$video_id = $post->ID;
	}

	// Validate: rating must be 1–4
	if ( $rating < 1 || $rating > 4 ) {
		return;
	}

	$post_data = array(
		'post_type'   => 'video_reviews',
		'post_title'  => sprintf(
			/* translators: 1: date, 2: time */
			__( 'Review – %1$s at %2$s', 'es-video-reviews' ),
			wp_date( get_option( 'date_format' ) ),
			wp_date( get_option( 'time_format' ) )
		),
		'post_content' => $review_text,
		'post_status'  => 'publish',
		'post_author'  => get_current_user_id() ? get_current_user_id() : 0,
	);

	$post_id = wp_insert_post( $post_data );

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_video_review_rating', $rating );
		if ( $video_id > 0 ) {
			update_post_meta( $post_id, '_video_review_video_id', $video_id );
		}
		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$redirect_url = remove_query_arg( 'review_submitted' );
		}
		wp_safe_redirect( add_query_arg( 'review_submitted', '1', $redirect_url ) );
		exit;
	}
}
add_action( 'template_redirect', 'es_video_reviews_handle_submit', 5 );

/**
 * Shortcode: [video_review_form] or [video_review_form video_id="123"]
 * Renders the review form with 4-star rating and review text field.
 * Use video_id to associate the review with a specific video (post ID).
 *
 * @param array $atts Shortcode attributes. Optional: 'video_id' => post ID of the video.
 * @return string HTML output.
 */
function es_video_reviews_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'video_id' => 0,
		),
		$atts,
		'video_review_form'
	);
	$video_id = absint( $atts['video_id'] );

	ob_start();

	$show_success = isset( $_GET['review_submitted'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['review_submitted'] ) );

	if ( $show_success ) {
		?>
		<div class="es-video-review-message es-video-review-success">
			<?php esc_html_e( 'Thank you! Your review has been submitted.', 'es-video-reviews' ); ?>
		</div>
		<?php
	}

	?>
	<form class="es-video-review-form" method="post" action="">
		<?php wp_nonce_field( 'es_video_review_submit', 'es_video_review_nonce' ); ?>
		<?php if ( $video_id > 0 ) : ?>
			<input type="hidden" name="es_video_review_video_id" value="<?php echo esc_attr( $video_id ); ?>" />
		<?php endif; ?>

		<div class="es-video-review-field es-video-review-stars">
			<label for="es-video-review-rating"><?php esc_html_e( 'Rating', 'es-video-reviews' ); ?></label>
			<div class="es-video-review-stars-input" role="group" aria-label="<?php esc_attr_e( 'Select 1 to 4 stars', 'es-video-reviews' ); ?>">
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<label class="es-video-review-star-label">
						<input type="radio" name="es_video_review_rating" value="<?php echo absint( $i ); ?>" required class="es-video-review-star-radio" />
						<span class="es-video-review-star" aria-hidden="true">★</span>
					</label>
				<?php endfor; ?>
			</div>
		</div>

		<div class="es-video-review-field">
			<label for="es-video-review-text"><?php esc_html_e( 'Your review', 'es-video-reviews' ); ?></label>
			<textarea id="es-video-review-text" name="es_video_review_text" class="es-video-review-text" rows="5" required placeholder="<?php esc_attr_e( 'Write your review here...', 'es-video-reviews' ); ?>"></textarea>
		</div>

		<div class="es-video-review-field es-video-review-submit">
			<button type="submit" class="es-video-review-submit-btn"><?php esc_html_e( 'Submit review', 'es-video-reviews' ); ?></button>
		</div>
	</form>
	<?php

	return ob_get_clean();
}
add_shortcode( 'video_review_form', 'es_video_reviews_form_shortcode' );

/**
 * Add "Rating" column to video_reviews list table in admin.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function es_video_reviews_add_rating_column( $columns ) {
	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;
		if ( 'title' === $key ) {
			$new_columns['video_review_rating']   = __( 'Rating', 'es-video-reviews' );
			$new_columns['video_review_video']   = __( 'Video', 'es-video-reviews' );
		}
	}

	// If there was no 'title' key, append columns.
	if ( ! isset( $new_columns['video_review_rating'] ) ) {
		$new_columns['video_review_rating'] = __( 'Rating', 'es-video-reviews' );
	}
	if ( ! isset( $new_columns['video_review_video'] ) ) {
		$new_columns['video_review_video'] = __( 'Video', 'es-video-reviews' );
	}

	return $new_columns;
}
add_filter( 'manage_video_reviews_posts_columns', 'es_video_reviews_add_rating_column' );

/**
 * Output star rating in the custom column.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function es_video_reviews_rating_column_content( $column, $post_id ) {
	if ( 'video_review_rating' !== $column ) {
		return;
	}

	$rating = get_post_meta( $post_id, '_video_review_rating', true );
	$rating = absint( $rating );

	if ( $rating < 1 || $rating > 4 ) {
		echo '—';
		return;
	}

	$stars = str_repeat( '★', $rating ) . str_repeat( '☆', 4 - $rating );
	printf(
		'<span class="es-video-review-admin-stars" title="%1$s">%2$s</span>',
		esc_attr( sprintf( /* translators: 1: number of stars, 2: max stars */ __( '%1$d out of %2$d stars', 'es-video-reviews' ), $rating, 4 ) ),
		esc_html( $stars )
	);
}
	add_action( 'manage_video_reviews_posts_custom_column', 'es_video_reviews_rating_column_content', 10, 2 );

/**
 * Output video name in the custom column.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function es_video_reviews_video_column_content( $column, $post_id ) {
	if ( 'video_review_video' !== $column ) {
		return;
	}

	$video_id = get_post_meta( $post_id, '_video_review_video_id', true );
	$video_id = absint( $video_id );

	if ( $video_id < 1 ) {
		echo '—';
		return;
	}

	$video = get_post( $video_id );
	if ( ! $video ) {
		echo '—';
		return;
	}

	$title = get_the_title( $video_id );
	if ( current_user_can( 'edit_post', $video_id ) ) {
		$edit_url = get_edit_post_link( $video_id, 'raw' );
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $edit_url ),
			esc_html( $title ? $title : __( '(no title)', 'es-video-reviews' ) )
		);
	} else {
		echo esc_html( $title ? $title : __( '(no title)', 'es-video-reviews' ) );
	}
}
add_action( 'manage_video_reviews_posts_custom_column', 'es_video_reviews_video_column_content', 10, 2 );

/**
 * Output admin styles for the rating column (only on video_reviews list screen).
 */
function es_video_reviews_admin_styles() {
	$screen = get_current_screen();
	if ( ! $screen || 'video_reviews' !== $screen->post_type || 'edit' !== $screen->base ) {
		return;
	}

	echo '<style>.es-video-review-admin-stars{font-size:1.1em;letter-spacing:0.05em;color:#dba617}</style>';
}
add_action( 'admin_head', 'es_video_reviews_admin_styles' );
