<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains classes used to manage the navigation structures in Moodle
 * and was introduced as part of the changes occuring in Moodle 2.0
 *
 * @since     2.0
 * @package   block_navigation
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class slide_navigation extends global_navigation {

   /**
     * Initialises the navigation object.
     *
     * This causes the navigation object to look at the current state of the page
     * that it is associated with and then load the appropriate content.
     *
     * This should only occur the first time that the navigation structure is utilised
     * which will normally be either when the navbar is called to be displayed or
     * when a block makes use of it.
     *
     * @return bool
     */

    public function initialise() {
        global $CFG, $SITE, $USER, $DB;


        $coursenavview = get_config('slide_navigation', 'coursenavitems');


        // Check if it has alread been initialised
        if ($this->initialised || during_initial_install()) {
            return true;
        }
        $this->initialised = true;

        // Set up the five base root nodes. These are nodes where we will put our
        // content and are as follows:
        // site:        Navigation for the front page.
        // myprofile:     User profile information goes here.
        // mycourses:   The users courses get added here.
        // courses:     Additional courses are added here.
        // users:       Other users information loaded here.
        $this->rootnodes = array();
        if (get_home_page() == HOMEPAGE_SITE) {
            // The home element should be my moodle because the root element is the site
            if (isloggedin() && !isguestuser()) {  // Makes no sense if you aren't logged in
                $this->rootnodes['home'] = $this->add(get_string('myhome'), new moodle_url('/my/'), self::TYPE_SETTING, null, 'home');
            }
        } else {
            // The home element should be the site because the root node is my moodle
            $this->rootnodes['home'] = $this->add(get_string('sitehome'), new moodle_url('/'), self::TYPE_SETTING, null, 'home');
            if ($CFG->defaulthomepage == HOMEPAGE_MY) {
                // We need to stop automatic redirection
                $this->rootnodes['home']->action->param('redirect', '0');
            }
        }
        $this->rootnodes['site']      = $this->add_course($SITE);
        $this->rootnodes['myprofile'] = $this->add(get_string('myprofile'), null, self::TYPE_USER, null, 'myprofile');
        $this->rootnodes['mycourses'] = $this->add(get_string('mycourses'), null, self::TYPE_ROOTNODE, null, 'mycourses');
        $this->rootnodes['courses']   = $this->add(get_string('courses'), null, self::TYPE_ROOTNODE, null, 'courses');
        $this->rootnodes['users']     = $this->add(get_string('users'), null, self::TYPE_ROOTNODE, null, 'users');


        // Fetch all of the users courses.
       $limit = 20;
        if (!empty($CFG->navcourselimit)) {
            $limit = $CFG->navcourselimit;
        }
         ($coursenavview =='courses')? $courselimit = $limit : $courselimit = 0;

        $mycourses = enrol_get_my_courses(NULL, 'visible DESC,sortorder ASC',$courselimit);
        $showallcourses = (count($mycourses) == 0 || !empty($CFG->navshowallcourses));
       // $showcategories = ($showallcourses && $this->show_categories());
        $showcategories = true;
        $issite = ($this->page->course->id == SITEID);
        $ismycourse = (array_key_exists($this->page->course->id, $mycourses));

        // Check if any courses were returned.

        if (count($mycourses) > 0) {
            // Add all of the users courses to the navigation
            foreach ($mycourses as $course) {

                if ($coursenavview =='courses'){
                 $course->coursenode = $this->add_course($course, false, true);
                }else{
                $this->load_course($course);
                }

            }
        }

        if ($showallcourses) {
            // Load all courses
            $this->load_all_courses();
       }

        // We always load the frontpage course to ensure it is available without
        // JavaScript enabled.
        $frontpagecourse = $this->load_course($SITE);
        $this->add_front_page_course_essentials($frontpagecourse, $SITE);
        $this->load_course_sections($SITE, $frontpagecourse);

        $canviewcourseprofile = true;

        if (!$issite) {
            // Next load context specific content into the navigation
            switch ($this->page->context->contextlevel) {
                case CONTEXT_SYSTEM :
                    // This has already been loaded we just need to map the variable
                    $coursenode = $frontpagecourse;
                    $this->load_all_categories(null, $showcategories);
                    break;
                case CONTEXT_COURSECAT :
                    // This has already been loaded we just need to map the variable
                    $coursenode = $frontpagecourse;
                    $this->load_all_categories($this->page->context->instanceid, $showcategories);
                    break;
                case CONTEXT_BLOCK :
                case CONTEXT_COURSE :
                    // Load the course associated with the page into the navigation
                    $course = $this->page->course;
                    if ($showcategories && !$ismycourse) {
                        $this->load_all_categories($course->category, $showcategories);
                    }
                    $coursenode = $this->load_course($course);

                    // If the course wasn't added then don't try going any further.
                    if (!$coursenode) {
                        $canviewcourseprofile = false;
                        break;
                    }

                    // If the user is not enrolled then we only want to show the
                    // course node and not populate it.

                    // Not enrolled, can't view, and hasn't switched roles
                    if (!can_access_course($course)) {
                        // TODO: very ugly hack - do not force "parents" to enrol into course their child is enrolled in,
                        // this hack has been propagated from user/view.php to display the navigation node. (MDL-25805)
                        $isparent = false;
                        if ($this->useridtouseforparentchecks) {
                            if ($this->useridtouseforparentchecks != $USER->id) {
                                $usercontext   = get_context_instance(CONTEXT_USER, $this->useridtouseforparentchecks, MUST_EXIST);
                                if ($DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id))
                                    and has_capability('moodle/user:viewdetails', $usercontext)) {
                                    $isparent = true;
                                }
                            }
                        }

                        if (!$isparent) {
                            $coursenode->make_active();
                            $canviewcourseprofile = false;
                            break;
                        }
                    }
                    // Add the essentials such as reports etc...
                    $this->add_course_essentials($coursenode, $course);
                    if ($this->format_display_course_content($course->format)) {
                        // Load the course sections
                        $sections = $this->load_course_sections($course, $coursenode);
                    }
                    if (!$coursenode->contains_active_node() && !$coursenode->search_for_active_node()) {
                        $coursenode->make_active();
                    }
                    break;
                case CONTEXT_MODULE :
                    $course = $this->page->course;
                    $cm = $this->page->cm;

                    if ($showcategories && !$ismycourse) {
                        $this->load_all_categories($course->category, $showcategories);
                    }

                    // Load the course associated with the page into the navigation
                    $coursenode = $this->load_course($course);

                    // If the course wasn't added then don't try going any further.
                    if (!$coursenode) {
                        $canviewcourseprofile = false;
                        break;
                    }

                    // If the user is not enrolled then we only want to show the
                    // course node and not populate it.
                    if (!can_access_course($course)) {
                        $coursenode->make_active();
                        $canviewcourseprofile = false;
                        break;
                    }

                    $this->add_course_essentials($coursenode, $course);

                    // Get section number from $cm (if provided) - we need this
                    // before loading sections in order to tell it to load this section
                    // even if it would not normally display (=> it contains only
                    // a label, which we are now editing)
                    $sectionnum = isset($cm->sectionnum) ? $cm->sectionnum : 0;
                    if ($sectionnum) {
                        // This value has to be stored in a member variable because
                        // otherwise we would have to pass it through a public API
                        // to course formats and they would need to change their
                        // functions to pass it along again...
                        $this->includesectionnum = $sectionnum;
                    } else {
                        $this->includesectionnum = false;
                    }

                    // Load the course sections into the page
                    $sections = $this->load_course_sections($course, $coursenode);
                    if ($course->id != SITEID) {
                        // Find the section for the $CM associated with the page and collect
                        // its section number.
                        if ($sectionnum) {
                            $cm->sectionnumber = $sectionnum;
                        } else {
                            foreach ($sections as $section) {
                                if ($section->id == $cm->section) {
                                    $cm->sectionnumber = $section->section;
                                    break;
                                }
                            }
                        }

                        // Load all of the section activities for the section the cm belongs to.
                        if (isset($cm->sectionnumber) and !empty($sections[$cm->sectionnumber])) {
                            list($sectionarray, $activityarray) = $this->generate_sections_and_activities($course);
                            $activities = $this->load_section_activities($sections[$cm->sectionnumber]->sectionnode, $cm->sectionnumber, $activityarray);
                        } else {
                            $activities = array();
                            if ($activity = $this->load_stealth_activity($coursenode, get_fast_modinfo($course))) {
                                // "stealth" activity from unavailable section
                                $activities[$cm->id] = $activity;
                            }
                        }
                    } else {
                        $activities = array();
                        $activities[$cm->id] = $coursenode->get($cm->id, navigation_node::TYPE_ACTIVITY);
                    }
                    if (!empty($activities[$cm->id])) {
                        // Finally load the cm specific navigaton information
                        $this->load_activity($cm, $course, $activities[$cm->id]);
                        // Check if we have an active ndoe
                        if (!$activities[$cm->id]->contains_active_node() && !$activities[$cm->id]->search_for_active_node()) {
                            // And make the activity node active.
                            $activities[$cm->id]->make_active();
                        }
                    } else {
                        //TODO: something is wrong, what to do? (Skodak)
                    }
                    break;
                case CONTEXT_USER :
                    $course = $this->page->course;
                    if ($showcategories && !$ismycourse) {
                        $this->load_all_categories($course->category, $showcategories);
                    }
                    // Load the course associated with the user into the navigation
                    $coursenode = $this->load_course($course);

                    // If the course wasn't added then don't try going any further.
                    if (!$coursenode) {
                        $canviewcourseprofile = false;
                        break;
                    }

                    // If the user is not enrolled then we only want to show the
                    // course node and not populate it.
                    if (!can_access_course($course)) {
                        $coursenode->make_active();
                        $canviewcourseprofile = false;
                        break;
                    }
                    $this->add_course_essentials($coursenode, $course);
                    $sections = $this->load_course_sections($course, $coursenode);
                    break;
            }
        } else {
            // We need to check if the user is viewing a front page module.
            // If so then there is potentially more content to load yet for that
            // module.
            if ($this->page->context->contextlevel == CONTEXT_MODULE) {
                $activitynode = $this->rootnodes['site']->get($this->page->cm->id, navigation_node::TYPE_ACTIVITY);
                if ($activitynode) {
                    $this->load_activity($this->page->cm, $this->page->course, $activitynode);
                }
            }
        }
        $limit = 20;

        if ($showcategories && (is_siteadmin($USER->id))) {
            $categories = $this->find_all_of_type(self::TYPE_CATEGORY);
             foreach ($categories as &$category) {
                if ($category->children->count() >= $limit && (is_siteadmin($USER->id))) {
                    $url = new moodle_url('/course/category.php', array('id'=>$category->key));
                    $category->add(get_string('viewallcourses'), $url, self::TYPE_SETTING);
                }
            }
        } else if ($this->rootnodes['courses']->children->count() >= $limit) {
            $this->rootnodes['courses']->add(get_string('viewallcoursescategories'), new moodle_url('/course/index.php'), self::TYPE_SETTING);
        }








        // Load for the current user
        $this->load_for_user();
        if ($this->page->context->contextlevel >= CONTEXT_COURSE && $this->page->context->instanceid != SITEID && $canviewcourseprofile) {
            $this->load_for_user(null, true);
        }
        // Load each extending user into the navigation.
        foreach ($this->extendforuser as $user) {
            if ($user->id != $USER->id) {
                $this->load_for_user($user);
            }
        }

        // Give the local plugins a chance to include some navigation if they want.
        foreach (get_list_of_plugins('local') as $plugin) {
            if (!file_exists($CFG->dirroot.'/local/'.$plugin.'/lib.php')) {
                continue;
            }
            require_once($CFG->dirroot.'/local/'.$plugin.'/lib.php');
            $function = $plugin.'_extends_navigation';
            if (function_exists($function)) {
                $function($this);
            }
        }

        // Remove any empty root nodes
        foreach ($this->rootnodes as $node) {
            // Dont remove the home node
            if ($node->key !== 'home' && !$node->has_children()) {
                $node->remove();
            }
        }

        if (!$this->contains_active_node()) {
            $this->search_for_active_node();
        }

        // If the user is not logged in modify the navigation structure as detailed
        // in {@link http://docs.moodle.org/dev/Navigation_2.0_structure}
        if (!isloggedin()) {
            $activities = clone($this->rootnodes['site']->children);
            $this->rootnodes['site']->remove();
            $children = clone($this->children);
            $this->children = new navigation_node_collection();
            foreach ($activities as $child) {
                $this->children->add($child);
            }
            foreach ($children as $child) {
                $this->children->add($child);
            }
        }
        return true;
    }




    /**
     * Loads all categories (top level or if an id is specified for that category)
     *
     * @param int $categoryid The category id to load or null/0 to load all base level categories
     * @param bool $showbasecategories If set to true all base level categories will be loaded as well
     *        as the requested category and any parent categories.
     * @return void
     */
    protected function load_all_categories($categoryid = null, $showbasecategories = false) {
        global $DB;
        $coursenavview = get_config('slie_navigation', 'coursenavitems');
        // Check if this category has already been loaded
        if ($categoryid !== null && array_key_exists($categoryid, $this->addedcategories) && $this->addedcategories[$categoryid]->children->count() > 0) {
            return $this->addedcategories[$categoryid];
        }

        $coursestoload = array();
        if (empty($categoryid)) { // can be 0
            // We are going to load all of the first level categories (categories without parents)
            $categories = $DB->get_records('course_categories', array('parent'=>'0'), 'sortorder ASC, id ASC');
        } else if (array_key_exists($categoryid, $this->addedcategories)) {
            // The category itself has been loaded already so we just need to ensure its subcategories
            // have been loaded
            list($sql, $params) = $DB->get_in_or_equal(array_keys($this->addedcategories), SQL_PARAMS_NAMED, 'parent', false);
            if ($showbasecategories) {
                // We need to include categories with parent = 0 as well
                $sql = "SELECT *
                          FROM {course_categories} cc
                         WHERE (parent = :categoryid OR parent = 0) AND
                               parent {$sql}
                      ORDER BY depth DESC, sortorder ASC, id ASC";
            } else {
                $sql = "SELECT *
                          FROM {course_categories} cc
                         WHERE parent = :categoryid AND
                               parent {$sql}
                      ORDER BY depth DESC, sortorder ASC, id ASC";
            }
            $params['categoryid'] = $categoryid;
            $categories = $DB->get_records_sql($sql, $params);
            if (count($categories) == 0) {
                // There are no further categories that require loading.
                return;
            }
        } else {
            // This category hasn't been loaded yet so we need to fetch it, work out its category path
            // and load this category plus all its parents and subcategories
            $category = $DB->get_record('course_categories', array('id' => $categoryid), 'path', MUST_EXIST);
            $coursestoload = explode('/', trim($category->path, '/'));
            list($select, $params) = $DB->get_in_or_equal($coursestoload);
            $select = 'id '.$select.' OR parent '.$select;
            if ($showbasecategories) {
                $select .= ' OR parent = 0';
            }
            $params = array_merge($params, $params);
           $categories = $DB->get_records_select('course_categories', $select, $params, 'sortorder');

if ($coursenavview =='catandcourses'){
       //check whether user is enrolled on that course and whether the course is visible
         global $USER;
            if (isloggedin() && !is_siteadmin($USER->id)){

                 foreach ($categories as $cat){
                   //  if ($cat->parent!=0){
                 //retrieve all courses for that category

                  $params = array('category'=>$cat->id, 'visible'=>1);
                   $sql = "SELECT *
                          FROM {course} c
                          WHERE category = :category
                          AND visible = :visible";


                   //$course = $DB->get_records_sql($sql, array($cat->id, 1));
                 $course = $DB->get_records_sql($sql, $params);

                     $courses = array();
                     foreach ($course as $c){
                     $context = get_context_instance(CONTEXT_COURSE, $c->id);
                     $enrolled = is_enrolled($context, $USER->id);

                 // $visible_course = $DB->get_records('course',array('id'=>$c->id,'visible'=>1));
                     //   if ($enrolled && $visible_course){

                       //check path ( if 'enrolled' subcategory has parent, show parent' )
                       //dont delete category which has 'enrolled' subcategory

                     //   if ($cat->id == $last_element){}

                         if ($enrolled ){
                    $courses[] =   $c->id;
                         }
                     }

                     $path = explode('/', trim($cat->path, '/'));
                     $last_element = end($path);
                     $length = sizeof($path);

                    if ($cat->coursecount !=0 && $length!=1 && $cat->id == $last_element){

                         if (empty($courses)){
                         unset($categories[$cat->id]);
                        }
                    }
                 }
            }
         }

       }

        // Now we have an array of categories we need to add them to the navigation.
        while (!empty($categories)) {
            $category = reset($categories);
            if (array_key_exists($category->id, $this->addedcategories)) {
                // Do nothing
            } else if ($category->parent == '0') {
                $this->add_category($category, $this->rootnodes['courses']);
            } else if (array_key_exists($category->parent, $this->addedcategories)) {
                $this->add_category($category, $this->addedcategories[$category->parent]);
            } else {


            // This category isn't in the navigation and niether is it's parent (yet).
                // We need to go through the category path and add all of its components in order.
                $path = explode('/', trim($category->path, '/'));
                foreach ($path as $catid) {
                     if (!array_key_exists($catid, $this->addedcategories)) {
                        // This category isn't in the navigation yet so add it.
                        $subcategory = $categories[$catid];
                        if ($subcategory->parent == '0') {
                            // Yay we have a root category - this likely means we will now be able
                            // to add categories without problems.
                            $this->add_category($subcategory, $this->rootnodes['courses']);
                        } else if (array_key_exists($subcategory->parent, $this->addedcategories)) {
                            // The parent is in the category (as we'd expect) so add it now.
                            $this->add_category($subcategory, $this->addedcategories[$subcategory->parent]);
                            // Remove the category from the categories array.
                            unset($categories[$catid]);
                        } else {
                            // We should never ever arrive here - if we have then there is a bigger
                            // problem at hand.
                            throw new coding_exception('Category path order is incorrect and/or there are missing categories');
                        }
                    }
                }
            }
            // Remove the category from the categories array now that we know it has been added.
            unset($categories[$category->id]);
        }


        // Check if there are any categories to load.
       global $USER;
      if (count($coursestoload) > 0) {
            //don't display all courses if the user is not an admin
           if ($coursenavview =='catandcourses'){
            if (!(isloggedin()) || (is_siteadmin($USER->id))){
                $this->load_all_courses($coursestoload);
             }
           }else{
               $this->load_all_courses($coursestoload);
           }

        }
      }
  }

  /**
   * The global navigation tree block class
   *
   * Used to produce the global navigation block new to Moodle 2.0
   *
   * @package   block_navigation
   * @category  navigation
   * @copyright 2009 Sam Hemelryk
   * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
   */
