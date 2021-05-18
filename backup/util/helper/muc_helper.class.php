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
     * @var array Store array of boolean if the chache of $itemname is already made.
     */
    private static $stores = [];

    /**
     * get - get a MUC per-request cache for this itemname
     *
     * @param string $itemname The key name of cache.
     * @return cache_application|cache_session|cache_store
     */
    public static function get($itemname) {
        if (!isset(self::$stores[$itemname])) {
            self::$stores[$itemname] = true;
        }

        return cache::make_from_params(cache_store::MODE_REQUEST, 'core', 'backup_' . $itemname,
            [], ['simplekeys' => true, 'simpledata' => true]);
    }

    /**
     * get_stores - get a list of stores that we have instantiated
     */
    public static function get_stores() {
        return self::$stores;
    }

    /**
     * reset - clear all stores and forget them
     */
    public static function reset() {
        foreach (array_keys(self::get_stores()) as $store) {
            $cache = self::get($store);
            $cache->purge();
        }

        self::$stores = [];
    }
}
