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
 * @package     block_groupspecifichtml
 * @category    blocks
 * @subpackage  backup-moodle2
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Specialised restore task for the groupspecifichtml block
 * (has own DB structures to backup)
 *
 * TODO: Finish phpdocs
 */
class restore_groupspecifichtml_block_task extends restore_block_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
    }

    public function get_fileareas() {
        return array(); // No associated fileareas.
    }

    public function get_configdata_encoded_attributes() {
        return array(); // We need to encode some attrs in configdata.
    }

    static public function define_decode_contents() {
        return array();
    }

    static public function define_decode_rules() {
        return array();
    }

    /**
     * Return the new id of a mapping for the given itemname
     *
     * @param string $itemname the type of item
     * @param int $oldid the item ID from the backup
     * @param mixed $ifnotfound what to return if $oldid wasnt found. Defaults to false
     */
    public function get_mappingid($itemname, $oldid, $ifnotfound = false) {
        $mapping = $this->get_mapping($itemname, $oldid);
        return $mapping ? $mapping->newitemid : $ifnotfound;
    }

    /**
     * Return the complete mapping from the given itemname, itemid
     */
    public function get_mapping($itemname, $oldid) {
        return restore_dbops::get_backup_ids_record($this->plan->get_restoreid(), $itemname, $oldid);
    }

    /**
     * After restoring a full block data we reopen config data and remap the fields keys
     * that address dynamic mapping to groups.
     */
    public function after_restore() {
        global $DB;

        $courseid = $this->get_courseid();
        $blockid = $this->get_blockid();

        $configdata = $DB->get_field('block_instances', 'configdata', array('id' => $blockid));
        $data = unserialize(base64_decode($configdata));

        $newdata = new StdClass(); // We need make a new object to avoid group id collisions.

        $newids = array();
        foreach ($data as $key => $info) {
            if (preg_match('/text_(\\d+)/', $key, $matches)) {
                if ($gid > 0) {
                    $gid = $matches[1];
                    $newgid = $this->get_mappingid('groups', $gid);
                    $newkey = 'text_'.$newgid;
                    $newdata->$newkey = $info;
                    $newids = $newgid;
                }
            } else {
                // Do not change.
                $newdata->$key = $info;
            }
        }

        $newdata->textids = implode(',', $newids);

        $configdata = base64_encode(serialize($newdata));
        $DB->set_field('block_instances', 'configdata', $configdata, array('id' => $blockid));
    }
}
