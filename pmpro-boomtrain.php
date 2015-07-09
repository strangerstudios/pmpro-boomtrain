<?php
/**
 * Plugin Name: PMPro Boomtrain
 * Description: Integrate Paid Memberships Pro with Boomtrain personalized marketing campaigns.
 * Author: Stranger Studios
 * Author URI: http://strangerstudios.com
 * Version: .1
 */

//init
function pmprobt_init() {
	//include PMPro_Boomtrain if we don't have it already
	if(!class_exists("PMPro_Boomtrain"))
		require_once(dirname(__FILE__) . "/includes/PMPro_Boomtrain.php");
    else
        return false;
}
add_action( "init", "pmprobt_init", 0 );

//add tracking code to footer
function pmprobt_wp_footer() {
	$options = get_option( 'pmprobt_options' );

	if ( empty( $options ) || empty( $options['tracking_code'] ) ) {
		return false;
	}

	//add tracking code
	echo $options['tracking_code'];

    //if user is logged in, identify them
    if ( is_user_logged_in() ) {
        global $current_user; ?>
        <script>
            _bt.identify('<?php echo $current_user->user_id; ?>');
            _bt.person.set({
                'email': '<?php echo $current_user->user_email; ?>',
                'firstName': '<?php echo $current_user->first_name; ?>',
                'lastName': '<?php echo $current_user->last_name; ?>'
            });
            <?php if(function_exists('pmpro_hasMembershipLevel')) {
                    if ( pmpro_hasMembershipLevel() ) {
                        ?>
                        _bt.person.set({'membership_level': '<?php echo $current_user->membership_level->id; ?>'});
                        <?php
                    }
                } ?>
                    </script>
                <?php
    }
}
add_action('wp_footer', 'pmprobt_wp_footer');

//update person on register or level change
function pmprobt_person_update($user_id, $level_id = null) {

    global $flag;

    //switch if a 2nd param was passed (pmpro_after_change_membership_level)
    if(!empty($level_id))
        $user_id = $level_id;

    //get user
    $user = get_userdata($user_id);
    $level = pmpro_getMembershipLevelForUser($user_id);

    //setup some fields
    $fields = array(
        'firstName' => $user->first_name,
        'lastName' => $user->last_name,
        'email' => $user->user_email
    );

    if(!empty($level))
        $fields['membership_level'] = $level->id;

    $api = new PMPro_Boomtrain();

    //does person already exist?
    $person = $api->getPerson($user->user_email);

    if(empty($person->appMemberId)) {
        $fields['bt_signup'] = true;
        $fields['bt_signup_timestamp'] = current_time('timestamp');
    }

    //update person
    $api->updatePerson($user_id, $fields);

    if(empty($person->appMemberId))
        $api->trackEvent('bt_signup', $user->user_email);

    if($flag)
        die();

    $flag = 1;
}
add_action('user_register', 'pmprobt_person_update');
add_action('pmpro_after_change_membership_level', 'pmprobt_person_update', 10, 2);

//change email in Boomtrain if a user's email is changed in WordPress
function pmprobt_profile_update( $user_id, $old_user_data ) {
	$new_user_data = get_userdata( $user_id );
	if ( $new_user_data->user_email != $old_user_data->user_email )
        pmprobt_person_update($user_id);
}
add_action( "profile_update", "pmprobt_profile_update", 10, 2 );

//admin init. registers settings
function pmprobt_admin_init() {
	//setup settings
	register_setting( 'pmprobt_options', 'pmprobt_options' );
	add_settings_section( 'pmprobt_section_general', '', 'pmprobt_section_general', 'pmprobt_options' );
	add_settings_field( 'pmprobt_option_tracking_code', 'Boomtrain Tracking Code', 'pmprobt_option_tracking_code', 'pmprobt_options', 'pmprobt_section_general' );
	add_settings_field( 'pmprobt_option_username', 'Boomtrain Username', 'pmprobt_option_username', 'pmprobt_options', 'pmprobt_section_general' );
	add_settings_field( 'pmprobt_option_password', 'Boomtrain Password', 'pmprobt_option_password', 'pmprobt_options', 'pmprobt_section_general' );
}

add_action( "admin_init", "pmprobt_admin_init" );

//options sections
function pmprobt_section_general() {
}

