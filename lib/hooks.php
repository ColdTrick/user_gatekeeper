<?php
/**
 * All plugin hook callbacks are bundled in this file
 */

/**
 * Listen to all page handlers and check if you wish to access a user's page.
 * Block if not friend or groupmember
 *
 * @param string $hook        'route'
 * @param string $type        the current page handler
 * @param array  $returnvalue the current params for the page_handler
 * @param null   $params      null
 *
 * @return void
 */
function user_gatekeeper_route_hook($hook, $type, $returnvalue, $params) {
	$page_owner = elgg_get_page_owner_entity();
	
	if (!empty($page_owner) && elgg_instanceof($page_owner, "user")) {
		// we found a user page, protect it
		user_gatekeeper_gatekeeper();
		
		// special protection of the */friends/* pages
		$segments = elgg_extract("segments", $returnvalue);
		if (isset($segments[0]) && (stristr($segments[0], "friends") !== false)) {
			// you can only look at your own friends content
			if ($page_owner->getGUID() != elgg_get_logged_in_user_guid()) {
				register_error(elgg_echo("InvalidParameterException:NoEntityFound"));
				forward(REFERER);
			}
		}
	}
}

/**
 * Get users that match the search parameters.
 *
 * Searches on username, display name, and profile fields.
 * Function taken from Elgg core, but added friends or group member requirement
 *
 * @param string $hook        Hook name
 * @param string $type        Hook type
 * @param array  $returnvalue Empty array
 * @param array  $params      Search parameters
 *
 * @return array
 */
function user_gatekeeper_search_users_hook($hook, $type, $returnvalue, $params) {
	$db_prefix = elgg_get_config("dbprefix");
	$user = elgg_get_logged_in_user_entity();
	
	// only logged in users can search for users
	if (empty($user)) {
		return array("entities" => array(), "count" => 0);
	}
	
	// start with the actual search
	$query = sanitise_string($params["query"]);
	
	$params["joins"] = array(
		"JOIN {$db_prefix}users_entity ue ON e.guid = ue.guid",
		"JOIN {$db_prefix}metadata md on e.guid = md.entity_guid",
		"JOIN {$db_prefix}metastrings msv ON n_table.value_id = msv.id"
	);
	
	// username and display name
	$fields = array("username", "name");
	$where = search_get_where_sql("ue", $fields, $params, FALSE);
	
	// profile fields
	$profile_fields = array_keys(elgg_get_config("profile_fields"));
	
	// get the where clauses for the md names
	// can"t use egef_metadata() because the n_table join comes too late.
	$clauses = elgg_entities_get_metastrings_options("metadata", array(
		"metadata_names" => $profile_fields,
	));
	
	$params["joins"] = array_merge($clauses["joins"], $params["joins"]);
	// no fulltext index, can"t disable fulltext search in this function.
	// $md_where .= " AND " . search_get_where_sql("msv", array("string"), $params, FALSE);
	$md_where = "(({$clauses["wheres"][0]}) AND msv.string LIKE '%$query%')";

	$params["wheres"] = array("(($where) OR ($md_where))");
	
	if (!$user->isAdmin()) {
		// normal users are limited to group members and friends_of
		
		// check for group membership
		$groups = user_gatekeeper_get_group_membership_guids($user->getGUID());
		$relation_wheres = array();
		if (!empty($groups)) {
			$params["joins"][] = "JOIN {$db_prefix}entity_relationships rg ON e.guid = rg.guid_one";
			$relation_wheres[] = "(rg.guid_two IN (" . implode(",", $groups) . ") AND rg.relationship = 'member')";
		}
		
		// check for friends
		$friends = user_gatekeeper_get_user_friends_of_guids($user->getGUID());
		if (!empty($friends)) {
			$relation_wheres[] = "(e.guid IN (" . implode(",", $friends) . "))";
		}
		
		if (!empty($relation_wheres)) {
			$params["wheres"][] = "(" . implode(" OR ", $relation_wheres) . ")";
		} else {
			// no friends or groups, so you can't find users
			return array("entities" => array(), "count" => 0);
		}
	}
	
	// override subtype -- All users should be returned regardless of subtype.
	$params["subtype"] = ELGG_ENTITIES_ANY_VALUE;
	$params["count"] = true;
	$count = elgg_get_entities($params);

	// no need to continue if nothing here.
	if (!$count) {
		return array("entities" => array(), "count" => $count);
	}

	$params["count"] = FALSE;
	$params["order_by"] = search_get_order_by_sql("e", "ue", $params["sort"], $params["order"]);
	$entities = elgg_get_entities($params);

	// add the volatile data for why these entities have been returned.
	foreach ($entities as $entity) {

		$title = search_get_highlighted_relevant_substrings($entity->name, $query);

		// include the username if it matches but the display name doesn"t.
		if (false !== strpos($entity->username, $query)) {
			$username = search_get_highlighted_relevant_substrings($entity->username, $query);
			$title .= " ($username)";
		}

		$entity->setVolatileData("search_matched_title", $title);

		$matched = "";
		foreach ($profile_fields as $md_name) {
			$metadata = $entity->$md_name;
			if (is_array($metadata)) {
				foreach ($metadata as $text) {
					if (stristr($text, $query)) {
						$matched .= elgg_echo("profile:{$md_name}") . ": "
								. search_get_highlighted_relevant_substrings($text, $query);
					}
				}
			} else {
				if (stristr($metadata, $query)) {
					$matched .= elgg_echo("profile:{$md_name}") . ": "
							. search_get_highlighted_relevant_substrings($metadata, $query);
				}
			}
		}

		$entity->setVolatileData("search_matched_description", $matched);
	}

	return array(
		"entities" => $entities,
		"count" => $count,
	);
}