<?php
/*
Plugin Name: Superflow: Annotate live websites
Plugin URI:   https://wordpress.org/plugins/superflow/
Description: Collect visual website feedback from colleagues and clients directly in your WordPress site.
Version:     1.0.0
Requires at least: 3.1.0
Tested up to: 6.4.2
Stable tag: 1.0.0
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/superflow-config.php')) {
  include('superflow-config.php');
}

function superflow_settings_link($links)
{
  $url = esc_url(add_query_arg(
    'page',
    'superflow-plugin',
    get_admin_url() . 'options-general.php?page=superflow-plugin'
  ));

  $settings_link = "<a href='$url'>" . __('Settings') . '</a>';

  array_push(
    $links,
    $settings_link
  );

  return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'superflow_settings_link');

function superflow_enqueue_admin_script($hook)
{
  wp_register_style('superflow_icons', plugin_dir_url(__FILE__) . 'superflow-icons.css');
  wp_enqueue_style('superflow_icons');

  if ('settings_page_superflow-plugin' != $hook && 'toplevel_page_superflow-plugin' != $hook ) {
    return;
  }

  wp_enqueue_style('superflow_style', plugin_dir_url(__FILE__) . 'dist/styles.css', [], SUPERFLOW_VERSION);
  wp_enqueue_script('superflow_script', plugin_dir_url(__FILE__) . 'dist/scripts.js', [], SUPERFLOW_VERSION);
}
add_action('admin_enqueue_scripts', 'superflow_enqueue_admin_script');

function superflow_check_user_role($roles, $user_id = null)
{
  if ($user_id) $user = get_userdata($user_id);
  else $user = wp_get_current_user();
  if (empty($user)) return false;
  if (empty($roles)) return false;
  foreach ($user->roles as $role) {
    if (in_array($role, $roles)) {
      return true;
    }
  }
  return false;
}

function superflow_insert_code_snippet()
{
  $display_button = true;
  $reason = '';

  $options = get_option('superflow_plugin_options');

  // General
  $api_key = $options['superflow_api_key'];
  $project_id = $options['superflow_project_id'];
  $connection_status = $options['superflow_connection_status'];
  $enable_widget = $options['superflow_enable_widget'] === 'false' ? false : true;

  if (is_admin()) {
    $enable_admin = $options['superflow_enable_admin'] === false || $options['superflow_enable_admin'] === 'false' ? false : true;

    if (!$enable_admin) {
      $display_button = false;
      $reason = 'Disabled in admin';
    } elseif (!$api_key) {
      $display_button = false;
      $reason = 'Missing API Key';
    } elseif (!$project_id) {
      $display_button = false;
      $reason = 'Missing Project ID';
    } elseif (!$enable_widget) {
      $display_button = false;
      $reason = 'Widget disabled';
    }
  } else {
    if (!$api_key) {
      $display_button = false;
      $reason = 'Missing API Key';
    } elseif (!$project_id) {
      $display_button = false;
      $reason = 'Missing Project ID';
    } elseif (!$enable_widget) {
      $display_button = false;
      $reason = 'Widget disabled';
    }
  }

  if ($display_button) {
    echo "
    <script>(function (window) {
    console.log('Loading superflow plugin...');
    const script = document.createElement('script');
    script.id = 'superflowToolbarScript';
    script.setAttribute('data-sf-platform', 'wordpress-plugin-auto');
    script.async = true;
    script.src = '" . esc_url(SUPERFLOW_TOOLBAR_URL) . "?apiKey=" . esc_js($api_key) . "&projectId=" . esc_js($project_id) . "';
    document.head.appendChild(script);
  })(window);</script>";
  }
}
add_action('wp_head', 'superflow_insert_code_snippet');
add_action('admin_head', 'superflow_insert_code_snippet');

function superflow_add_menu_page()
{
  if (current_user_can('manage_options')) {
    add_options_page('Superflow settings', 'Superflow', 'manage_options', 'superflow-plugin', 'superflow_render_plugin_settings_page');
  } else {
    add_menu_page('Superflow settings', 'Superflow', 'read', 'superflow-plugin', 'superflow_render_plugin_settings_page', 'dashicons-image-filter', 99);
  }
}
add_action('admin_menu', 'superflow_add_menu_page');

// Activation hook
function superflow_activation()
{
  do_action('superflow_default_options');
}
register_activation_hook(__FILE__, 'superflow_activation');


// Set default options
function superflow_default_values()
{
  // Form settings
  $options = array(
    'superflow_api_key' => '',
    'superflow_project_id' => '',
    'superflow_connection_id' => '',
    'superflow_server_doc_id' => '',
    'superflow_connection_status' => 'pending',
    "superflow_connection_email" => get_option('admin_email'),
    'superflow_flow_type' => 'auto',
    'superflow_enable_widget' => 1,
    'superflow_enable_admin' => false,
  );
  add_option('superflow_plugin_options', $options);
}
add_action('superflow_default_options', 'superflow_default_values');

function superflow_render_plugin_settings_page()
{
  $plugin_options = get_option('superflow_plugin_options');
  $site_url = get_site_url();
  $can_manage_options = current_user_can('manage_options');
  $nonce = wp_create_nonce('superflow_nonce');

  unset($available_post_types['custom_css']);
  unset($available_post_types['customize_changeset']);
  unset($available_post_types['nav_menu_item']);
  unset($available_post_types['oembed_cache']);
  unset($available_post_types['revision']);
  unset($available_post_types['user_request']);
  unset($available_post_types['wp_block']);

  $ajaxUrl = admin_url('admin-ajax.php');

  echo '

  <div class="superflow-container">
    <div class="superflow-logo">
      <svg width="206" height="48" viewBox="0 0 206 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M6.39844 11.1998C6.39844 7.66518 9.26382 4.7998 12.7984 4.7998C16.3331 4.7998 19.1984 7.66518 19.1984 11.1998V17.5998H12.7984C9.26382 17.5998 6.39844 14.7344 6.39844 11.1998Z" fill="#FFCD2E"/>
        <path d="M0 28.0004C0 22.6985 4.29807 18.4004 9.6 18.4004H19.2V28.0004C19.2 33.3023 14.9019 37.6004 9.6 37.6004C4.29807 37.6004 0 33.3023 0 28.0004Z" fill="#A259FE"/>
        <path d="M20 20.0004C20 14.6985 24.2981 10.4004 29.6 10.4004C34.9019 10.4004 39.2 14.6985 39.2 20.0004C39.2 25.3023 34.9019 29.6004 29.6 29.6004H20V20.0004Z" fill="#FF7162"/>
        <path d="M20 30.4004H26.4C29.9346 30.4004 32.8 33.2658 32.8 36.8004C32.8 40.335 29.9346 43.2004 26.4 43.2004C22.8654 43.2004 20 40.335 20 36.8004V30.4004Z" fill="#0DCF82"/>
        <path d="M58.7208 35.224C57.1634 35.224 55.7554 34.9573 54.4968 34.424C53.2594 33.8907 52.2781 33.1227 51.5528 32.12C50.8274 31.1173 50.4541 29.9333 50.4328 28.568H55.2328C55.2968 29.4853 55.6168 30.2107 56.1928 30.744C56.7901 31.2773 57.6008 31.544 58.6248 31.544C59.6701 31.544 60.4914 31.2987 61.0888 30.808C61.6861 30.296 61.9848 29.6347 61.9848 28.824C61.9848 28.1627 61.7821 27.6187 61.3768 27.192C60.9714 26.7653 60.4594 26.4347 59.8408 26.2C59.2434 25.944 58.4114 25.6667 57.3448 25.368C55.8941 24.9413 54.7101 24.5253 53.7928 24.12C52.8968 23.6933 52.1181 23.064 51.4568 22.232C50.8168 21.3787 50.4968 20.248 50.4968 18.84C50.4968 17.5173 50.8274 16.3653 51.4888 15.384C52.1501 14.4027 53.0781 13.656 54.2728 13.144C55.4674 12.6107 56.8328 12.344 58.3688 12.344C60.6728 12.344 62.5394 12.9093 63.9688 14.04C65.4194 15.1493 66.2194 16.7067 66.3688 18.712H61.4408C61.3981 17.944 61.0674 17.3147 60.4488 16.824C59.8514 16.312 59.0514 16.056 58.0488 16.056C57.1741 16.056 56.4701 16.28 55.9368 16.728C55.4248 17.176 55.1688 17.8267 55.1688 18.68C55.1688 19.2773 55.3608 19.7787 55.7448 20.184C56.1501 20.568 56.6408 20.888 57.2168 21.144C57.8141 21.3787 58.6461 21.656 59.7128 21.976C61.1634 22.4027 62.3474 22.8293 63.2648 23.256C64.1821 23.6827 64.9714 24.3227 65.6328 25.176C66.2941 26.0293 66.6248 27.1493 66.6248 28.536C66.6248 29.7307 66.3154 30.84 65.6968 31.864C65.0781 32.888 64.1714 33.7093 62.9768 34.328C61.7821 34.9253 60.3634 35.224 58.7208 35.224ZM86.2848 17.272V35H81.7728V32.76C81.1968 33.528 80.4394 34.136 79.5008 34.584C78.5834 35.0107 77.5808 35.224 76.4928 35.224C75.1061 35.224 73.8794 34.936 72.8128 34.36C71.7461 33.7627 70.9034 32.8987 70.2848 31.768C69.6874 30.616 69.3888 29.2507 69.3888 27.672V17.272H73.8688V27.032C73.8688 28.44 74.2208 29.528 74.9248 30.296C75.6288 31.0427 76.5888 31.416 77.8048 31.416C79.0421 31.416 80.0128 31.0427 80.7168 30.296C81.4208 29.528 81.7728 28.44 81.7728 27.032V17.272H86.2848ZM93.585 19.832C94.161 19.0213 94.9504 18.3493 95.953 17.816C96.977 17.2613 98.1397 16.984 99.441 16.984C100.956 16.984 102.321 17.3573 103.537 18.104C104.774 18.8507 105.745 19.9173 106.449 21.304C107.174 22.6693 107.537 24.2587 107.537 26.072C107.537 27.8853 107.174 29.496 106.449 30.904C105.745 32.2907 104.774 33.368 103.537 34.136C102.321 34.904 100.956 35.288 99.441 35.288C98.1397 35.288 96.9877 35.0213 95.985 34.488C95.0037 33.9547 94.2037 33.2827 93.585 32.472V43.448H89.105V17.272H93.585V19.832ZM102.961 26.072C102.961 25.0053 102.737 24.088 102.289 23.32C101.862 22.5307 101.286 21.9333 100.561 21.528C99.857 21.1227 99.089 20.92 98.257 20.92C97.4464 20.92 96.6784 21.1333 95.953 21.56C95.249 21.9653 94.673 22.5627 94.225 23.352C93.7984 24.1413 93.585 25.0693 93.585 26.136C93.585 27.2027 93.7984 28.1307 94.225 28.92C94.673 29.7093 95.249 30.3173 95.953 30.744C96.6784 31.1493 97.4464 31.352 98.257 31.352C99.089 31.352 99.857 31.1387 100.561 30.712C101.286 30.2853 101.862 29.6773 102.289 28.888C102.737 28.0987 102.961 27.16 102.961 26.072ZM126.633 25.752C126.633 26.392 126.59 26.968 126.505 27.48H113.545C113.651 28.76 114.099 29.7627 114.889 30.488C115.678 31.2133 116.649 31.576 117.801 31.576C119.465 31.576 120.649 30.8613 121.353 29.432H126.185C125.673 31.1387 124.691 32.5467 123.241 33.656C121.79 34.744 120.009 35.288 117.897 35.288C116.19 35.288 114.654 34.9147 113.289 34.168C111.945 33.4 110.889 32.3227 110.121 30.936C109.374 29.5493 109.001 27.9493 109.001 26.136C109.001 24.3013 109.374 22.6907 110.121 21.304C110.867 19.9173 111.913 18.8507 113.257 18.104C114.601 17.3573 116.147 16.984 117.897 16.984C119.582 16.984 121.086 17.3467 122.409 18.072C123.753 18.7973 124.787 19.832 125.513 21.176C126.259 22.4987 126.633 24.024 126.633 25.752ZM121.993 24.472C121.971 23.32 121.555 22.4027 120.745 21.72C119.934 21.016 118.942 20.664 117.769 20.664C116.659 20.664 115.721 21.0053 114.953 21.688C114.206 22.3493 113.747 23.2773 113.577 24.472H121.993ZM133.743 20.024C134.319 19.0853 135.065 18.3493 135.983 17.816C136.921 17.2827 137.988 17.016 139.183 17.016V21.72H137.999C136.591 21.72 135.524 22.0507 134.799 22.712C134.095 23.3733 133.743 24.5253 133.743 26.168V35H129.263V17.272H133.743V20.024ZM150.648 20.952H147.544V35H143V20.952H140.984V17.272H143V16.376C143 14.2 143.619 12.6 144.856 11.576C146.093 10.552 147.96 10.072 150.456 10.136V13.912C149.368 13.8907 148.611 14.072 148.184 14.456C147.757 14.84 147.544 15.5333 147.544 16.536V17.272H150.648V20.952ZM158.031 11.32V35H153.551V11.32H158.031ZM169.37 35.288C167.663 35.288 166.127 34.9147 164.762 34.168C163.396 33.4 162.319 32.3227 161.53 30.936C160.762 29.5493 160.378 27.9493 160.378 26.136C160.378 24.3227 160.772 22.7227 161.562 21.336C162.372 19.9493 163.471 18.8827 164.858 18.136C166.244 17.368 167.791 16.984 169.498 16.984C171.204 16.984 172.751 17.368 174.138 18.136C175.524 18.8827 176.612 19.9493 177.402 21.336C178.212 22.7227 178.618 24.3227 178.618 26.136C178.618 27.9493 178.202 29.5493 177.37 30.936C176.559 32.3227 175.45 33.4 174.042 34.168C172.655 34.9147 171.098 35.288 169.37 35.288ZM169.37 31.384C170.18 31.384 170.938 31.192 171.642 30.808C172.367 30.4027 172.943 29.8053 173.37 29.016C173.796 28.2267 174.01 27.2667 174.01 26.136C174.01 24.4507 173.562 23.16 172.666 22.264C171.791 21.3467 170.714 20.888 169.434 20.888C168.154 20.888 167.076 21.3467 166.202 22.264C165.348 23.16 164.922 24.4507 164.922 26.136C164.922 27.8213 165.338 29.1227 166.17 30.04C167.023 30.936 168.09 31.384 169.37 31.384ZM205.2 17.272L200.016 35H195.184L191.952 22.616L188.72 35H183.856L178.64 17.272H183.184L186.32 30.776L189.712 17.272H194.448L197.776 30.744L200.912 17.272H205.2Z" fill="#353945"/>
      </svg>
    </div>

    <div class="superflow-project-card">
        <div class="superflow-project-header">
          <h2>Project</h2>
          <span class="superflow-status-label sf-not-connected warning-label">Not Connected</span>
          <span class="superflow-status-label sf-connected success-label">Connected</span>
        </div>
        <p class="sf-not-connected installation-info">Install Superflow on your WordPress site to iterate and ship 10X faster!</p>
        <p class="sf-not-connected">Just click on <strong>Connect</strong> below to start your 10-day free trial, and install Superflow in just a <br /> few clicks! <a href="https://usesuperflow.com" target="_blank">Click here</a> to learn more about Superflow.</p>
        <p class="sf-connected installation-info">Install Superflow on your WordPress site to iterate and ship 10X faster!</p>
        <p class="sf-connected">Just click on <strong>Connect</strong> below to start your 10-day free trial, and install Superflow in just a <br /> few clicks! <a href="https://usesuperflow.com" target="_blank">Click here</a> to learn more about Superflow.</p>
        <button id="connectToSuperflow" style="display:none;" class="superflow-connect-btn sf-not-connected">Connect Superflow</button>
        <button id="manageSuperflowSettingBtn" style="display:none;" class="superflow-manage-setting-btn sf-connected">Manage Settings</button>
        <button id="disconnectToSuperflow" style="display:none;" class="superflow-disconnect-btn sf-connected">Disconnect</button>
    </div>
</div>';
  echo '<script>
      window.superflowAjaxUrl = "' . esc_js($ajaxUrl) . '";
      window.superflowPluginOptions = ' . wp_json_encode($plugin_options) . ';
      window.superflowCanManageOptions = ' . wp_json_encode($can_manage_options) . ';
      window.superflowSiteUrl = "' . esc_js($site_url) . '";
      window.superflowNonce = "' . esc_js($nonce) . '";
    </script>';
}

function superflow_register_settings()
{
  register_setting('superflow_plugin_options', 'superflow_plugin_options', 'superflow_plugin_options_validate');

  // General
  add_settings_field('superflow_project_id', 'Project ID', 'superflow_plugin_setting_project_id', 'superflow_plugin');
  add_settings_field('superflow_api_key', 'API Key', 'superflow_plugin_setting_api_key', 'superflow_plugin');
  add_settings_field('superflow_connection_id', 'Connection ID', 'superflow_plugin_setting_connection_id', 'superflow_plugin');
  add_settings_field('superflow_connection_status', 'Status', 'superflow_plugin_setting_connection_status', 'superflow_plugin');
  add_settings_field('superflow_connection_email', 'Connection Email', 'superflow_plugin_setting_connection_email', 'superflow_plugin');
  add_settings_field('superflow_server_doc_id', 'Server ID', 'superflow_plugin_setting_server_doc_id', 'superflow_plugin');
  add_settings_field('superflow_enable_widget', 'Enable Superflow', 'superflow_plugin_setting_enable_widget', 'superflow_plugin');
  add_settings_field('superflow_flow_type', 'Flow Type', 'superflow_plugin_setting_flow_type', 'superflow_plugin');
  add_settings_field('superflow_enable_admin', 'Enable Admin', 'superflow_plugin_setting_enable_admin', 'superflow_plugin');
}
add_action('admin_init', 'superflow_register_settings');

function superflow_plugin_options_validate($input)
{
  // IMPROVEMENT: validation

  // $newinput['destination_id'] = trim( $input['destination_id'] );
  // if ( ! preg_match( '/^[a-z0-9]{32}$/i', $newinput['destination_id'] ) ) {
  //     $newinput['destination_id'] = '';
  // }

  // return $newinput;

  return $input;
}

function superflow_save_project_config()
{
  // Do NOT remove this line !!!
  if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'superflow_nonce')) {
    // Invalid nonce, you can choose to exit or return an error.
     wp_send_json_error('Forbidden', 403);
     return;
  }

  // Do NOT remove this line !!!
  if (!current_user_can('manage_options')) {
    wp_send_json_error('Forbidden', 403);
    return;
  }

  $superflow_plugin_options = get_option('superflow_plugin_options');

  if (isset($_POST["superflow_api_key"]) && isset($_POST["superflow_project_id"])) {
    $superflow_plugin_options['superflow_api_key'] = sanitize_text_field($_POST["superflow_api_key"]);
    $superflow_plugin_options['superflow_project_id'] = sanitize_text_field($_POST["superflow_project_id"]);
  } else {
    $superflow_plugin_options['superflow_api_key'] = "";
    $superflow_plugin_options['superflow_project_id'] = "";
  }

  if (isset($_POST["superflow_enable_widget"]) && $_POST["superflow_enable_widget"]) {
    $superflow_plugin_options['superflow_enable_widget'] = 1;
  } else {
    $superflow_plugin_options['superflow_enable_widget'] = 0;
  }

  // We will not change connection id and email once initialized
  if (!empty($_POST["superflow_connection_id"])) {
    $superflow_plugin_options['superflow_connection_id'] = sanitize_text_field($_POST["superflow_connection_id"]);
  } else {
    $superflow_plugin_options['superflow_connection_id'] = "";
  }

  // Flow type should be auto or manual
  if (isset($_POST["superflow_flow_type"]) && $_POST["superflow_flow_type"]) {
    $superflow_plugin_options['superflow_flow_type'] = sanitize_text_field($_POST["superflow_flow_type"]);
  }

  if (isset($_POST["superflow_server_doc_id"]) && $_POST["superflow_server_doc_id"]) {
    $superflow_plugin_options['superflow_server_doc_id'] = sanitize_text_field($_POST["superflow_server_doc_id"]);
  }

  if (isset($_POST["superflow_connection_status"]) && $_POST["superflow_connection_status"]) {
    $superflow_plugin_options['superflow_connection_status'] = sanitize_text_field($_POST["superflow_connection_status"]);
  } else {
    $superflow_plugin_options['superflow_connection_status'] = "pending";
  }

  if (!empty($superflow_plugin_options['superflow_connection_id']) && !empty($superflow_plugin_options['superflow_api_key']) && !empty($superflow_plugin_options['superflow_project_id'])) {
    $superflow_plugin_options['superflow_connection_status'] = "created";
    $superflow_plugin_options['superflow_enable_widget'] = 1;
  } else {
    $superflow_plugin_options['superflow_connection_status'] = "pending";
    $superflow_plugin_options['superflow_enable_widget'] = 0;
  }

  update_option('superflow_plugin_options', $superflow_plugin_options);

  echo wp_json_encode($superflow_plugin_options);
  die();
}
add_action('wp_ajax_' . 'superflow_save_project_config', 'superflow_save_project_config');


function superflow_get_project_config()
{
  $superflow_plugin_options = get_option('superflow_plugin_options');

  echo wp_json_encode(["config" => $superflow_plugin_options]);
  die();
}
add_action('wp_ajax_' . 'superflow_get_project_config', 'superflow_get_project_config');
