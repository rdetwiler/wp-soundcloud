<?php
/*
  Plugin Name: SoundCloud Manager
  Plugin URI: http://www.45press.com
  Description: Provides SoundCloud dashboard functionality in WordPress.
  Version: 1.0
  Author: James Fortunato
  Author URI: http://www.45press.com
  License: GPL2
 */
 
 define ('SC_PLUGIN_DIR', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) );
// ------------------------------------------------------------------------
// REQUIRE MINIMUM VERSION OF WORDPRESS:                                               
// ------------------------------------------------------------------------
function requires_wordpress_version() {
	global $wp_version;
	$plugin = plugin_basename( __FILE__ );
	$plugin_data = get_plugin_data( __FILE__, false );

	if ( version_compare($wp_version, "3.3", "<" ) ) {
		if( is_plugin_active($plugin) ) {
			deactivate_plugins( $plugin );
			wp_die( "'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress admin</a>." );
		}
	}
}
add_action( 'admin_init', 'requires_wordpress_version' );

// Define icon styles for the custom post type
function sc_icons() {
    ?>
    <style type="text/css" media="screen">
		#icon-sc {background: url(<?php echo SC_PLUGIN_DIR; ?>/includes/images/soundcloud_32.png) no-repeat;}
    </style>
    <?php
}
add_action('admin_head', 'sc_icons');

// add js script to admin
function sc_admin_script() {
    wp_enqueue_script('jquery');
	wp_enqueue_script('soundcloud-js', plugins_url('/includes/js/soundcloud.js', __FILE__));
}
add_action('admin_init', 'sc_admin_script');

// add css style to admin
function sc_admin_style() {
    wp_enqueue_style('sc-admin', plugins_url('/includes/css/soundcloud.css', __FILE__));
}
add_action('admin_init', 'sc_admin_style');

// Set-up Action and Filter Hooks
register_activation_hook(__FILE__, 'sc_add_defaults');
register_uninstall_hook(__FILE__, 'sc_delete_plugin_options');
add_action('admin_init', 'sc_init' );
add_action('admin_menu', 'sc_add_options_page');

// Delete options table entries ONLY when plugin deactivated AND deleted
function sc_delete_plugin_options() {
	delete_option('sc_options');
}

// Define default option settings
function sc_add_defaults() {
	$tmp = get_option('sc_options');
    if(($tmp['chk_default_options_db']=='1')||(!is_array($tmp))) {
		delete_option('sc_options'); // so we don't have to reset all the 'off' checkboxes too! (don't think this is needed but leave for now)
		$arr = array(	"client_id" => "Your client ID goes here",
						"client_secret" => "Your client secret goes here",
						"callback_url" => "Your callback url goes here",
		);
		update_option('sc_options', $arr);
	}
}

// Init plugin options to white list our options
function sc_init(){
	register_setting( 'sc_plugin_options', 'sc_options', 'sc_validate_options' );
}

// Add menu pages
function sc_add_options_page() {
	add_menu_page('SoundCloud', 'SoundCloud', 'manage_options', 'soundcloud-manager', 'sc_render_manager_page', SC_PLUGIN_DIR.'includes/images/soundcloud-icon.png');
	add_submenu_page(null, 'Disconnect from Soundcloud', 'Disconnect from Soundcloud', 'manage_options', 'soundcloud-disconnect', 'sc_disconnect');
	add_options_page('Soundcloud Settings Page', 'Soundcloud App Settings', 'manage_options', __FILE__, 'sc_render_form');
}

