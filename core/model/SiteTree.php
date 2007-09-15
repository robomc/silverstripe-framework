<?php
/**
 * @package sapphire
 * @subpackage core
 */

/**
 * Basic data-object representing all pages within the site tree.
 * This data-object takes care of the heirachy.  All page types that live within the heirachy
 * should inherit from this.
 *
 * In addition, it contains a number of static methods for querying the site tree.
 */
class SiteTree extends DataObject {

	/**
	 * Indicates what kind of children this page type can have.
	 * This can be an array of allowed child classes, or the string "none" -
	 * indicating that this page type can't have children.
	 * If a classname is prefixed by "*", such as "*Page", then only that
	 * class is allowed - no subclasses. Otherwise, the class and all its
	 * subclasses are allowed.
	 *
	 * @var array
	 */
	static $allowed_children = array("SiteTree");

	/**
	 * The default child class for this page.
	 *
	 * @var string
	 */
	static $default_child = "Page";

	/**
	 * The default parent class for this page.
	 *
	 * @var string
	 */
	static $default_parent = null;

	/**
	 * Controls whether a page can be in the root of the site tree.
	 *
	 * @var bool
	 */
	static $can_be_root = true;

	/**
	 * List of permission codes a user can have to allow a user to create a
	 * page of this type.
	 *
	 * @var array
	 */
	static $need_permission = null;

	/**
	 * If you extend a class, and don't want to be able to select the old class
	 * in the cms, set this to the old class name. Eg, if you extended Product
	 * to make ImprovedProduct, then you would set $hide_ancestor to Product.
	 *
	 * @var string
	 */
	static $hide_ancestor = null;

	static $db = array(
		"URLSegment" => "Varchar(255)",
		"Title" => "Varchar(255)",
		"MenuTitle" => "Varchar(100)",
		"Content" => "HTMLText",
		"MetaTitle" => "Varchar(255)",
		"MetaDescription" => "Varchar(255)",
		"MetaKeywords" => "Varchar(255)",
		"ShowInMenus" => "Boolean",
		"ShowInSearch" => "Boolean",
		"HomepageForDomain" => "Varchar(100)",
		"ProvideComments" => "Boolean",
		"Sort" => "Int",
		"LegacyURL" => "Varchar(255)",
		"HasBrokenFile" => "Boolean",
		"HasBrokenLink" => "Boolean",
		"Status" => "Varchar",
		"ReportClass" => "Varchar",
		"Priority" => "Float",

		"Viewers" => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers', 'Anyone')",
		"Editors" => "Enum('LoggedInUsers, OnlyTheseUsers', 'LoggedInUsers')",
		"ViewersGroup" => "Int",
		"EditorsGroup" => "Int"
	);

  static $indexes = array(
    "SearchFields" => "fulltext (Title, MenuTitle, Content, MetaTitle, MetaDescription, MetaKeywords)",
    "TitleSearchFields" => "fulltext (Title)"
	);

	static $has_many = array(
		"Comments" => "PageComment"
	);

	static $many_many = array(
		"LinkTracking" => "SiteTree",
		"ImageTracking" => "File"
	);

	static $belongs_many_many = array(
		"BackLinkTracking" => "SiteTree"
	);

	static $many_many_extraFields = array(
		"LinkTracking" => array("FieldName" => "Varchar"),
		"ImageTracking" => array("FieldName" => "Varchar")
	);

	static $casting = array(
		"Breadcrumbs" => "HTMLText",
		"LastEdited" => "Datetime",
		"Created" => "Datetime",
	);

	static $defaults = array(
		"ShowInMenus" => 1,
		"ShowInSearch" => 1,
		"Status" => "New page",
		"CanCreateChildren" => array(10),
		"Priority" => 0.5,

		"Viewers" => "Anyone",
		"Editors" => "LoggedInUsers"
	);

	static $has_one = array(
		"Parent" => "SiteTree"
	);

	static $versioning = array(
		"Stage",  "Live"
	);

	static $default_sort = "Sort";

	/**
	 * The text shown in the create page dropdown. If
	 * this is not set, default to "Create a ClassName".
	 * @var string
	 */
	static $add_action = null;

	/**
	 * If this is false, the class cannot be created in the CMS.
	 * @var boolean
	*/
	static $can_create = true;


	/**
	 * Icon to use in the CMS
	 *
	 * This should be the base filename.  The suffixes -file.gif,
	 * -openfolder.gif and -closedfolder.gif will be appended to the base name
	 * that you provide there.
	 * If you prefer, you can pass an array:
	 * array("jsparty\tree\images\page", $option).
	 * $option can be either "file" or "folder" to force the icon to always
	 * be a file or folder, regardless of whether the page has children or not
	 *
	 * @var string|array
	 */
	static $icon = array("jsparty/tree/images/page", "file");


