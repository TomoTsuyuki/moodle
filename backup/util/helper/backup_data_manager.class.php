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
 * MUC helper for backup methods
 *
 * @package    core
 * @subpackage backup-helper
 * @copyright  2021 Catalyst-IT
 * @author     Tomo Tsuyuki
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class backup_data_manager
 */
class backup_data_manager {

    /**
     * @var array Store array of cache_store if the cache of $itemname is already made.
     */
    private static $stores = [];

    /** @var stdClass Cached data. */
    private $data;

    public function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Magic method getter, redirects to read only values.
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'info':
                if (is_null($this->data->infodecoded)) {
                    // Load info.
                    $data = $this->data;
                    try {
                        $dbrec = self::get_backup_db_record($data->restoreid, $data->itemname, $data->itemid);
                        $this->data->info = backup_controller_dbops::decode_backup_temp_info($dbrec->info);
                    } catch (dml_exception $e) {
                        $this->data->info = null;
                    }
                    $this->data->infodecoded = true;
                } else if ($this->data->infodecoded === false) {
                    // Decode info.
                    $this->data->info = backup_controller_dbops::decode_backup_temp_info($this->data->info);
                    $this->data->infodecoded = true;
                }
                return $this->data->info;
            default:
                return $this->data->$name;
        }
    }

    /**
     * Magic method for isset/empty.
     * @param $key
     * @return bool
     */
    public function __isset($key){
        if (null === $this->__get($key)){
            return false;
        }
        return true;
    }

    /**
     * get - get a MUC per-request cache for this backupid/itemname
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @return cache_application|cache_session|cache_store
     */
    protected static function get_cache(string $backupid, string $itemname) {
        if (!isset(self::$stores[$backupid][$itemname])) {
            self::$stores[$backupid][$itemname] = \cache::make('core', 'backup', compact('backupid', 'itemname'));
        }
        return self::$stores[$backupid][$itemname];
    }

    /**
     * Set key and value set into cache.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @param string $key The key to use.
     * @param mixed $data The data to set.
     */
    public static function set_data(string $backupid, string $itemname, $key, $data) {
        $store = self::get_cache($backupid, $itemname);
        $store->set($key, $data);
    }

    /**
     * Set key and value sets into cache.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @param array $keyvaluearray An array of key => value pairs to send to the cache.
     */
    public static function set_many(string $backupid, string $itemname, array $keyvaluearray) {
        $store = self::get_cache($backupid, $itemname);
        $store->set_many($keyvaluearray);
    }

    /**
     * Purges the cache deleting all items within it.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     */
    public static function purge(string $backupid, string $itemname) : void {
        $store = self::get_cache($backupid, $itemname);
        $store->purge();
    }

    /**
     * get_stores - get a list of stores that we have instantiated
     *
     * @param string $backupid Id for using for backup/restore (Optional).
     *                      If it's not specified, array of stores by backupid is returned.
     * @return array
     */
    public static function get_stores(string $backupid = "0") : array {
        if ($backupid) {
            return self::$stores[$backupid] ?? [];
        } else {
            return self::$stores;
        }
    }

    /**
     * reset - clear all stores and forget them
     *
     * @param string $backupid Id for clearing for backup/restore (Optional).
     *                         Clear all stores if it's not given.
     */
    public static function reset(string $backupid = "0") : void {
        if ($backupid === "0") {
            foreach (self::get_stores() as $stores) {
                foreach ($stores as $store) {
                    $store->purge();
                }
            }
            self::$stores = [];
        } else {
            if (isset($stores[$backupid])) {
                foreach ($stores[$backupid] as $store) {
                    $store->purge();
                }
                unset($stores[$backupid]);
            }
        }
    }

    /**
     * Get a record by $restoreid, $itemname, and $itemid.
     *
     * @param string $restoreid id of backup
     * @param string $itemname name of the item (e.g. 'user')
     * @param ?int $itemid id of item (e.g. id of 'user')
     * @return mixed
     */
    public static function get_backup_record(string $restoreid, string $itemname, ?int $itemid) {
        if (is_null($itemid)) {
            return false;
        }
        // If the record doesn't need info, then check cache and return if available.
        $dbrec = self::get_backup_cached_record($restoreid, $itemname, $itemid);
        if (!empty($dbrec)) {
            $data = new self($dbrec);
            $dbrec->infodecoded = null;
            return $data;
        }

        // If the record is not in cache, search from db.
        $dbrec = self::get_backup_db_record($restoreid, $itemname, $itemid);
        if ($dbrec) {
            // Set cache.
            self::set_backup_cached_record($restoreid, $itemname, $itemid, (array) $dbrec);
            $dbrec->infodecoded = false;
            $data = new self($dbrec);
            return $data;
        } else {
            return false;
        }

    }

    /**
     * Return cached backup id's
     *
     * @param string $restoreid id of backup
     * @param string $itemname name of the item
     * @param int $itemid id of item
     * @return mixed backup id's
     */
    protected static function get_backup_cached_record(string $restoreid, string $itemname, int $itemid) {
        $cache = self::get_cache($restoreid, $itemname);

        // If record exists in cache then return.
        $result = $cache->get($itemid);
        if ($result) {
            $result = (object) $result;
            $result->restoreid = $restoreid;
            $result->itemname = $itemname;
            $result->itemid = $itemid;
            $result->info = null;
        }
        return $result;
    }

    /**
     * Get backup record from db.
     *
     * @param string $restoreid id of backup
     * @param string $itemname name of the item
     * @param int $itemid id of item
     * @return mixed
     */
    protected static function get_backup_db_record(string $restoreid, string $itemname, int $itemid) {
        global $DB;
        $record = [
            'backupid' => $restoreid,
            'itemname' => $itemname,
            'itemid'   => $itemid
        ];
        return $DB->get_record('backup_ids_temp', $record);
    }

    /**
     * Cache backup ids'
     *
     * @param string $restoreid id of backup
     * @param string $itemname name of the item
     * @param int $itemid id of item
     * @param array $extrarecord extra record which needs to be updated
     * @return void
     */
    public static function set_backup_record(string $restoreid, string $itemname, int $itemid, array $extrarecord) : void {
        self::set_backup_cached_record($restoreid, $itemname, $itemid, $extrarecord);
        self::set_backup_db_record($restoreid, $itemname, $itemid, $extrarecord);
    }

    /**
     * Updates existing backup record
     *
     * @param string $restoreid id of backup
     * @param string $itemname name of the item
     * @param int $itemid id of item
     * @param array $extrarecord extra record which needs to be updated
     */
    protected static function set_backup_cached_record(string $restoreid, string $itemname, int $itemid,
            array $extrarecord) : void {
        $cache = self::get_cache($restoreid, $itemname);
        // Info won't be in cache.
        unset($extrarecord['info']);
        // Update existing cache or add new record to cache.
        if ($cache->get($itemid)) {
            // Update only if extrarecord is not empty.
            if (!empty($extrarecord)) {
                $trecord = array_merge($cache->get($itemid), $extrarecord);
                self::set_data($restoreid, $itemname, $itemid, $trecord);
            }
        } else {
            $recorddefault = array (
                    'newitemid' => 0,
                    'parentitemid' => null,
                    'info' => null);
            $extrarecord = array_merge($recorddefault, $extrarecord);
            self::set_data($restoreid, $itemname, $itemid, $extrarecord);
        }
    }

    /**
     * Insert / updates existing backup record in db.
     *
     * @param string $restoreid id of backup
     * @param string $itemname name of the item
     * @param int $itemid id of item
     * @param array $extrarecord extra record which needs to be updated
     */
    protected static function set_backup_db_record(string $restoreid, string $itemname, int $itemid, array $extrarecord) : void {
        global $DB;
        if ($existingrecord = self::get_backup_db_record($restoreid, $itemname, $itemid)) {
            // Update only if extrarecord is not empty.
            if (!empty($extrarecord)) {
                $extrarecord['id'] = $existingrecord->id;
                $DB->update_record('backup_ids_temp', $extrarecord);
            }
        } else {
            $record = [
                    'backupid' => $restoreid,
                    'itemname' => $itemname,
                    'itemid'   => $itemid];
            $recorddefault = [
                    'newitemid' => 0,
                    'parentitemid' => null,
                    'info' => null];
            $record = array_merge($record, $recorddefault, $extrarecord);
            $DB->insert_record('backup_ids_temp', $record);
        }
    }
}
