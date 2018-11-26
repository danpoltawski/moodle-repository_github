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
 * Defines {@link \repository_github\privacy\provider} class.
 *
 * @package     repository_github
 * @category    privacy
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_github\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for the GitHub repository plugin.
 *
 * @copyright  2018 David Mudrák <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\user_preference_provider {

    use \core_privacy\local\legacy_polyfill;

    /**
     * Describe all the places where the GitHub repository plugin stores some personal data.
     *
     * @param collection $collection Collection of items to add metadata to.
     * @return collection Collection with our added items.
     */
    public static function _get_metadata(collection $collection) {

        $collection->add_user_preference('repository_github_username', 'privacy:metadata:preference:username');

        $collection->add_external_location_link('github', [
           'githubusername' => 'privacy:metadata:external:github:githubusername',
        ], 'privacy:metadata:external:github');

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the specified user.
     *
     * @param int $userid ID of the user.
     * @return contextlist List of contexts containing the user's personal data.
     */
    public static function _get_contexts_for_userid($userid) {
        return new contextlist();
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
    }

    /**
     * Export personal data stored in the given contexts.
     *
     * @param approved_contextlist $contextlist List of contexts approved for export.
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * Delete personal data for all users in the context.
     *
     * @param context $context Context to delete personal data from.
     */
    public static function _delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $users = $userlist->get_users();
        foreach ($users as $user) {
            delete_user_data($user);
        }
    }

    /**
     * Delete personal data for the user in a list of contexts.
     *
     * @param approved_contextlist $contextlist List of contexts to delete data from.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        delete_user_data($user);
    }

    /**
     * Export all user preferences controlled by this plugin.
     *
     * @param int $userid ID of the user we are exporting data for
     */
    public static function _export_user_preferences($userid) {

        $username = get_user_preferences('repository_github_username', null, $userid);

        if ($username !== null) {
            writer::export_user_preference('repository_github', 'repository_github_username', $username,
                get_string('privacy:metadata:preference:username', 'repository_github'));
        }
    }

    /**
     * This does the deletion of user data for the github repository.
     *
     * @param  stdclass $user The user to delete data.
     */
    protected static function delete_user_data(\stdClass $user) {
        unset_user_preference('repository_github_username', $user);
    }
}
