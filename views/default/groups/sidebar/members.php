<?php
/**
 * Group members sidebar
 *
 * @package ElggGroups
 *
 * @uses $vars["entity"] Group entity
 * @uses $vars["limit"]  The number of members to display
 */

$group = elgg_extract("entity", $vars);

if (!empty($group) && elgg_instanceof($group, "group")) {
	// check for group tools setting
	if (elgg_is_active_plugin("group_tools") && $group->getPrivateSetting("group_tools:cleanup:members") == "yes") {
		// this view is hidden
		return true;
	}
	
	if (!$group->isMember()) {
		// you're not a member of this group
		return true;
	}
	
	// show members
	$limit = elgg_extract("limit", $vars, 14);
	
	$all_link = elgg_view("output/url", array(
		"href" => "groups/members/" . $group->getGUID(),
		"text" => elgg_echo("groups:members:more"),
		"is_trusted" => true,
	));
	
	$body = elgg_list_entities_from_relationship(array(
		"relationship" => "member",
		"relationship_guid" => $group->getGUID(),
		"inverse_relationship" => true,
		"types" => "user",
		"limit" => $limit,
		"list_type" => "gallery",
		"gallery_class" => "elgg-gallery-users",
		"pagination" => false
	));
	
	$body .= "<div class='center mts'>$all_link</div>";
	
	echo elgg_view_module("aside", elgg_echo("groups:members"), $body);
}
