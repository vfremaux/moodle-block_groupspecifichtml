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

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing HTML block instances.
 *
 * @package   block_groupspecifichtml
 * @copyright 2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version   Moodle 2.x
 */

class block_groupspecifichtml_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $COURSE;

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_groupspecifichtml'));
        $mform->setType('config_title', PARAM_MULTILANG);

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $this->block->context);
        $label = get_string('configcontentforall', 'block_groupspecifichtml');
        $mform->addElement('editor', 'config_text_all', $label, null, $editoroptions);
        $mform->setType('config_text_all', PARAM_RAW); // XSS is prevented when printing the block contents and serving files.

        $label = get_string('configcontentforany', 'block_groupspecifichtml');
        $mform->addElement('editor', 'config_text_0', $label, null, $editoroptions);
        $mform->setType('config_text_0', PARAM_RAW); // XSS is prevented when printing the block contents and serving files.

        $groups = groups_get_all_groups($COURSE->id);
        $gids = array();
        if (!empty($groups)) {
            foreach ($groups as $g) {
                $label = get_string('configcontent', 'block_groupspecifichtml', $g->name);
                $mform->addElement('editor', 'config_text_'.$g->id, $label, null, $editoroptions);
                $mform->setType('config_text_'.$g->id, PARAM_RAW); // XSS is prevented when printing the block contents and serving files.
                $gids[] = $g->id;
            }
        }

        $mform->addElement('hidden', 'config_textids');
        $mform->setType('config_textids', PARAM_TEXT);
        $mform->setDefault('config_textids', implode(',', $gids));

    }

    public function set_data($defaults, &$files = null) {
        global $COURSE;

        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($this->block->config) && is_object($this->block->config)) {

            // Draft file handling for all.
            $textall = $this->block->config->text_all;
            $draftideditor = file_get_submitted_draft_itemid('config_text_all');
            if (empty($textall)) {
                $currenttext = '';
            } else {
                $currenttext = $textall;
            }
            $defaults->config_text_all['text'] = file_prepare_draft_area($draftideditor, $this->block->context->id,
                    'block_groupspecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
            $defaults->config_text_all['itemid'] = $draftideditor;
            $defaults->config_text_all['format'] = @$this->block->config->format;

            // Draft file handling for any.
            $text0 = $this->block->config->text_0;
            $draftideditor = file_get_submitted_draft_itemid('config_text_0');
            if (empty($text0)) {
                $currenttext = '';
            } else {
                $currenttext = $text0;
            }
            $defaults->config_text_0['text'] = file_prepare_draft_area($draftideditor, $this->block->context->id,
                    'block_groupspecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
            $defaults->config_text_0['itemid'] = $draftideditor;
            $defaults->config_text_0['format'] = @$this->block->config->format;

            if (!empty($groups)) {
                foreach ($groups as $g) {
                    // Draft file handling for each.
                    $textvar = 'text_'.$g->id;
                    $configtextvar = 'config_text_'.$g->id;
                    $$textvar = $this->block->config->$textvar;
                    $draftideditor = file_get_submitted_draft_itemid('config_text_'.$g->id);
                    if (empty($$textvar)) {
                        $currenttext = '';
                    } else {
                        $currenttext = $$textvar;
                    }
                    $defaults->{$configtextvar}['text'] = file_prepare_draft_area($draftideditor, 
                            $this->block->context->id, 'block_groupspecifichtml', 'content',
                                    0, array('subdirs' => true), $currenttext);
                    $defaults->{$configtextvar}['itemid'] = $draftideditor;
                    $defaults->{$configtextvar}['format'] = @$this->block->config->format;
                }
            }
        } else {
            $textall = '';
            $text0 = '';
            if (!empty($groups)) {
                foreach ($groups as $g) {
                    $textvar = 'text_'.$g->id;
                    $$textvar = '';
                }
            }
        }

        if (!$this->block->user_can_edit() && !empty($this->block->config->title)) {
            // If a title has been set but the user cannot edit it format it nicely.
            $title = $this->block->config->title;
            $defaults->config_title = format_string($title, true, $this->page->context);
            // Remove the title from the config so that parent::set_data doesn't set it.
            unset($this->block->config->title);
        }

        // Have to delete text here, otherwise parent::set_data will empty content of editor.
        unset($this->block->config->text_all);
        unset($this->block->config->text_0);
        if (!empty($groups)) {
            foreach ($groups as $g) {
                $textvar = 'text_'.$g->id;
                unset($this->block->config->{$textvar});
            }
        }
        parent::set_data($defaults);

        // restore $text
        if (!isset($this->block->config)) {
            $this->block->config = new StdClass();
        }
        $this->block->config->text_all = $textall;
        $this->block->config->text_0 = $text0;
        if (!empty($groups)) {
            foreach ($groups as $g) {
                $textvar = 'text_'.$g->id;
                $this->block->config->{$textvar} = $$textvar;
            }
        }

        if (isset($title)) {
            // Reset the preserved title.
            $this->block->config->title = $title;
        }

    }
}
