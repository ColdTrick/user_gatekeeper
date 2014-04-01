<?php
/**
 * The main file of this plugin, get loaded when all plugins start
 */

require_once(dirname(__FILE__) . "/lib/functions.php");
require_once(dirname(__FILE__) . "/lib/hooks.php");

// register default Elgg events
elgg_register_event_handler("init", "system", "user_gatekeeper_init");
elgg_register_event_handler("pagesetup", "system", "user_gatekeeper_pagesetup");

/**
 * Gets called when the plugin gets initialized
 *
 * @return void
 */
function user_gatekeeper_init() {
	
	// extends views
	elgg_extend_view("groups/sidebar/members", "user_gatekeeper/group_members");
	
	// register plugin hooks
	elgg_register_plugin_hook_handler("route", "all", "user_gatekeeper_route_hook");
	
	elgg_unregister_plugin_hook_handler("search", "user", "search_users_hook");
	elgg_register_plugin_hook_handler("search", "user", "user_gatekeeper_search_users_hook");
}

/**
 * Gets called just before the first page content is created
 *
 * @return void
 */
function user_gatekeeper_pagesetup() {
	$page_owner = elgg_get_page_owner_entity();
	
	// some page handlers can't auto detect the page owner
	if (elgg_in_context("profile")) {
		user_gatekeeper_gatekeeper();
	}
	
	if (!empty($page_owner)) {
		// special group protection
		if (elgg_instanceof($page_owner, "group")) {
			if (!$page_owner->isMember()) {
				// unregister widgets
				elgg_unregister_widget_type("group_members");
			}
		}
		
		// protect some extra pages
		$context = elgg_get_context();
		$special_contexts = array("friends", "friendsof", "collections");
		if (in_array($context, $special_contexts)) {
			if ($page_owner->getGUID() != elgg_get_logged_in_user_guid()) {
				register_error(elgg_echo("InvalidParameterException:NoEntityFound"));
				forward(REFERER);
			}
		}
	}
}
