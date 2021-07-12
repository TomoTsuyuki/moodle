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
 * Class backup_muc_manager
 */
abstract class backup_muc_manager {

    /**
     * @var array Store array of cache_store if the cache of $itemname is already made.
     */
    private static $stores = [];

    /**
     * get - get a MUC per-request cache for this backupid/itemname
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @return cache_application|cache_session|cache_store
     */
    public static function get(string $backupid, string $itemname) {
        if (!isset(self::$stores[$backupid][$itemname])) {
            self::$stores[$backupid][$itemname] = \cache::make('core', 'backup', compact('backupid', 'itemname'));
        }
        return self::$stores[$backupid][$itemname];
    }

    /**
     * Get all keys for the cache.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @return array|int[]|string[]
     * @throws coding_exception
     */
    public static function get_allkeys(string $backupid, string $itemname) {
        $store = self::get($backupid, $itemname);
        $storekeys = $store->get('keys');
        return $storekeys ? array_keys($storekeys) : [];
    }

    /**
     * Set key and value set into cache.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @param string $key The key to use.
     * @param mixed $data The data to set.
     */
    public static function set(string $backupid, string $itemname, $key, $data) {
        $store = self::get($backupid, $itemname);
        $store->set($key, $data);
        $storekeys = $store->get('keys');
        $storekeys[$key] = true;
        $store->set('keys', $storekeys);
    }

    /**
     * Set key and value sets into cache.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     * @param array $keyvaluearray An array of key => value pairs to send to the cache.
     */
    public static function set_many(string $backupid, string $itemname, array $keyvaluearray) {
        $store = self::get($backupid, $itemname);
        $store->set_many($keyvaluearray);
        $storekeys = $store->get('keys');
        foreach ($keyvaluearray as $key => $value) {
            $storekeys[$key] = true;
        }
        $store->set('keys', $storekeys);
    }

    /**
     * Purges the cache deleting all items within it.
     *
     * @param string $backupid Id for using for backup/restore.
     * @param string $itemname The key name of cache.
     */
    public static function purge(string $backupid, string $itemname) {
        $store = self::get($backupid, $itemname);
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
}
