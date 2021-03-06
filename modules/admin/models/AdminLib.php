<?php
class AdminLib {
	static function navigation(&$w,$title,$prenav=null) {
		if ($title) {
			$w->ctx("title",$title);
		}
		$nav = $prenav ? $prenav : array();
		if ($w->Auth->loggedIn()) {
			$w->menuLink("admin/users","List Users",$nav);
			$w->menuLink("admin/groups","List Groups",$nav);
			$w->menuLink("admin/lookup","Lookup",$nav);
			$w->menuLink("admin/phpinfo","PHP Info",$nav);
		}
		$w->ctx("navigation", $nav);
	}
}