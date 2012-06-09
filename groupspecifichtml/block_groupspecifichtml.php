<?php //$Id: block_groupspecifichtml.php,v 1.1 2012-03-22 13:43:39 vf Exp $

class block_groupspecifichtml extends block_base {

    function init() {
        $this->title = get_string('blockname', 'block_groupspecifichtml');
        $this->version = 2012032000;
    }

    function applicable_formats() {
        return array('all' => true, 'admin' => false);
    }

    function specialization() {
        $this->title = isset($this->config->title) ? format_string($this->config->title) : format_string(get_string('newhtmlblock', 'block_groupspecifichtml'));
    }

    function instance_allow_multiple() {
        return true;
    }

    function get_content() {
    	global $COURSE, $CFG, $USER;
    	
        if ($this->content !== NULL) {
            return $this->content;
        }
                
        if (!empty($this->instance->pinned) or $this->instance->pagetype === 'course-view') {
            // fancy html allowed only on course page and in pinned blocks for security reasons
            $filteropt = new stdClass;
            $filteropt->noclean = true;
        } else {
            $filteropt = null;
        }
        
        $this->content = new stdClass;

        $this->content->text = '';

		$usegroupmenu = true;
		if (!has_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_COURSE, $COURSE->id))){
	        $usergroupings = groups_get_user_groups($COURSE->id, $USER->id);
	        if (count($usergroupings) == 1){
	        	$usergroups = next($usergroupings);
	        	if (count($usergroups) == 1){
	        		$usegroupmenu = false;
	        	}
	        }
	    }

        if ($groups = groups_get_all_groups($COURSE->id) && $usegroupmenu){
        	$this->content->text .= groups_print_course_menu($COURSE, $CFG->wwwroot.'/course/view.php?id='.$COURSE->id, true);
        }

        $gid = 0 + groups_get_course_group($COURSE);

        $textkey = "text_all";
        $this->content->text .= !empty($this->config->$textkey) ? format_text($this->config->$textkey, FORMAT_HTML, $filteropt) : '';
        $textkey = "text_$gid";
        $this->content->text .= isset($this->config->$textkey) ? format_text($this->config->$textkey, FORMAT_HTML, $filteropt) : '';
        $this->content->footer = '';

        unset($filteropt); // memory footprint
        
        if (empty($this->content->text)) $this->content->text = '&nbsp;';

        return $this->content;
    }

    /**
     * Will be called before an instance of this block is backed up, so that any links in
     * any links in any HTML fields on config can be encoded.
     * @return string
     */
    function get_backup_encoded_config() {
        /// Prevent clone for non configured block instance. Delegate to parent as fallback.
        if (empty($this->config)) {
            return parent::get_backup_encoded_config();
        }
        $data = clone($this->config);
        foreach(array_keys($data->textids) as $gid){
        	$textkey = "text_$gid";
	        $data->$textkey = backup_encode_absolute_links($data->text[$gid]);
	    }
        return base64_encode(serialize($data));
    }

    /**
     * This function makes all the necessary calls to {@link restore_decode_content_links_worker()}
     * function in order to decode contents of this block from the backup 
     * format to destination site/course in order to mantain inter-activities 
     * working in the backup/restore process. 
     * 
     * This is called from {@link restore_decode_content_links()} function in the restore process.
     *
     * NOTE: There is no block instance when this method is called.
     *
     * @param object $restore Standard restore object
     * @return boolean
     **/
    function decode_content_links_caller($restore) {
        global $CFG;

        if ($restored_blocks = get_records_select("backup_ids","table_name = 'block_instance' AND backup_code = $restore->backup_unique_code AND new_id > 0", "", "new_id")) {
            $restored_blocks = implode(',', array_keys($restored_blocks));
            $sql = "SELECT bi.*
                      FROM {$CFG->prefix}block_instance bi
                           JOIN {$CFG->prefix}block b ON b.id = bi.blockid
                     WHERE b.name = 'groupspecifichtml' AND bi.id IN ($restored_blocks)"; 

            if ($instances = get_records_sql($sql)) {
                foreach ($instances as $instance) {
                    $blockobject = block_instance('groupspecifichtml', $instance);
                    foreach(array_keys($blockobject->config->textids) as $gid){
                    	$textkey = "text_$gid";
	                    $blockobject->config->$textkey = restore_decode_absolute_links($blockobject->config->$textkey);
	                    $blockobject->config->$textkey = restore_decode_content_links_worker($blockobject->config->$textkey, $restore);
	                }
                    $blockobject->instance_config_commit($blockobject->pinned);
                }
            }
        }

        return true;
    }

    /*
     * Hide the title bar when none set..
     */
    function hide_header(){
        return empty($this->config->title);
    }

	function get_group_content($gid, $grouponly = false){

		$text = '';

        if (!empty($this->instance->pinned) or $this->instance->pagetype === 'course-view') {
            // fancy html allowed only on course page and in pinned blocks for security reasons
            $filteropt = new stdClass;
            $filteropt->noclean = true;
        } else {
            $filteropt = null;
        }

		if (!$grouponly){
	        $textkey = "text_all";
	        $text .= !empty($this->config->$textkey) ? format_text($this->config->$textkey, FORMAT_HTML, $filteropt) : '';
	    }
        $textkey = "text_$gid";
        $text .= isset($this->config->$textkey) ? format_text($this->config->$textkey, FORMAT_HTML, $filteropt) : '';
        
        return $text;
	}

}
?>