	static $extensions = array(
		"Hierarchy",
		"Versioned('Stage', 'Live')",
	);


	/**
	 * Get the URL for this page.
	 *
	 * @param string $action An action to include in the link
	 * @return string The URL for this page
	 */
	public function Link($action = null) {
		if($action == "index") {
			$action = "";
		}
		return Director::baseURL() . $this->URLSegment . "/$action";
	}
	


	/**
	 * Get the absolute URL for this page by stage
	 */
	public function AbsoluteLink() {
		if($this->hasMethod('alternateAbsoluteLink')) return $this->alternateAbsoluteLink();
		else return Director::absoluteURL($this->Link());
	}
	
		
	/**
	 * Returns link/current, depending on whether you're on the current page.
	 * This is useful for css styling of menus.
	 *
	 * @return string Either 'link' or 'current'.
	 */
	public function LinkOrCurrent() {
		return ($this->isCurrent()) ? "current" : "link";
	}


	/**
	 * Returns link/section, depending on whether you're on the current section.
	 * This is useful for css styling of menus.
	 *
	 * @return string Either 'link' or 'section'.
	 */
	public function LinkOrSection() {
		return ($this->isSection()) ? "section" : "link";
	}


	/**
	 * Returns link/current/section, depending if you're not in the current
	 * section, you're on the current page, or you're in the current section
	 * but not on the current page.
	 *
	 * @return string Either 'link', 'current' or 'section'.
	 */
	public function LinkingMode() {
		$this->prepareCurrentAndSection();

		if($this->ID == self::$currentPageID) {
			return "current";
		} else if(in_array($this->ID, self::$currentSectionIDs)) {
			return "section";
		} else {
			return "link";
		}
	}


	/**
	 * Get the URL segment for this page, eg 'home'
	 *
	 * @return string The URL segment
	 */
	public function ElementName() {
		return $this->URLSegment;
	}


	/**
	 * Check if this page is in the given current section.
	 *
	 * @param string $sectionName Name of the section to check.
	 * @return boolean True if we are in the given section.
	 */
	public function InSection($sectionName) {
		$page = Director::currentPage();
		while($page) {
			if($sectionName == $page->URLSegment)
				return true;
			$page = $page->Parent;
		}
		return false;
	}


	/**
	 * Returns comments on this page. This will only show comments that
	 * have been marked as spam if "?showspam=1" is appended to the URL.
	 *
	 * @return DataObjectSet Comments on this page.
	 */
	public function Comments() {
		$spamfilter = isset($_GET['showspam']) ? '' : 'AND IsSpam=0';
		$unmoderatedfilter = Permission::check('ADMIN') ? '' : 'AND NeedsModeration = 0';
		$comments =  DataObject::get("PageComment", "ParentID = '" . Convert::raw2sql($this->ID) . "' $spamfilter $unmoderatedfilter", "Created DESC");
		
		return $comments ? $comments : new DataObjectSet();
	}


	/**
	 * Create a duplicate of this node. Doesn't affect joined data - create a
	 * custom overloading of this if you need such behaviour.
	 *
	 * @return SiteTree The duplicated object.
	 */
	 public function duplicate($doWrite = true) {
		$page = parent::duplicate($doWrite);
		$page->CheckedPublicationDifferences = $page->AddedToStage = true;
		return $page;
	}


	/**
	 * Duplicates each child of this node recursively and returns the
	 * duplicate node.
	 *
	 * @return SiteTree The duplicated object.
	 */
	public function duplicateWithChildren() {
		$clone = $this->duplicate();
		$children = $this->AllChildren();

		if($children) {
			foreach($children as $child) {
				$childClone = method_exists($child, 'duplicateWithChildren')
					? $child->duplicateWithChildren()
					: $child->duplicate();
				$childClone->ParentID = $clone->ID;
				$childClone->write();
			}
		}

		return $clone;
	}


	/**
	 * Duplicate this node and its children as a child of the node with the
	 * given ID
	 *
	 * @param int $id ID of the new node's new parent
	 */
	public function duplicateAsChild($id) {
		$newSiteTree = $this->duplicate();
		$newSiteTree->ParentID = $id;
		$newSiteTree->write();
	}


	/**
	 * An array of this pages URL segment and it's parents.
	 * This is generated by prepareCurrentAndSection for use by
	 * isCurrent() and isSection()
	 *
	 * @var array
	 */
	protected static $currentSectionIDs;


	/**
	 * The current page ID.
	 * This is generated by prepareCurrentAndSection for use by
	 * isCurrent() and isSection()
	 *
	 * @var int
	 */
	protected static $currentPageID;