//options code
function pmprobt_option_tracking_code() {
	$options = get_option( 'pmprobt_options' );

	if ( isset( $options['tracking_code'] ) ) {
		$tracking_code = $options['tracking_code'];
	} else {
		$tracking_code = "";
	}
	echo "<textarea id='pmprobt_tracking_code' name='pmprobt_options[tracking_code]' rows=1 cols=120>" . $tracking_code . "</textarea>";
}

function pmprobt_option_username() {
	$options = get_option( 'pmprobt_options' );

	if ( isset( $options['username'] ) ) {
		$username = $options['username'];
	} else {
		$username = "";
	}
	echo "<input id='pmprobt_username' name='pmprobt_options[username]' type='text' value='" . $username . "' />";
}

function pmprobt_option_password() {
    $options = get_option( 'pmprobt_options' );

    if ( isset( $options['password'] ) ) {
        $password = $options['password'];
    } else {
        $password = "";
    }
    echo "<input id='pmprobt_password' name='pmprobt_options[password]' type='text' value='" . $password . "' />";
}


// add the admin options page	
function pmprobt_admin_add_page() {
	add_options_page( 'PMPro Boomtrain Options', 'PMPro Boomtrain', 'manage_options', 'pmprobt_options', 'pmprobt_options_page' );
}

add_action( 'admin_menu', 'pmprobt_admin_add_page' );

//html for options page
function pmprobt_options_page() {
	global $pmpro_msg, $pmpro_msgt;

	//get options
	$options = get_option( "pmprobt_options" );

	//defaults
	if ( empty( $options ) ) {
		$options = array(
			'tracking_code' => '',
			'api_key'       => ''
		);
		update_option( "pmprobt_options", $options );
	}

	//check for tracking code
	if ( ! empty( $options['tracking_code'] ) ) {
		$tracking_code = $options['tracking_code'];
	}

	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2><?php _e( 'PMPro Boomtrain Integration Options', 'pmprobt' ); ?></h2>

		<?php if ( ! empty( $pmpro_msg ) ) { ?>
			<div class="message <?php echo $pmpro_msgt; ?>"><p><?php echo $pmpro_msg; ?></p></div>
		<?php } ?>

		<form action="options.php" method="post">
			<p><?php _e( 'This plugin will integrate your site with Boomtrain. Add your Boomtrain tracking code below to begin tracking your users in Boomtrain.', 'pmprobt' ); ?></p>

			<p><?php _e( 'If you have <a href="http://www.paidmembershipspro.com">Paid Memberships Pro</a> installed, your users\' membership levels will also be recorded', 'pmprobt' ); ?></p>

			<p><?php _e( 'Don\'t have a Boomtrain account? <a href="http://boomtrain.com/" target="_blank">Get one here</a>.', 'pmprobt' ); ?></p>
			<?php settings_fields( 'pmprobt_options' ); ?>
			<?php do_settings_sections( 'pmprobt_options' ); ?>
			<div class="bottom-buttons">
				<input type="hidden" name="pmprot_options[set]" value="1"/>
				<input type="submit" name="submit" class="button-primary"
				       value="<?php esc_attr_e( 'Save Settings' ); ?>">
			</div>

		</form>
	</div>
<?php
}

/*
	Defaults on Activation
*/
function pmprobt_activation() {
	//get options
	$options = get_option( "pmprobt_options" );

	//defaults
	if ( empty( $options ) ) {
		$options = array(
			"api_key"         => "",
			"activation_code" => "",
		);
		update_option( "pmprobt_options", $options );
	}
}
register_activation_hook( __FILE__, "pmprobt_activation" );

/*
Function to add links to the plugin action links
*/
function pmprobt_add_action_links( $links ) {

	$new_links = array(
		'<a href="' . get_admin_url( null, 'options-general.php?page=pmprobt_options' ) . '">Settings</a>',
	);

	return array_merge( $new_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pmprobt_add_action_links' );

/*
Function to add links to the plugin row meta
*/
function pmprobt_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-Boomtrain.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'http://www.paidmembershipspro.com/add-ons/third-party-integration/pmpro-Boomtrain-integration/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url( 'http://paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}

	return $links;
}

add_filter( 'plugin_row_meta', 'pmprobt_plugin_row_meta', 10, 2 );