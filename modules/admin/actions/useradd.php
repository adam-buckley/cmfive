<?php
/**
* Display User edit form in colorbox
*
* @param <type> $w
*/
function useradd_GET(Web &$w) {
	$p = $w->pathMatch("box");
	if (!$p['box']) {
		AdminLib::navigation($w,"Add User");
	} else {
		$w->setLayout(null);
	}
}

/**
 * Handle User Edit form submission
 *
 * @param <type> $w
 */
function useradd_POST(Web &$w) {
	$errors = $w->validate(array(
	array("login",".+","Login is mandatory"),
	array("password",".+","Password is mandatory"),
	array("password2",".+","Password2 is mandatory"),
	));
	if ($_REQUEST['password2'] != $_REQUEST['password']) {
		$errors[]="Passwords don't match";
	}
	if (sizeof($errors) != 0) {
		$w->error(implode("<br/>\n",$errors),"/admin/useradd");
	}

	// first saving basic contact info
	$contact = new Contact($w);
	$contact->fill($_REQUEST);
	$contact->dt_created = time();
	$contact->private_to_user_id= null;
	$contact->insert();

	// now saving the user
	$user = new User($w);
	$user->login = $_REQUEST['login'];
	$user->setPassword($_REQUEST['password']);
	$user->is_active = $_REQUEST['is_active'] ? $_REQUEST['is_active'] : 0;
	$user->is_admin = $_REQUEST['is_admin'] ? $_REQUEST['is_admin'] : 0;
	$user->dt_created = time();
	$user->contact_id = $contact->id;
	$user->insert();
	$w->ctx("user",$user);

	// now saving the roles
	$roles = $w->Auth->getAllRoles();
	foreach ($roles as $r) {
		if ($_REQUEST["check_".$r]==1) {
			$user->addRole($r);
		}
	}
	$w->msg("User ".$user->login." added","/admin/users");
}