	/**
	 * This function is used for isCurrent() and isSection() to prepare
	 * the cached answers.
	 */
	protected function prepareCurrentAndSection() {
		if(!self::$currentPageID) {
			self::$currentPageID = Director::currentPage() ? Director::currentPage()->ID : null;
			if(!isset(self::$currentPageID)) {
				self::$currentPageID = -1;
				$nextID = isset(Director::currentPage()->Parent->ID)
					? Director::currentPage()->Parent->ID
					: null;
			} else {
				$nextID = SiteTree::$currentPageID;
			}

			$table = (Versioned::current_stage() == "Live")
				? "SiteTree_Live"
				: "SiteTree";

			SiteTree::$currentSectionIDs = array();
			while($nextID) {
				self::$currentSectionIDs[] = $nextID;
				$nextID = DB::query("SELECT ParentID FROM SiteTree WHERE ID = $nextID")->value();
			}
		}
	}


	/**
	 * Check if this is the currently viewed page.
	 *
	 * @return boolean True if this is the current page.
	 */
	public function isCurrent() {
		$this->prepareCurrentAndSection();
		return $this->ID == SiteTree::$currentPageID;
	}


	/**
	 * Check if the currently viewed page is in this section.
	 *
	 * @return boolean True if the currently viewed page is in this section.
	 */
	public function isSection() {
		$this->prepareCurrentAndSection();
		return in_array($this->ID, self::$currentSectionIDs);
	}


	/**
	 * Return a breadcrumb trail to this page.
	 *
	 * @param int $maxDepth The maximum depth to traverse.
	 * @param boolean $unlinked Do not make page names links
	 * @param string $stopAtPageType ClassName of a page to stop the upwards traversal.
	 * @return string The breadcrumb trail.
	 */
	public function Breadcrumbs($maxDepth = 20, $unlinked = false,
															$stopAtPageType = false) {
		$page = $this;
		$parts = array();
		$i = 0;
		while(($page && (sizeof($parts) < $maxDepth))	||
					($stopAtPageType && $page->ClassName != $stopAtPageType)) {
			if($page->ShowInMenus || ($page->ID == $this->ID)) {
				if($page->URLSegment == 'home') {
					$hasHome = true;
				}
				$parts[] = (($page->ID == $this->ID) || $unlinked)
					? Convert::raw2xml($page->Title)
					: ("<a href=\"" . $page->Link() . "\">" . Convert::raw2xml($page->Title) . "</a>");
			}
			$page = $page->Parent;
		}

		return implode(" &raquo; ", array_reverse($parts));
	}


	/**
	 * Get the parent of this page.
	 *
	 * @return SiteTree Parent of this page.
	 */
	public function getParent() {
		if($this->getField("ParentID"))
			return DataObject::get_one("SiteTree",
																 "`SiteTree`.ID = " . $this->getField("ParentID"));
	}


	/**
	 * Make this page a child of another page.
	 *
	 * @param SiteTree|int $item Either the parent object, or the parent ID
	 */
	public function setParent($item) {
		if(is_object($item)) {
			$this->setField("ParentID", $item->ID);
		} else {
			$this->setField("ParentID", $item);
		}
	}


	/**
	 * Return a string of the form "parent - page" or
	 * "grandparent - parent - page".
	 *
	 * @param int $level The maximum amount of levels to traverse.
	 * @param string $seperator Seperating string
	 * @return string The resulting string
	 */
	function NestedTitle($level = 2, $separator = " - ") {
		$item = $this;
		while($item && $level > 0) {
			$parts[] = $item->Title;
			$item = $item->Parent;
			$level--;
		}
		return implode($separator, array_reverse($parts));
	}

	/**
	 * This function should return true if the current user can add children
	 * to this page.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * Returns true if the member is allowed to do the given action.
	 *
	 * @param string $perm The permission to be checked, such as 'View'.
	 * @param Member $member The member whose permissions need checking.
	 *                       Defaults to the currently logged in user.
	 *
	 * @return boolean True if the the member is allowed to do the given
	 *                 action.
	 *
	 * @todo Check we get a endless recursion if we use parent::can()
	 */
	function can($perm, $member = null) {
		if(!isset($member)) {
			$member = Member::currentUser();
		}
		if($member && $member->isAdmin()) {
			return true;
		}

	  switch(strtolower($perm)) {
	    case 'edit':
	      if((Permission::check('CMS_ACCESS_CMSMain') &&
						(($this->Editors == 'LoggedInUsers' && $member) ||
					  ($this->Editors == 'OnlyTheseUsers' && $member &&
						$member->isInGroup($this->EditorsGroup)))) == false)
					return false;
				break;

	    case 'view':
			case 'view_page':
				if((($this->Viewers == 'Anyone') ||
						($this->Viewers == 'LoggedInUsers' && $member) ||
						($this->Viewers == 'OnlyTheseUsers' && $member &&
						 $member->isInGroup($this->ViewersGroup))) == false)
					return false;
				break;
		}

		return true;

		//return parent::can($perm, $member);
	}


