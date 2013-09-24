<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Project_Deletion_Request extends ORM {

    protected $_table_name = 'project_deletion_requests';

    protected $_primary_key = 'request_id';

    protected $_table_columns = array(
        'request_id' => array(),
        'project_id' => array(),
        'due_on' => array()
    );

    /**
     * Delete all project deletion requests for the given project
     *
     * @param   int     $project_id
     */
    public function delete_all($project_id)
    {
        DB::delete($this->_table_name)
            ->where('project_id', '=', DB::expr($project_id))
            ->execute();
    }
}

// END Kohana_Model_Project_Deletion_Request
