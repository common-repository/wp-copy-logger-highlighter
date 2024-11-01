<?php
/**
 * Data Reports on Copies
 *
 * This page shows when a user selects Copy Logger from the admin menu
 * Initial features: Should be able to search/sort based on times and keywords
 *                   Should be able to turn highlights on/off for users and/or admins
 */

if( ! current_user_can( 'manage_options' ) ) {
	exit();
}

$highlight_color = "#fffa00";

if( isset( $_POST['highlight_color'] ) && wp_verify_nonce( $_POST['_wpnonce'] ) ) {
	update_option( "cplh_highlight_color", $_REQUEST['highlight_color_val'] );
	update_option( "cplh_attach_message", $_REQUEST['copied_text_attachment'] );
	if( isset( $_POST['highlight_posts'] ) ) {
		update_option( "cplh_highlight_posts", true );
	} else {
		update_option( "cplh_highlight_posts", false );
	}
	if( isset( $_POST['highlight_pages'] ) ) {
		update_option( "cplh_highlight_pages", true );
	} else {
		update_option( "cplh_highlight_pages", false );
	}
	if( isset( $_POST['highlight_admin'] ) ) {
		update_option( "cplh_highlight_admin", true );
	} else {
		update_option( "cplh_highlight_admin", false );
	}
	echo "<p><strong>Settings Saved!</strong></p>";
}

if( get_option( "cplh_highlight_color" ) !== false  ) {
	$highlight_color = get_option( "cplh_highlight_color" );
}

?>

<div class="wrap">
	<h2>Copy Logger & Highlighter</h2>
	<h3>Highlighting Options</h3>
	<form action="" method="post">
		<?php wp_nonce_field(); ?>
		<p><input name="highlight_posts" type="checkbox" <?php
			if ( get_option( "cplh_highlight_posts" ) !== false && get_option( "cplh_highlight_posts" ) ) {
				echo 'checked="checked" ';
			}
			?>id="highlight_posts" /> Highlight On Posts</p>
		<p><input name="highlight_pages" type="checkbox"  <?php
			if ( get_option( "cplh_highlight_pages" ) !== false && get_option( "cplh_highlight_pages" ) ) {
				echo 'checked="checked" ';
			}
			?>id="highlight_pages" /> Highlight On Pages</p>

		<p><input name="highlight_admin" type="checkbox"  <?php
			if ( get_option( "cplh_highlight_admin" ) !== false && get_option( "cplh_highlight_admin" ) ) {
				echo 'checked="checked" ';
			}
			?>id="highlight_pages" /> Only Admin Sees Highlight</p>
		<p>Highlight Color: <input type="text" name="highlight_color" id="highlight_color"
								   style="border: solid 3px <?php echo $highlight_color; ?>;"
								   value="<?php echo $highlight_color; ?>" />
			<input type="hidden" name="highlight_color_val" id="highlight_color_val" value="<?php echo $highlight_color; ?>" /></p>
		<p>Attach Message to Copied Text: </p>
		<p><textarea name="copied_text_attachment" rows="5" cols="70"><?php
			if ( get_option( "cplh_attach_message" ) !== false ) {
				echo get_option( "cplh_attach_message" );
			} ?></textarea></p>
		<p><button type="submit">Save Settings</button></p>
	</form>

	<h3>Copy Logs</h3>
	<p>Filter Logs: <!-- <select id="cp_filter_sort">
			<option value="0">Sort by Newest</option>
			<option value="1">Sort By Popular</option>
		</select> //-->

		Post/Page ID: <input size="5" type="text" id="cp_filter_post" /> <input type="hidden" id="cp_filter_post_id" />
	</p>
	<p><button class="load_copy_logs">Load Logs</button></p>
	<ul id="display_logs"></ul>
</div>