	/**
	 * This function should return true if the current user can add children
	 * to this page.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * @return boolean True if the current user can add children.
	 */
	public function canAddChildren() {
		return $this->canEdit() && $this->stat('allowed_children') != 'none';
	}


	/**
	 * This function should return true if the current user can view this
	 * page.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * @return boolean True if the current user can view this page.
	 */
	public function canView() {
		return $this->can('view');
	}


	/**
	 * This function should return true if the current user can delete this
	 * page.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * @return boolean True if the current user can delete this page.
	 */
	public function canDelete() {
		return $this->stat('can_create') != false;
	}


	/**
	 * This function should return true if the current user can create new
	 * pages of this class.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * @return boolean True if the current user can create pages on this
	 *                 class.
	 */
	public function canCreate() {
		return $this->stat('can_create') != false || Director::isDev();
	}


	/**
	 * This function should return true if the current user can edit this
	 * page.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * @return boolean True if the current user can edit this page.
	 */
	public function canEdit() {
		return $this->can('Edit');
	}

	/**
	 * This function should return true if the current user can publish this
	 * page.
	 *
	 * It can be overloaded to customise the security model for an
	 * application.
	 *
	 * @return boolean True if the current user can publish this page.
	 */
	public function canPublish() {
		return $this->canEdit();
	}

	/**
	 * Collate selected descendants of this page.
	 *
	 * {@link $condition} will be evaluated on each descendant, and if it is
	 * succeeds, that item will be added to the $collator array.
	 *
	 * @param string $condition The PHP condition to be evaluated. The page
	 *                          will be called $item
	 * @param array $collator An array, passed by reference, to collect all
	 *                        of the matching descendants.
	 */
	public function collateDescendants($condition, &$collator) {
		if($children = $this->Children()) {
			foreach($children as $item) {
				if(eval("return $condition;")) $collator[] = $item;
				$item->collateDescendants($condition, $collator);
			}
			return true;
		}
	}


	/**
	 * Return the title, description and keywords metatags.
	 * @param boolean|string $includeTitle Show default <title>-tag, set to false for custom templating
	 *
	 * @param boolean $includeTitle Show default <title>-tag, set to false for
	 *                              custom templating
	 * @return string The XHTML metatags
	 */
	public function MetaTags($includeTitle = true) {
		$tags = "";
		if($includeTitle === true || $includeTitle == 'true') {
			$tags .= "<title>" . Convert::raw2xml(($this->MetaTitle)
				? $this->MetaTitle
				: $this->Title) . "</title>\n";
		}
		$tags .= "<meta name=\"generator\" http-equiv=\"generator\" content=\"SilverStripe 2.0 - http://www.silverstripe.com\" />\n";

		$charset = ContentNegotiator::get_encoding();
		$tags .= "<meta http-equiv=\"Content-type\" content=\"text/html; charset=$charset\" />\n";
		if($this->MetaKeywords) {
			$tags .= "<meta name=\"keywords\" http-equiv=\"keywords\" content=\"" .
				Convert::raw2att($this->MetaKeywords) . "\" />\n";
		}
		if($this->MetaDescription) {
			$tags .= "<meta name=\"description\" http-equiv=\"description\" content=\"" .
				Convert::raw2att($this->MetaDescription) . "\" />\n";
		}
		if($this->ExtraMeta) { 
			$tags .= $this->ExtraMeta . "\n";
		} 

		return $tags;
	}


	/**
	 * Returns the object that contains the content that a user would
	 * associate with this page.
	 *
	 * Ordinarily, this is just the page itself, but for example on
	 * RedirectorPages or VirtualPages ContentSource() will return the page
	 * that is linked to.
	 *
	 * @return SiteTree The content source.
	 */
	public function ContentSource() {
		return $this;
	}


