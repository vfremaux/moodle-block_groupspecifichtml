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
 * @package   block_group_network
 * @category  blocks
 * @author    Valery Fremaux (valery.fremaux@gmail.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
defined('MOODLE_INTERNAL') || die();

class block_groupspecifichtml extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_groupspecifichtml');
    }

    public function applicable_formats() {
        return array('all' => false, 'course' => true, 'admin' => false, 'my' => false);
    }

    public function specialization() {
        $defaulttitle = format_string(get_string('newhtmlblock', 'block_groupspecifichtml'));
        $this->title = isset($this->config->title) ? format_string($this->config->title) : $defaulttitle;
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function get_content() {
        global $COURSE, $CFG, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $filteropt = new stdClass;
        $filteropt->overflowdiv = true;
        if ($this->content_is_trusted()) {
            // Fancy html allowed only on course, category and system blocks.
            $filteropt->noclean = true;
        }

        $this->content = new stdClass;

        $this->content->text = '';

        $usegroupmenu = true;
        if (has_capability('moodle/site:accessallgroups', context_course::instance($COURSE->id))) {
            $usegroupmenu = true;
        } else {
            $usergroupings = groups_get_user_groups($COURSE->id, $USER->id);
            if (count($usergroupings) == 1) {
                $usergroups = array_pop($usergroupings);
                if (count($usergroups) == 1) {
                    $usegroupmenu = false;
                    $uniquegroup = array_pop($usergroups);
                }
            }
        }

        $coursegroups = groups_get_all_groups($COURSE->id);
        if (($coursegroups) && $usegroupmenu) {
            $menuurl = new moodle_url('/course/view.php', array('id' => $COURSE->id));
            $this->content->text .= groups_print_course_menu($COURSE, $menuurl, true);
        }

        $gid = 0 + groups_get_course_group($COURSE, $USER->id);
        if (@$uniquegroup && !$gid) {
            $gid = $uniquegroup;
        }

        $textkeys = array('text_all' => 99999999);
        if (!empty($coursegroups)) {
            $textkeys['text_'.$gid] = $gid;
        }

        foreach ($textkeys as $tk => $tkgid) {
            if (isset($this->config->$tk)) {
                $format = FORMAT_HTML;
                // Check to see if the format has been properly set on the config.
                $formatkey = str_replace('text_', 'format_', $tk);
                if (isset($this->config->$formatkey)) {
                    $format = $this->config->$formatkey;
                }
                // Rewrite url.
                $this->config->$tk = file_rewrite_pluginfile_urls($this->config->$tk, 'pluginfile.php', $this->context->id,
                                                                  'block_groupspecifichtml', 'content', $tkgid, null);
                /*
                 * Default to FORMAT_HTML which is what will have been used before the
                 * editor was properly implemented for the block.
                 */
                $this->content->text .= format_text($this->config->$tk, $format, $filteropt);
            } else {
                $this->content->text .= '';
            }
        }

        $this->content->footer = '';

        unset($filteropt); // Memory footprint.

        if (empty($this->content->text)) {
            $this->content->text = '&nbsp;';
        }

        return $this->content;
    }

    /**
     * Serialize and store config data
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB, $COURSE;

        $config = clone($data);
        // Move embedded files into a proper filearea and adjust HTML links to match.
        $config->text_all = file_save_draft_area_files($data->text_all['itemid'], $this->context->id, 'block_groupspecifichtml',
                                                       'content', 99999999, array('subdirs' => true), $data->text_all['text']);
        $config->format_all = $data->text_all['format'];

        $config->text_0 = file_save_draft_area_files($data->text_0['itemid'], $this->context->id, 'block_groupspecifichtml',
                                                     'content', 0, array('subdirs' => true), $data->text_0['text']);
        $config->format_0 = $data->text_0['format'];

        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($groups)) {
            foreach ($groups as $g) {
                $textkey = 'text_'.$g->id;
                $formatkey = 'format_'.$g->id;
                $config->{$textkey} = file_save_draft_area_files($data->{$textkey}['itemid'], $this->context->id,
                                                                 'block_groupspecifichtml', 'content', $g->id,
                                                                 array('subdirs' => true), $data->{$textkey}['text']);
                $config->{$formatkey} = $data->{$textkey}['format'];
            }
        }

        parent::instance_config_save($config, $nolongerused);
    }

    public function instance_delete() {
        global $DB;

        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_groupspecifichtml');
        return true;
    }

    public function content_is_trusted() {
        global $SCRIPT;

        if (!$context = context::instance_by_id($this->instance->parentcontextid)) {
            return false;
        }

        // Find out if this block is on the profile page.
        if ($context->contextlevel == CONTEXT_USER) {
            if ($SCRIPT === '/my/index.php') {
                /*
                 * This is exception - page is completely private, nobody else may see content
                 * there that is why we allow JS here.
                 */
                return true;
            } else {
                // No JS on public personal pages, it would be a big security issue.
                return false;
            }
        }

        return true;
    }

    /**
     * The block should only be dockable when the title of the block is not empty
     * and when parent allows docking.
     *
     * @return bool
     */
    public function instance_can_be_docked() {
        return (!empty($this->config->title) && parent::instance_can_be_docked());
    }

    /*
     * Hide the title bar when none set..
     */
    public function hide_header() {
        return empty($this->config->title);
    }
}
