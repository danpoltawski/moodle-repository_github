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
 * Simple repository plugin for downloading zipballs from github tags
 *
 * @package    repository_githubtagdownload
 * @copyright  2012 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Simple repository plugin for downloading zipballs from github tags
 *
 * @package    repository_githubtagdownload
 * @copyright  2012 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_githubtagdownload extends repository {
    /** user preference key for username */
    const PREFNAME = 'repository_githubtagdownload_username';
    /** Base of uri for github api requests */
    const APIBASE = 'https://api.github.com';
    /** @var string username for which we are browsing */
    private $username = '';

    /**
     * Check if we are 'logged in' (have a github
     * user prefence)
     *
     * @return bool true if logged in
     */
    public function check_login() {
        // We've already set a github username.
        $username = get_user_preferences(self::PREFNAME, '');
        if (!empty($username)) {
            $this->username = $username;
            return true;
        }

        // A username has been submitted from form.
        $submitted = optional_param('github_username', '', PARAM_ALPHANUM);
        if (!empty($submitted)) {
            $this->username = $submitted;
            set_user_preference(self::PREFNAME, $submitted);
            return true;
        }

        return false;
    }

    public function print_login() {
        $username = new stdClass();
        $username->label = get_string('username', 'repository_githubtagdownload');
        $username->type  = 'text';
        $username->name  = 'github_username';
        $username->value = '';

        return array('login' => array($username));
    }

    public function logout() {
        $this->username = '';
        unset_user_preference(self::PREFNAME);
        return $this->print_login();
    }

    /**
     * Print files listing. We display the users repositories
     * and then allow tag zipballs to be downloaded
     *
     * @param string $path the github repository name
     * @param string $page the page number of file list
     * @return array the list of files, including meta infomation
     */
    public function get_listing($path='', $page = '') {
        $ret = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        if (empty($path)) {
            $ret['list'] = $this->list_repositories();
        } else {
            $ret['list'] = $this->list_repository_tags($path);
        }

        return $ret;
    }

    /**
     * Returns a listing of zipballs to be downloaded
     * from a specified repository
     *
     * @param string $reponame the github repository
     * @return array listing of files for repository
     */
    private function list_repository_tags($reponame) {
        global $OUTPUT;

        $c = new curl();
        $url = self::APIBASE.'/repos/'.$this->username.'/'.$reponame.'/tags';
        $json = $c->get($url);

        if ($c->info['http_code'] !== 200 or empty($json)) {
            return array();
        }
        $tags = json_decode($json);

        $files = array();
        foreach ($tags as $tag) {
            $files[] = array('title'=> $reponame.'-'.$tag->name.'.zip',
                'thumbnail' => $OUTPUT->pix_url(file_extension_icon('tagdownload.zip', 90))->out(false),
                'source' => $tag->zipball_url,
                'url' => $tag->commit->url,
            );
        };

        return $files;
    }

    /**
     * Returns a listing of repositories in 'repository'
     * folder form for browsing
     *
     * @return array listing of repositories as folders
     */
    private function list_repositories() {
        global $OUTPUT;

        $c = new curl();
        $url = self::APIBASE.'/users/'.$this->username.'/repos?sort=updated';
        $json = $c->get($url);

        if ($c->info['http_code'] !== 200 or empty($json)) {
            // Unset user preference, because probably don't
            // want to go back here consistently.
            unset_user_preference(self::PREFNAME);
            return array();
        }

        $repos = json_decode($json);

        $files = array();
        foreach ($repos as $repo) {
            $files[] = array('title'=> $repo->name,
                'date' => strtotime($repo->updated_at),
                'path' => $repo->name,
                'size' => $repo->size,
                'thumbnail' => $OUTPUT->pix_url(file_folder_icon(90))->out(false),
                'children' => array(),
            );
        };

        return $files;
    }

    /**
     * Download a zipball from github
     *
     * @param string $url the url of file
     * @param string $filename save location
     * @return array with elements:
     *   path: internal location of the file
     *   url: URL to the source (from parameters)
     */
    public function get_file($url, $filename = '') {
        $path = $this->prepare_file($filename);

        $fp = fopen($path, 'w');
        $c = new curl();
        $c->setopt(array('CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 3));
        $result = $c->download(array(array('url' => $url, 'file'=> $fp)));

        // Close file handler.
        fclose($fp);
        if (empty($result)) {
            unlink($path);
            return null;
        }
        return array('path'=>$path, 'url'=>$url);
    }

    public function supported_filetypes() {
        return array('application/zip');
    }
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }
}