class block_slide_navigation extends block_base {

    /** @var int This allows for multiple navigation trees */
    public static $navcount;
    /** @var string The name of the block */
    public $blockname = null;
    /** @var bool A switch to indicate whether content has been generated or not. */
    protected $contentgenerated = false;
    /** @var bool|null variable for checking if the block is docked*/
    protected $docked = null;

    /** @var int Trim characters from the right */
    const TRIM_RIGHT = 1;
    /** @var int Trim characters from the left */
    const TRIM_LEFT = 2;
    /** @var int Trim characters from the center */
    const TRIM_CENTER = 3;

    /**
     * Set the initial properties for the block
     */
    function init() {
        global $CFG;
        $this->blockname = get_class($this);
        $this->title = get_string('pluginname', $this->blockname);
    }

    /**
     * All multiple instances of this block
     * @return bool Returns false
     */
    function instance_allow_multiple() {
        return false;
    }

    /**
     * Set the applicable formats for this block to all
     * @return array
     */
    function applicable_formats() {
        return array('all' => true);
    }

    /**
     * Allow the user to configure a block instance
     * @return bool Returns true
     */
    function instance_allow_config() {
        return true;
    }

    /**
     * The navigation block cannot be hidden by default as it is integral to
     * the navigation of Moodle.
     *
     * @return false
     */
    function  instance_can_be_hidden() {
        return false;
    }