// Render the Plugin options form
function sc_render_form() {
	?>
	<div class="wrap">
		
		<!-- Display Plugin Icon, Header, and Description -->
		<div class="icon32" id="icon-options-general"><br></div>
		<h2>Soundcloud Test Settings Page</h2>
		<p>Please enter the details from your SoundCloud application page. You can create a new SoundCloud application <a href="http://soundcloud.com/you/apps" target="_blank">HERE</a>.
		</br></br><strong>Your Redirect URI for Authentication is: </strong><?php echo site_url();?>/wp-admin/admin.php?page=soundcloud-manager</p>
		<strong>Please copy and paste the Redirect URI for Authentication into your SoundCloud app, as well as the form below.</strong>

		<!-- Beginning of the Plugin Options Form -->
		<form method="post" action="options.php">
			<?php settings_fields('sc_plugin_options'); ?>
			<?php $options = get_option('sc_options'); ?>

			<table class="form-table">
				<tr>
					<th scope="row">Client ID</th>
					<td>
						<input type="text" size="57" name="sc_options[client_id]" value="<?php echo $options['client_id']; ?>" />
					</td>
				</tr>				
				<tr>
					<th scope="row">Client Secret</th>
					<td>
						<input type="text" size="57" name="sc_options[client_secret]" value="<?php echo $options['client_secret']; ?>" />
					</td>
				</tr>				
				<tr>
					<th scope="row">Redirect URI for Authentication</th>
					<td>
						<input type="text" size="57" name="sc_options[callback_url]" value="<?php echo $options['callback_url']; ?>" />
					</td>
				</tr>				
			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?php	
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function sc_validate_options($input) {
	 // Sanitize textbox input (strip html tags, and escape characters)
	$input['client_id'] =  wp_filter_nohtml_kses($input['client_id']); 
	$input['client_secret'] =  wp_filter_nohtml_kses($input['client_secret']);
	$input['callback_url'] =  wp_filter_nohtml_kses($input['callback_url']); 
	return $input;
}

// SoundCloud manager page
function sc_render_manager_page() {
	require_once plugin_dir_path(__FILE__) . 'includes/Services/Soundcloud.php';
	require_once plugin_dir_path(__FILE__) . 'includes/class/pagination.class.php';	
	
	$options = get_option('sc_options');
	$client = new Services_Soundcloud($options['client_id'], $options['client_secret'], $options['callback_url']);
	
	// Error message if app settings are not filled in.
	if (($options['client_id'] == "Your client ID goes here") || ($options['client_secret'] == "Your client secret goes here") || ($options['callback_url'] == "Your callback url goes here"))
	{
		echo '<div class="error fade"><p>Warning: One or more of the required fields have not been set. Please insure that your cliend ID, client secret, and callback url are defined <a href="'.admin_url('options-general.php?page=soundcloud/soundcloud.php').'">HERE</a>.</br>Soundcloud functionality will not be available until these fields are set.</p></div></br>';	
	}
	
	// SoundCloud login/logout
	if (isset($_GET['code']) || get_option('soundcloud_access_token')){
		echo '<a href="'.admin_url('admin.php?page=soundcloud-disconnect').'" class="soundcloud-icon"><img src="'.plugins_url().'/soundcloud/includes/images/btn-disconnect-l.png" alt="Disconnect"/></a>';	
		try {
			$client = new Services_Soundcloud($options['client_id'], $options['client_secret'], $options['callback_url']);	
			if (isset($_GET['code'])) {
				$accessToken = $client->accessToken($_GET['code']);
				update_option('soundcloud_access_token', $accessToken['access_token']);
				update_option('soundcloud_refresh_token', $accessToken['refresh_token']);
				update_option('soundcloud_token_expiration', time() + $accessToken['expires_in']);
			}
			else if(get_option('soundcloud_access_token')) {
			// refresh token code deprecated due to non-expiring tokens
				/*if (time() > get_option('soundcloud_token_expiration')) {
					$client->setAccessToken(get_option('soundcloud_access_token'));
					
					// refresh access token
					$accessToken = $client->accessTokenRefresh(get_option('soundcloud_refresh_token'));	
					//$client->setAccessToken($accessToken['access_token']);							
					update_option('soundcloud_access_token', $accessToken['access_token']);
					update_option('soundcloud_refresh_token', $accessToken['refresh_token']);
					update_option('soundcloud_token_expiration', time() + $accessToken['expires_in']);
				}
				else
				{*/
					$client->setAccessToken(get_option('soundcloud_access_token'));
				//}				
			}			
		} 
		catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
			var_dump($e->getMessage());
			exit();
		}
	}
	else {
		echo '<a href="'.$client->getAuthorizeUrl(array('scope'=>'non-expiring')).'" class="soundcloud-icon"><img src="'.plugins_url().'/soundcloud/includes/images/btn-connect-sc-l.png" alt="Connect with Soundcloud"/></a>';	
	}	
	
	// tab switching
	if (isset ($_GET['tab'])) {
		sc_admin_tabs($_GET['tab']); 
		$tab = $_GET['tab'];
	}		
	else {
		sc_admin_tabs('user');
		$tab = 'user';
	}
	if (get_option('soundcloud_access_token')) {
		try {
			$me = json_decode($client->get('me'), true);
		}
		catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
			var_dump($e->getMessage());
			exit();			
		}		
		echo '<h3>Logged in as: ' . $me['username'] . '</h3>';	
	}
	else {
		echo '<h3>Not logged in.</h3>';	
	}

	switch ($tab){
		case 'user' :
			try {
				$me = json_decode($client->get('me'), true);
				?>
				<form action="" method="post">
					<table class="form-table">
						<tr valign="top">
						  <th scope="row"><label for="username">Username:</label></th>
						  <td><input type="text" name="username" value="<?php echo $me['username']; ?>" size="30" class="regular-text code"></td>
						</tr>
						<tr valign="top">
						  <th scope="row"><label for="permalink">Permalink:</label></th>
						  <td><input type="text" name="permalink" value="<?php echo $me['permalink']; ?>" size="30" class="regular-text code"></td>
						</tr>
						<tr valign="top">
						  <th scope="row"><label for="description">Description:</label></th>
						  <td><input type="text" name="description" value="<?php echo $me['description']; ?>" size="30" class="regular-text code"></td>
						</tr>
						<tr valign="top">
						  <th scope="row"><label for="website">Website:</label></th>
						  <td><input type="text" name="website" value="<?php echo $me['website']; ?>" size="30" class="regular-text code"></td>
						</tr>
						<tr valign="top">
						  <th scope="row"><label for="website_title">Website Name:</label></th>
						  <td><input type="text" name="website_title" value="<?php echo $me['website_title']; ?>" size="30" class="regular-text code"></td>
						</tr>
					</table>
					<p class="submit"><input type="submit" value="Update Account" class="button-primary"></p>
					
				</form>
				<?php
				if (isset($_POST['username']) || isset($_POST['permalink']) || isset($_POST['description']) || isset($_POST['website']) || isset($_POST['website_title'])) {
					$response = json_decode($client->put('me', array(
						'user[username]' => $_POST['username'],
						'user[permalink]' => $_POST['permalink'],
						'user[description]' => $_POST['description'],
						'user[website]' => (strlen($_POST['website'])) ? $_POST['website'] : null,
						'user[website_title]' => (strlen($_POST['website_title'])) ? $_POST['website_title'] : null						
					)));								
				}
			}
			catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
				var_dump($e->getMessage());
				exit();			
			}		
		break;
		
		case 'upload' :
			?>	
			<form action="" enctype="multipart/form-data" method="post" id ="add_track">
				<table class="form-table">
					<tr valign="top">
					  <th scope="row"><label for="track_title2">Track title:</label></th>
					  <td><input type="text" name="track_title2" size="30" class="regular-text code"></td>
					</tr>
					<tr valign="top">
					  <th scope="row"><label for="track_file2">Please specify a track:</label></th>
					  <td><input type="file" name="track_file2" id="track_file2" size="40" accept="audio/*" class="regular-text code"></td>
					</tr>
					<tr valign="top">
					  <th scope="row"><label for="track_art2">Please specify track artwork:</label></th>
					  <td><input type="file" name="track_art2" id="track_art2" size="40" accept="image/*" class="regular-text code"></td>
					</tr>
					<tr valign="top">
					  <th scope="row"><label for="tag">Track tags:</label></th>
					  <td><input type="text" name="tag" id="tag" size="30"><input type="button" id="add_tag" name="add_tag" class="button" value="Add Tag" />
						<br><ul id="tags"></ul></td>
					</tr>
					<tr valign="top">
					  <th scope="row"><label for="website_title">Track privacy:</label></th>
					  <td><select name="sharing2">
						<option value="public">Public</option>
						<option value="private">Private</option>
					  </select></td>
					</tr>
					</table>
			<p class="submit"><input type="submit" value="Upload" class="button-primary"></p>
			</form>					
			<?php
			try {
				$tmp_file = '/tmp/' . stripslashes($_FILES['track_file']['name']);
				$tmp_art_file = '/tmp/' . stripslashes($_FILES['track_art']['name']);
				if (move_uploaded_file($_FILES['track_file']['tmp_name'], $tmp_file) && move_uploaded_file($_FILES['track_art']['tmp_name'], $tmp_art_file)) {
					// upload audio file
					$track = json_decode($client->post('tracks', array(
						'track[title]' => $_POST['track_title'],
						'track[asset_data]' => '@' . $tmp_file,
						'track[artwork_data]' => '@' . $tmp_art_file,
						'track[tags]' => (strlen($_POST['tags'])) ? $_POST['tags']: null,
						'track[sharing]' => $_POST['sharing']
					)));
					unlink(realpath($tmp_file));
					unlink(realpath($tmp_art_file));
				}	
			}
			catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
				var_dump($e->getMessage());
				exit();			
			}
		break;		
		
		case 'tracks' :
			try {
				echo '</br>';

				$page_size = 5;
				
				// Pagination code
				$p = new pagination;
				$p->items($me['track_count']);
				$p->limit($page_size); // Limit entries per page
				$p->target("admin.php?page=soundcloud-manager&tab=tracks"); 
				$p->currentPage($_GET[$p->paging]); // Gets and validates the current page
				$p->calculate(); // Calculates what to show
				$p->parameterName('paging');
				$p->adjacents(1); //No. of page away from the current page 
		
				if(!isset($_GET['paging'])) {
					$p->page = 1;
				} else {
					$p->page = $_GET['paging'];
				}				
				if ($p->page == 1) {
					// get first page of tracks
					$tracks = json_decode($client->get('users/'. $me['id'] .'/tracks', array(
						'order' => 'created_at',
						'limit' => $page_size)
					));
				}		
				else {
					// get additional pages of tracks 	
					$tracks = json_decode($client->get('users/'. $me['id'] .'/tracks', array(
						'order' => 'created_at',
						'limit' => $page_size,
						'offset' => ($page_size * $page)						
					)));
				}
				?>
				<div class="tablenav">
					<div class='tablenav-pages'>
						<?php echo $p->show();?>
					</div>
				</div>
				<?php
				foreach ($tracks as $track) {
					$client->setCurlOptions(array(CURLOPT_FOLLOWLOCATION => 1));
					$embed_info = json_decode($client->get('oembed', array('url' => $track->permalink_url)));

					// render the html for the player widget
					echo $embed_info->html . '</br></br>';
				}

			}
			catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
				var_dump($e->getMessage());
				exit();			
			}		
		break;			
	}
}

function sc_disconnect() {
	delete_option('soundcloud_access_token');
	delete_option('soundcloud_refresh_token');
	delete_option('soundcloud_token_expiration');
	wp_redirect(admin_url('admin.php?page=soundcloud-manager'));
}


function sc_admin_tabs( $current = 'user' ) {
    $tabs = array( 'user' => 'User Information', 'upload' => 'Upload Track', 'tracks' => 'View Tracks' );
    echo '<div id="icon-sc" class="icon32"><br></div>';
    echo '<h2 class="nav-tab-wrapper">';

    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=soundcloud-manager&tab=$tab'>$name</a>";
    }
    echo '</h2>';
}

?>