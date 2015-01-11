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

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_editablecontenthtml'));
        $mform->setType('config_title', PARAM_MULTILANG);

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $this->block->context);
        $mform->addElement('editor', 'config_text_all', get_string('configcontentforall', 'block_groupspecifichtml'), null, $editoroptions);
        $mform->setType('config_text_all', PARAM_RAW); // XSS is prevented when printing the block contents and serving files

        $mform->addElement('editor', 'config_text_0', get_string('configcontentforany', 'block_groupspecifichtml'), null, $editoroptions);
        $mform->setType('config_text_0', PARAM_RAW); // XSS is prevented when printing the block contents and serving files

        $groups = groups_get_all_groups($COURSE->id);
        $gids = array();
        if (!empty($groups)) {
            foreach($groups as $g) {
                $mform->addElement('editor', 'config_text_'.$g->id, get_string('configcontent', 'block_groupspecifichtml', $g->name), null, $editoroptions);
                $mform->setType('config_text_'.$g->id, PARAM_RAW); // XSS is prevented when printing the block contents and serving files
                $gids[] = $g->id;
            }
        }

        $mform->addElement('hidden', 'config_textids');
        $mform->setType('config_textids', PARAM_TEXT);
        $mform->setDefault('config_textids', implode(',', $gids));

    }

    function set_data($defaults, &$files = null) {
        global $COURSE;

        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($this->block->config) && is_object($this->block->config)) {

            // Draft file handling for all.
            $text_all = $this->block->config->text_all;
            $draftid_editor = file_get_submitted_draft_itemid('config_text_all');
            if (empty($text_all)) {
                $currenttext = '';
            } else {
                $currenttext = $text_all;
            }
            $defaults->config_text_all['text'] = file_prepare_draft_area($draftid_editor, $this->block->context->id, 'block_groupspecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
            $defaults->config_text_all['itemid'] = $draftid_editor;
            $defaults->config_text_all['format'] = @$this->block->config->format;

            // Draft file handling for any.
            $text_0 = $this->block->config->text_0;
            $draftid_editor = file_get_submitted_draft_itemid('config_text_0');
            if (empty($text_0)) {
                $currenttext = '';
            } else {
                $currenttext = $text_0;
            }
            $defaults->config_text_0['text'] = file_prepare_draft_area($draftid_editor, $this->block->context->id, 'block_groupspecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
            $defaults->config_text_0['itemid'] = $draftid_editor;
            $defaults->config_text_0['format'] = @$this->block->config->format;

            if (!empty($groups)) {
                foreach ($groups as $g) {
                    // Draft file handling for each.
                    $textvar = 'text_'.$g->id;
                    $configtextvar = 'config_text_'.$g->id;
                    $$textvar = $this->block->config->$textvar;
                    $draftid_editor = file_get_submitted_draft_itemid('config_text_'.$g->id);
                    if (empty($$textvar)) {
                        $currenttext = '';
                    } else {
                        $currenttext = $$textvar;
                    }
                    $defaults->{$configtextvar}['text'] = file_prepare_draft_area($draftid_editor, $this->block->context->id, 'block_groupspecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
                    $defaults->{$configtextvar}['itemid'] = $draftid_editor;
                    $defaults->{$configtextvar}['format'] = @$this->block->config->format;
                }
            }
        } else {
            $text_all = '';
            $text_0 = '';
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
        $this->block->config->text_all = $text_all;
        $this->block->config->text_0 = $text_0;
        if (!empty($groups)) {
            foreach ($groups as $g) {
                $textvar = 'text_'.$g->id;
                $this->block->config->{$textvar} = $$textvar;
            }
        }

        if (isset($title)) {
            // Reset the preserved title
            $this->block->config->title = $title;
        }

    }
}