    /**
     * Find out if an instance can be docked.
     *
     * @return bool true or false depending on whether the instance can be docked or not.
     */
    function instance_can_be_docked() {
        return (parent::instance_can_be_docked() && (empty($this->config->enabledock) || $this->config->enabledock=='yes'));
    }

    /**
     * Gets Javascript that may be required for navigation
     */
    function get_required_javascript() {
        global $CFG;
        user_preference_allow_ajax_update('docked_block_instance_'.$this->instance->id, PARAM_INT);
        $this->page->requires->js_module('core_dock');

        $expansionlimit = 0;
        if (!empty($this->config->expansionlimit)) {
            $expansionlimit = $this->config->expansionlimit;
        }
        $arguments = array(
            'id'             => $this->instance->id,
            'instance'       => $this->instance->id,
            'candock'        => $this->instance_can_be_docked(),
            'expansionlimit' => $expansionlimit
        );
        $this->page->requires->string_for_js('viewallcourses', 'moodle');
       // $this->page->requires->yui_module(array('core_dock', 'moodle-block_navigation-navigation'), 'M.block_navigation.init_add_tree', array($arguments));
    }

    /**
     * Gets the content for this block by grabbing it from $this->page
     *
     * @return object $this->content
     */
    function get_content() {
        global $CFG, $OUTPUT;
        // First check if we have already generated, don't waste cycles
        if ($this->contentgenerated === true) {
            return $this->content;
        }
        // JS for navigation moved to the standard theme, the code will probably have to depend on the actual page structure
        // $this->page->requires->js('/lib/javascript-navigation.js');
        // Navcount is used to allow us to have multiple trees although I dont' know why
        // you would want two trees the same

        block_slide_navigation::$navcount++;

        // Check if this block has been docked
        if ($this->docked === null) {
            $this->docked = get_user_preferences('nav_in_tab_panel_globalnav'.block_slide_navigation::$navcount, 0);
        }

        // Check if there is a param to change the docked state
        if ($this->docked && optional_param('undock', null, PARAM_INT)==$this->instance->id) {
            unset_user_preference('nav_in_tab_panel_globalnav'.block_slide_navigation::$navcount);
            $url = $this->page->url;
            $url->remove_params(array('undock'));
            redirect($url);
        } else if (!$this->docked && optional_param('dock', null, PARAM_INT)==$this->instance->id) {
            set_user_preferences(array('nav_in_tab_panel_globalnav'.block_slide_navigation::$navcount=>1));
            $url = $this->page->url;
            $url->remove_params(array('dock'));
            redirect($url);
        }

        $trimmode = self::TRIM_LEFT;
        $trimlength = 50;

        if (!empty($this->config->trimmode)) {
            $trimmode = (int)$this->config->trimmode;
        }

        if (!empty($this->config->trimlength)) {
            $trimlength = (int)$this->config->trimlength;
        }

        // Initialise (only actually happens if it hasn't already been done yet
        $navigation = new slide_navigation($this->page);
        $navigation->initialise();

        $expansionlimit = null;
        if (!empty($this->config->expansionlimit)) {
            $expansionlimit = $this->config->expansionlimit;
            $navigation->set_expansion_limit($this->config->expansionlimit);
        }
        $this->trim($navigation, $trimmode, $trimlength, ceil($trimlength/2));

        // Get the expandable items so we can pass them to JS
        $expandable = array();
        $navigation->find_expandable($expandable);
        if ($expansionlimit) {
            foreach ($expandable as $key=>$node) {
                if ($node['type'] > $expansionlimit && !($expansionlimit == navigation_node::TYPE_COURSE && $node['type'] == $expansionlimit && $node['branchid'] == SITEID)) {
                    unset($expandable[$key]);
                }
            }
        }

        $this->page->requires->data_for_js('navtreeexpansions'.$this->instance->id, $expandable);

        $options = array();
        $options['linkcategories'] = (!empty($this->config->linkcategories) && $this->config->linkcategories == 'yes');

        // Grab the items to display
        $renderer = $this->page->get_renderer('block_slide_navigation');
        $this->content = new stdClass();
        $this->content->text = $renderer->navigation_tree($navigation, $expansionlimit, $options);

        // Set content generated to true so that we know it has been done
        $this->contentgenerated = true;

        return $this->content;
    }

