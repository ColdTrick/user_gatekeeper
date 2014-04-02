<?php
/**
 * All helper functions for this plugin are bundled here
 */

/**
 * Protect a user's page from being viewed by anyone who's not a friend of group member
 *
 * @return void|bool
 */
function user_gatekeeper_gatekeeper() {
	
	// start with the default gatekeeper
	gatekeeper();
	
	// get the current user and page owner
	$user = elgg_get_logged_in_user_entity();
	$page_owner = elgg_get_page_owner_entity();
	
	if ($user->isAdmin()) {
		// admins can view everything
		return true;
	}
	
	if (empty($page_owner) || !elgg_instanceof($page_owner, "user")) {
		// how did we get here?
		return false;
	}
	
	if ($user->getGUID() == $page_owner->getGUID()) {
		// user is viewing his/her own page
		return true;
	}
	
	if ($user->isFriendOf($page_owner->getGUID())) {
		// current user is a friend of the page owner
		return true;
	}
	
	// check group membership
	$user_group_guids = user_gatekeeper_get_group_membership_guids($user->getGUID());
	$page_owner_group_guids = user_gatekeeper_get_group_membership_guids($page_owner->getGUID());
	
	if (!empty($user_group_guids) && !empty($page_owner_group_guids)) {
		foreach ($user_group_guids as $group_guid) {
			if (in_array($group_guid, $page_owner_group_guids)) {
				// the current user and the page owner share at least one group
				return true;
			}
		}
	}
	
	// if we come here you're not allowed to view the current user's page
	// so let you know and forward
	register_error(elgg_echo("InvalidParameterException:NoEntityFound"));
	forward(REFERER);
}

/**
 * Return the group GUIDs of all the groups the provided user is a member of
 *
 * @param int $user_guid the user to check
 *
 * @return bool|int[]
 */
function user_gatekeeper_get_group_membership_guids($user_guid) {
	$result = false;
	
	$user_guid = sanitise_int($user_guid, false);
	
	if (!empty($user_guid) && elgg_is_active_plugin("groups")) {
		$options = array(
			"type" => "group",
			"limit" => false,
			"relationship" => "member",
			"relationship_guid" => $user_guid,
			"callback" => "user_gatekeeper_row_to_guid"
		);
		
		$result = elgg_get_entities_from_relationship($options);
	}
	
	return $result;
}

/**
 * Get the guids of all users who have the specified user as a friend
 *
 * @param int $user_guid the user to find the friends of
 *
 * @return bool|int[]
 */
function user_gatekeeper_get_user_friends_of_guids($user_guid) {
	$result = false;
	
	$user_guid = sanitise_int($user_guid, false);
	
	if (!empty($user_guid)) {
		$options = array(
			"type" => "user",
			"limit" => false,
			"relationship" => "friend",
			"relationship_guid" => $user_guid,
			"inverse_relationship" => true,
			"callback" => "user_gatekeeper_row_to_guid"
		);
		
		$result = elgg_get_entities_from_relationship($options);
	}
	
	return $result;
}

/**
 * Custom callback function for elgg_get_entities* to return only the GUID of a row
 *
 * @param stdClass $row a database row
 *
 * @return int
 */
function user_gatekeeper_row_to_guid($row) {
	return (int) $row->guid;
}