	/**
	 * Add default records to database.
	 *
	 * This function is called whenever the database is built, after the
	 * database tables have all been created. Overload this to add default
	 * records when the database is built, but make sure you call
	 * parent::requireDefaultRecords().
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		
		if($this->class == 'SiteTree') {
			if(!DataObject::get_one("SiteTree", "URLSegment = 'home'")) {
				$homepage = new Page();

				$homepage->Title = "Home";
				$homepage->Content = "<p>Welcome to SilverStripe! This is the default homepage. You can edit this page by opening <a href=\"admin/\">the CMS</a>.</p>";
				$homepage->URLSegment = "home";
				$homepage->Status = "Published";
				$homepage->write();
				$homepage->publish("Stage", "Live");
				$homepage->flushCache();
				Database::alteration_message("Home page created","created");		
			}

			if(DB::query("SELECT COUNT(*) FROM SiteTree")->value() == 1) {
				$aboutus = new Page();
				$aboutus->Title = "About Us";
				$aboutus->Content = "<p>You can fill this page out with your own content, or delete it and create your own pages.<br /></p>";
				$aboutus->URLSegment = "about-us";
				$aboutus->Status = "Published";
				$aboutus->write();
				$aboutus->publish("Stage", "Live");
				Database::alteration_message("About Us created","created");

				$contactus = new Page();
				$contactus->Title = "Contact Us";
				$contactus->Content = "<p>You can fill this page out with your own content, or delete it and create your own pages.<br /></p>";
				$contactus->URLSegment = "contact-us";
				$contactus->Status = "Published";
				$contactus->write();
				$contactus->publish("Stage", "Live");

				$contactus->flushCache();
			}
		}
	}


	//------------------------------------------------------------------------------------//

	protected function onBeforeWrite() {
		if(!$this->Sort && $this->ParentID) {
			$this->Sort = DB::query(
				"SELECT MAX(Sort) + 1 FROM SiteTree WHERE ParentID = $this->ParentID")->value();
		}

		// Auto-set URLSegment
		if((!$this->URLSegment || $this->URLSegment == 'new-page') &&
			 $this->Title) {
			$this->URLSegment = $this->generateURLSegment($this->Title);

		// Keep it clean
		} else if(isset($this->changed['URLSegment']) &&
							$this->changed['URLSegment']) {
			$segment = ereg_replace('[^A-Za-z0-9]+','-',$this->URLSegment);
			$segment = ereg_replace('-+','-',$segment);
			if(!$segment) {
				$segment = "page-$this->ID";
			}
			$this->URLSegment = $segment;
		}
		
		DataObject::set_context_obj($this);
		
		// Ensure URLSegment is unique
		$idFilter = ($this->ID)
			? " AND `SiteTree`.ID <> '$this->ID'" :
			'';

		$count = 1;
		while(DataObject::get_one("SiteTree", "URLSegment = '$this->URLSegment' $idFilter")) {
			$count++;
			$this->URLSegment = ereg_replace('-[0-9]+$','', $this->URLSegment) . "-$count";
		}

		DataObject::set_context_obj(null);
		
		// If the URLSegment has been changed, rewrite links
		if(isset($this->changed['URLSegment']) && $this->changed['URLSegment']) {
			if($this->hasMethod('BackLinkTracking')) {
				$links = $this->BackLinkTracking();
				if($links) {
					foreach($links as $link) {
						$link->rewriteLink($this->original['URLSegment'] . '/',
															 $this->URLSegment . '/');
						$link->write();
					}
				}
			}
		}


		// If priority is empty or invalid, set it to the default value
		if(!is_numeric($this->Priority) ||
			 (($this->Priority < 0) || ($this->Priority > 1)))
			$this->Priority = self::$defaults['Priority'];

		parent::onBeforeWrite();
	}
	
	


	/**
	 * Generate a URL segment based on the title provided.
	 * @param string $title Page title.
	 * @return string Generated url segment
	 */
	function generateURLSegment($title){
		$t = strtolower($title);
		$t = str_replace('&amp;','-and-',$t);
		$t = str_replace('&','-and-',$t);
		$t = ereg_replace('[^A-Za-z0-9]+','-',$t);
		$t = ereg_replace('-+','-',$t);
		if(!$t) {
			$t = "page-$this->ID";
		}
		return $t;
	}


	function makelinksunique() {
		$badURLs = "'" . implode("', '", DB::query("SELECT URLSegment, count(*) FROM SiteTree GROUP BY URLSegment HAVING count(*) > 1")->column()) . "'";
		$pages = DataObject::get("SiteTree", "URLSegment IN ($badURLs)");

		foreach($pages as $page) {
			echo "<li>$page->Title: ";
			$urlSegment = $page->URLSegment;
			$page->write();
			if($urlSegment != $page->URLSegment) {
				echo " changed $urlSegment -> $page->URLSegment";
			}
			else {
				echo " $urlSegment is already unique";
			}
			die();
		}
	}