    /**
     * Returns the attributes to set for this block
     *
     * This function returns an array of HTML attributes for this block including
     * the defaults.
     * {@link block_tree::html_attributes()} is used to get the default arguments
     * and then we check whether the user has enabled hover expansion and add the
     * appropriate hover class if it has.
     *
     * @return array An array of HTML attributes
     */
    public function html_attributes() {
        $attributes = parent::html_attributes();
        if (!empty($this->config->enablehoverexpansion) && $this->config->enablehoverexpansion == 'yes') {
            $attributes['class'] .= ' block_js_expansion';
        }
        return $attributes;
    }

    /**
     * Trims the text and shorttext properties of this node and optionally
     * all of its children.
     *
     * @param navigation_node $node
     * @param int $mode One of navigation_node::TRIM_*
     * @param int $long The length to trim text to
     * @param int $short The length to trim shorttext to
     * @param bool $recurse Recurse all children
     */
    public function trim(navigation_node $node, $mode=1, $long=50, $short=25, $recurse=true) {
        switch ($mode) {
            case self::TRIM_RIGHT :
                if (textlib::strlen($node->text)>($long+3)) {
                    // Truncate the text to $long characters
                    $node->text = $this->trim_right($node->text, $long);
                }
                if (is_string($node->shorttext) && textlib::strlen($node->shorttext)>($short+3)) {
                    // Truncate the shorttext
                    $node->shorttext = $this->trim_right($node->shorttext, $short);
                }
                break;
            case self::TRIM_LEFT :
                if (textlib::strlen($node->text)>($long+3)) {
                    // Truncate the text to $long characters
                    $node->text = $this->trim_left($node->text, $long);
                }
                if (is_string($node->shorttext) && textlib::strlen($node->shorttext)>($short+3)) {
                    // Truncate the shorttext
                    $node->shorttext = $this->trim_left($node->shorttext, $short);
                }
                break;
            case self::TRIM_CENTER :
                if (textlib::strlen($node->text)>($long+3)) {
                    // Truncate the text to $long characters
                    $node->text = $this->trim_center($node->text, $long);
                }
                if (is_string($node->shorttext) && textlib::strlen($node->shorttext)>($short+3)) {
                    // Truncate the shorttext
                    $node->shorttext = $this->trim_center($node->shorttext, $short);
                }
                break;
        }
        if ($recurse && $node->children->count()) {
            foreach ($node->children as &$child) {
                $this->trim($child, $mode, $long, $short, true);
            }
        }
    }
    /**
     * Truncate a string from the left
     * @param string $string The string to truncate
     * @param int $length The length to truncate to
     * @return string The truncated string
     */
    protected function trim_left($string, $length) {
        return '...'.textlib::substr($string, textlib::strlen($string)-$length, $length);
    }
    /**
     * Truncate a string from the right
     * @param string $string The string to truncate
     * @param int $length The length to truncate to
     * @return string The truncated string
     */
    protected function trim_right($string, $length) {
        return textlib::substr($string, 0, $length).'...';
    }
    /**
     * Truncate a string in the center
     * @param string $string The string to truncate
     * @param int $length The length to truncate to
     * @return string The truncated string
     */
    protected function trim_center($string, $length) {
        $trimlength = ceil($length/2);
        $start = textlib::substr($string, 0, $trimlength);
        $end = textlib::substr($string, textlib::strlen($string)-$trimlength);
        $string = $start.'...'.$end;
        return $string;
    }
}
