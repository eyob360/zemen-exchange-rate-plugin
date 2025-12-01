<?php
/**
 * Register the plugin menu and submenus.
 */
if (!defined('ABSPATH')) exit;


function exr_360_add_admin_menu()
{
    // Main menu
    add_menu_page(
        "360ExchangeRate", // Page title
        "360ExchangeRate", // Menu title
        "manage_options", // Capability
        "360exchangerate", // Menu slug
        "exr_360_main_page", // Callback function
        "dashicons-chart-bar", // Icon
        26 // Position
    );

    // Submenu - Upload Daily Exchange
    add_submenu_page(
        "360exchangerate", // Parent slug
        "Upload Daily Exchange", // Page title
        "Upload Daily Exchange", // Menu title
        "manage_options", // Capability
        "upload-daily-exchange", // Menu slug
        "exr_360_upload_daily_page" // Callback function
    );

    // Submenu - Manage Exchange
    add_submenu_page(
        "360exchangerate", // Parent slug
        "Manage Exchange", // Page title
        "Manage Exchange", // Menu title
        "manage_options", // Capability
        "manage-exchange", // Menu slug
        "exr_360_manage_page" // Callback function
    );
}
add_action("admin_menu", "exr_360_add_admin_menu");