	function makelinksuniquequick() {
		$badURLs = "'" . implode("', '", DB::query("SELECT URLSegment, count(*) FROM SiteTree GROUP BY URLSegment HAVING count(*) > 1")->column()) . "'";
		$pages = DB::query("SELECT *, SiteTree.ID FROM SiteTree LEFT JOIN Page ON Page.ID = SiteTree.ID WHERE  URLSegment IN ($badURLs)");

		foreach($pages as $page) {
			echo "<li>$page[Title]: ";
			$urlSegment = $page['URLSegment'];
			$newURLSegment = $urlSegment . '-' . $page['ID'];
			DB::query("UPDATE SiteTree SET URLSegment = '$newURLSegment' WHERE ID = $page[ID]");
			if($urlSegment != $newURLSegment) {
				echo " changed $urlSegment -> $newURLSegment";
			}
			else {
				echo " $urlSegment is already unique";
			}
		}
		echo "<p>done";
	}


	/**
	 * Replace a URL in html content with a new URL.
	 * @param string $old The old URL
	 * @param string $new The new URL
	 */
	function rewriteLink($old, $new) {
		$fields = $this->getCMSFields(null)->dataFields();
		foreach($fields as $field) {
			if(is_a($field, 'HtmlEditorField')) {
				$fieldName = $field->Name();
				$field->setValue($this->$fieldName);
				$field->rewriteLink($old, $new);
				$field->saveInto($this);
			}
		}
	}

	//------------------------------------------------------------------------------------//
	
	/**
	 * Holds callback functions to be called when getCMSFields() is called
	 *
	 * @var array
	 */
	static $cms_additions = array();


	/**
	 * Allows modules to extend the cms editing form for all pages in the site
	 *
	 * @param mixed $function the name of your function, either as a string,
	 *                        or in the form array('class','function)
	 */
	static function ExtendCMS($function)
	{
		self::$cms_additions[] = $function;
	}


	/**
	 * Returns a FieldSet with which to create the CMS editing form.
	 *
	 * You can override this in your child classes to add extra fields - first
	 * get the parent fields using parent::getCMSFields(), then use
	 * addFieldToTab() on the FieldSet.
	 *
	 * @return FieldSet The fields to be displayed in the CMS.
	 */
	function getCMSFields() {
		require_once("forms/Form.php");
		Requirements::javascript("cms/javascript/SitetreeAccess.js");

		// Backlink report
		if($this->hasMethod('BackLinkTracking')) {
			$links = $this->BackLinkTracking();

			if($links->exists()) {
				foreach($links as $link) {
					$backlinks[] = "<li><a class=\"cmsEditlink\" href=\"admin/show/$link->ID\">" .
						$link->Breadcrumbs(null,true) . "</a></li>";
				}
				$backlinks = "<div style=\"clear:left\">The following pages link to this page:<ul>" .
					implode("",$backlinks) . "</ul></div>";
			}
		}

		if(!isset($backlinks)) {
			$backlinks = "<p>This page hasn't been linked to from any pages.</p>";
		}


		// Status / message
		// Create a status message for multiple parents
		if($this->ID && is_numeric($this->ID)) {
			$linkedPages = DataObject::get("VirtualPage", "CopyContentFromID = $this->ID");
		}

		if(isset($linkedPages)) {
			foreach($linkedPages as $linkedPage) {
				$parentPage = $linkedPage->Parent;
				$parentPageTitle = $parentPage->Title;

				if($parentPage->ID) {
					$parentPageLinks[] = "<a class=\"cmsEditlink\" href=\"admin/show/$linkedPage->ID\">{$parentPage->Title}</a>";
				} else {
					$parentPageLinks[] = "<a class=\"cmsEditlink\" href=\"admin/show/$linkedPage->ID\">Site Content (Top Level)</a>";
				}
			}

			$lastParent = array_pop($parentPageLinks);
			$parentList = "'$lastParent'";

			if(count($parentPageLinks) > 0) {
				$parentList = "'" . implode("', '", $parentPageLinks) . "' and "
					. $parentList;
			}

			$statusMessage[] = "This content also appears on the virtual pages in the $parentList sections.";
		}

		if($this->HasBrokenLink || $this->HasBrokenFile) {
			$statusMessage[] = "This page has broken links.";
		}

		$message = "STATUS: $this->Status<br />";
		if(isset($statusMessage)) {
			$message .= "NOTE: " . implode("<br />", $statusMessage);
		}


		// Lay out the fields
		$fields = new FieldSet(
			new TabSet("Root",
				new TabSet("Content",
					new Tab("Main",
						new TextField("Title", "Page name"),
						/*new UniqueTextField("Title",
								"Title",
								"SiteTree",
								"Another page is using that name. Page names should be unique.",
								"Page Name"
						),*/
						new TextField("MenuTitle", "Navigation label"),
						new HtmlEditorField("Content","Content")
					),
					new Tab("Meta-data",
						new FieldGroup("URL",
							new LabelField("http://www.yoursite.com/"),
							//new TextField("URLSegment",""),
							new UniqueRestrictedTextField("URLSegment",
								"URLSegment",
								"SiteTree",
								"Another page is using that URL. URL must be unique for each page",
								"[^A-Za-z0-9-]+",
								"-",
								"URLs can only be made up of letters, digits and hyphens.",
								""
							),
							new LabelField("/")
						),
						new HeaderField("Search Engine Meta-tags"),
						new TextField("MetaTitle", "Title"),
						new TextareaField("MetaDescription", "Description"),
						new TextareaField("MetaKeywords", "Keywords"),
						new TogglePanel("Advanced Options...",array( 
							new TextareaField("ExtraMeta","Custom Meta Tags"), 
							new LiteralField("", "<p>Manually specify a Priority for this page: (valid values are from 0 to 1, a zero will remove this page from the index)</p>"), 
							new NumericField("Priority","Page Priority")), 
 							true 
						) 
					)
				),
				new Tab("Behaviour",
					new DropdownField("ClassName", "Page type", $this->getClassDropdown()),
					new CheckboxField("ShowInMenus", "Show in menus?"),
					new CheckboxField("ShowInSearch", "Show in search?"),
					/*, new TreeMultiselectField("MultipleParents", "Page appears within", "SiteTree")*/
					new CheckboxField("ProvideComments", "Allow comments on this page?"),
					new LiteralField("", "<p>Use this page as the 'home page' for the following domains: (separate multiple domains with commas)</p>"),
					new TextField("HomepageForDomain", "Domain(s)")
				),
				new TabSet("Reports",
					new Tab("BackLinks",
						new LiteralField("Backlinks", $backlinks)
					)
				),
				new Tab("Access",
					new HeaderField("Who can view this page on my site?", 2),
					new OptionsetField("Viewers", "",
														 array("Anyone" => "Anyone",
																	 "LoggedInUsers" => "Logged-in users",
																	 "OnlyTheseUsers" => "Only these people (choose from list)")),
					new DropdownField("ViewersGroup", "Group", Group::map()),
					new HeaderField("Who can edit this inside the CMS?", 2),
					new OptionsetField("Editors", "",
														 array("LoggedInUsers" => "Anyone who can log-in to the CMS",
																	 "OnlyTheseUsers" => "Only these people (choose from list)")),
					new DropdownField("EditorsGroup", "Group", Group::map())
				)
			),
			new NamedLabelField("Status", $message, "pageStatusMessage", true)
		);

		foreach(self::$cms_additions as $extension)
		{
			$fields = call_user_func($extension,$fields);
		}
		
		$this->extend('updateCMSFields', $fields);

		return $fields;
	}


