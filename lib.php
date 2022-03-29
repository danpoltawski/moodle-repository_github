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
 * @package    repository_github
 * @copyright  2012 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Simple repository plugin for downloading zipballs from github tags
 *
 * @package    repository_github
 * @copyright  2012 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_github extends repository {
    /** user preference key for username */
    const PREFNAME = 'repository_github_username';
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
            if ($this->validate_user($username)) {
                return true;
            }
        }

        // A username has been submitted from form.
        $submitted = optional_param('github_username', '', PARAM_ALPHANUMEXT);
        if (!empty($submitted)) {
            if ($this->validate_user($submitted)) {
                set_user_preference(self::PREFNAME, $submitted);
                return true;
            }
        }

        return false;
    }

    private function validate_user($username) {
        $c = new curl();
        $json = $c->get(self::APIBASE.'/users/'.$username);
        if ($c->info['http_code'] !== 200 or empty($json)) {
            return false;
        }

        $this->username = $username;
        return true;
    }

    public function print_login() {
        $username = new stdClass();
        $username->label = get_string('username', 'repository_github');
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
        $ret['logouttext'] = get_string('currentuser', 'repository_github', $this->username);
        $ret['path'] = array(array('name'=>get_string('repos', 'repository_github'), 'path'=>''));

        if (empty($path)) {
            $ret['list'] = $this->list_repositories();
        } else {

            $pieces = explode('/', $path);
            $ret['path'][] = array('name'=> $pieces[0], 'path'=> $pieces[0]);

            if (count($pieces) > 1) {

                if ($pieces[1] === 'tags') {
                    $ret['path'][] = array('name'=> get_string('tags', 'repository_github'), 'path'=> 'tags');
                    $ret['list'] = $this->list_repository_tags($pieces[0]);
                } else if ($pieces[1] === 'branches') {
                    $ret['path'][] = array('name'=> get_string('branches', 'repository_github'), 'path'=> 'branches');
                    $ret['list'] = $this->list_repository_branches($pieces[0]);
                }
            } else {
                $ret['list'] = $this->list_repository_metafolders($pieces[0]);
            }
        }

        return $ret;
    }

    private function list_repository_metafolders($repository) {
        global $OUTPUT;

        $files = array();
        $files[] = array('title'=> 'Tags',
            'path' =>  $repository.'/tags',
            'thumbnail' => $this->get_icon_url(file_folder_icon(90)),
            'children' => array(),
        );
        $files[] = array('title'=> 'Branches',
            'path' =>  $repository.'/branches',
            'thumbnail' => $this->get_icon_url(file_folder_icon(90)),
            'children' => array(),
        );

        return $files;
    }

    private function list_repository_branches($reponame) {
        global $OUTPUT;

        $c = new curl();
        $url = self::APIBASE.'/repos/'.$this->username.'/'.$reponame.'/branches';
        $json = $c->get($url);

        if ($c->info['http_code'] !== 200 or empty($json)) {
            return array();
        }
        $branches = json_decode($json);

        $files = array();
        foreach ($branches as $branch) {
            $files[] = array('title'=> $reponame.'-'.$branch->name.'.zip',
                'thumbnail' => $this->get_icon_url(file_extension_icon('download.zip', 90)),
                'source' => self::APIBASE.'/repos/'.$this->username.'/'.$reponame.'/zipball/'.$branch->name,
                'shorttitle' => $branch->name,
                'url' => $branch->commit->url,
            );
        };

        return $files;
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
                'thumbnail' => $this->get_icon_url(file_extension_icon('download.zip', 90)),
                'source' => $tag->zipball_url,
                'shorttitle' => $tag->name,
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
                'size' => $repo->size*1024,
                'shorttitle' => $repo->name.': '.$repo->description,
                'thumbnail' => $this->get_icon_url(file_folder_icon(90)),
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
        $result = $c->download_one($url, null, ['file' => $fp]);

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
        return FILE_INTERNAL | FILE_EXTERNAL;
    }

    /**
     * Helper function dealing with deprecation of pix_url in Moodle 3.3.
     *
     * In Moodle 3.3, the $OUTPUT->pix_url() has been deprecated and
     * image_url() should be used for images, like the thumbnails in our case.
     *
     * @param string $iconpath relative path to the icon image representing an item
     * @return string URL of the image
     */
    protected function get_icon_url($iconpath) {
        global $CFG, $OUTPUT;

        if ($CFG->branch >= 33) {
            return $OUTPUT->image_url($iconpath)->out(false);
        } else {
            return $OUTPUT->pix_url($iconpath)->out(false);
        }
    }
}
