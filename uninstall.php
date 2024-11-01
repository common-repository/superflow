<?php

if (!defined('ABSPATH')) {
    exit;
}

// Do NOT delete this line !!!
if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

$option_name = 'superflow_plugin_options';

delete_option($option_name);

?>