	/**
	 * Get the actions available in the CMS for this page - eg Save, Publish.
	 *
	 * @return DataObjectSet The available actions for this page.
	 */
	function getCMSActions() {
		$actions = array();

		if($this->isPublished() && $this->canPublish()) {
			$actions[] = FormAction::create('unpublish', 'Unpublish', 'delete')
				->describe("Remove this page from the published site");
		}

		if($this->stagesDiffer('Stage', 'Live')) {

			if($this->isPublished() && $this->canEdit())	{
				$actions[] = FormAction::create('rollback', 'Cancel draft changes', 'delete')
					->describe("Delete your draft and revert to the currently published page");
			}
		}

		if($this->canPublish())
			$actions[] = new FormAction('publish', 'Save & Publish');

		return new DataObjectSet($actions);
	}


	/**
	 * Check if this page is new - that is, if it has yet to have been written
	 * to the database.
	 *
	 * @return boolean True if this page is new.
	 */
	function isNew() {
		/**
		 * This check was a problem for a self-hosted site, and may indicate a
		 * bug in the interpreter on their server, or a bug here
		 * Changing the condition from empty($this->ID) to
		 * !$this->ID && !$this->record['ID'] fixed this.
		 */
		if(empty($this->ID))
			return true;

		if(is_numeric($this->ID))
			return false;

		return stripos($this->ID, 'new') === 0;
	}


	/**
	 * Check if this page has been published.
	 *
	 * @return boolean True if this page has been published.
	 */
	function isPublished() {
		if($this->isNew())
			return false;

		return (DB::query("SELECT ID FROM `SiteTree_Live` WHERE ID = $this->ID")->value())
			? true
			: false;
	}


	/**
	 * Look for ghost parents
	 */
	function MultipleParents() {
		$parents = new GhostPage_ComponentSet($this->Parent);
		$parents->setOwner($this);
		$ghostPages = DataObject::get("GhostPage", "LinkedPageID = '$this->ID'");

		if($ghostPages) {
			foreach($ghostPages as $ghostPage) {
				// Ignore root ghost-pages
				if($p = $ghostPage->getParent())
					$parents->push($p);
			}
		}

		return $parents;
	}


	/**
	 * Get the class dropdown used in the CMS to change the class of a page.
	 * This returns the list of options in the drop as a Map from class name
	 * to text in dropdown.
	 *
	 * @return array
	 */
	function getClassDropdown() {
		$classes = ClassInfo::getValidSubClasses('SiteTree');
		array_shift($classes);

		foreach($classes as $class) {
			$instance = singleton($class);
			if((($instance instanceof HiddenClass) || !$instance->canCreate()) && ($class != $this->class)) continue;

			$addAction = $instance->uninherited('add_action', true);
			if(!$addAction) $addAction = "a $class";

			$result[$class] = ($class == $this->class)
				? "Currently $addAction"
				: "Change to $addAction";
		}

		return $result;
	}


	/**
	 * Returns an array of the class names of classes that are allowed
	 * to be children of this class.
	 *
	 * @return array
	 */
	function allowedChildren() {
		$candidates = $this->stat('allowed_children');
		if($candidates && $candidates != "none" && $candidates != "SiteTree_root") {
			foreach($candidates as $candidate) {
				if(substr($candidate,0,1) == '*') {
					$allowedChildren[] = substr($candidate,1);
				} else {
					$subclasses = ClassInfo::subclassesFor($candidate);
					foreach($subclasses as $subclass) {
						if($subclass != "SiteTree_root") $allowedChildren[] = $subclass;
					}
				}
			}
			return $allowedChildren;
		}
	}


	/**
	 * Returns the class name of the default class for children of this page.
	 *
	 * @return string
	 */
	function defaultChild() {
		$default = $this->stat('default_child');
		$allowed = $this->allowedChildren();
		if($allowed) {
			if(!$default || !in_array($default, $allowed))
				$default = reset($allowed);
			return $default;
		}
	}


	/**
	 * Returns the class name of the default class for the parent of this
	 * page.
	 *
	 * @return string
	 */
	function defaultParent() {
		return $this->stat('default_parent');
	}


	/**
	 * Function to clean up the currently loaded page after a reorganise has
	 * been called. It should return a piece of JavaScript to be executed on
	 * the client side, to clean up the results of the reorganise.
	 */
	function cmsCleanup_parentChanged() {
	}


	/**
	 * Get the title for use in menus for this page. If the MenuTitle
	 * field is set it returns that, else it returns the Title field.
	 *
	 * @return string
	 */
	function getMenuTitle(){
		if($value = $this->getField("MenuTitle")) {
			return $value;
		} else {
			return $this->getField("Title");
		}
	}


	/**
	 * Set the menu title for this page.
	 *
	 * @param string $value
	 */
	function setMenuTitle($value) {
		if($value == $this->getField("Title")) {
			$this->setField("MenuTitle", null);
		} else {
			$this->setField("MenuTitle", $value);
		}
	}

	/**
	 * TitleWithStatus will return the title in an <ins>, <del> or
	 * <span class=\"modified\"> tag depending on its publication status.
	 *
	 * @return string
	 */
	function TreeTitle() {
		// If somthing
		if(!$this->CheckedPublicationDifferences && $this->ID) {
			$stageVersion =
				DB::query("SELECT Version FROM SiteTree WHERE ID = $this->ID")->value();
			$liveVersion =
				DB::query("SELECT Version FROM SiteTree_Live WHERE ID = $this->ID")->value();

			if($stageVersion && !$liveVersion)
				$this->AddedToStage = true;
			else if(!$stageVersion && $liveVersion)
				$this->DeletedFromStage = true;
			else if($stageVersion != $liveVersion)
				$this->ModifiedOnStage = true;
		}

		$tag =
			($this->DeletedFromStage ? "del title=\"Removed from draft site\"" :
			($this->AddedToStage ? "ins title=\"Added to draft site\"" :
			($this->ModifiedOnStage ? "span title=\"Modified on draft site\" class=\"modified\"" : "")));

		if($tag) {
			return "<$tag>" . $this->Title . "</" . strtok($tag,' ') . ">";
		}	else {
			return $this->Title;
		}
	}


	/**
	 * Return the CSS classes to apply to this node in the CMS tree
	 *
	 * @param Controller $controller The controller object that the tree
	 *                               appears on
	 * @return string
	 */
	function CMSTreeClasses($controller) {
		$classes = $this->class;
		if($this->HasBrokenFile || $this->HasBrokenLink)
			$classes .= " BrokenLink";

		if(!$this->canAddChildren())
			$classes .= " nochildren";

		if(!$this->canDelete())
			$classes .= " nodelete";

		if($controller->isCurrentPage($this))
			$classes .= " current";

		$classes .= $this->markingClasses();

		return $classes;
	}

}
?>