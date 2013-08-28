<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Project extends ORM {

    protected $_table_name = 'projects';

    protected $_primary_key = 'project_id';

    protected $_table_columns = array(
        'project_id' => array(),
        'account_id' => array(),
        'name' => array(),
        'description' => array(),
        'created_by' => array(),
        'archived' => array(),
        'deleted' => array()
    );

    /**
     * Defines validation rules
     *
     * @return  array
     */
    public function rules()
    {
        return array(
            'name' => array(
                array('not_empty')
            )
        );
    }

    /**
     *  Defines filters
     *
     * @return  array
     */
    public function filters()
    {
        return array(
            'name' => array(
                array('trim')
            )
        );
    }

    /**
     * Get data about one or multiple projects
     *
     * @param   int|array   $project_id
     * @return  array
     */
    public function get_project_data($project_id)
    {
        $result = DB::select_array(array(
            'p.project_id',
            'p.account_id',
            'p.name',
            'p.description',
            'p.archived',
            'p.deleted',
            'u.user_id',
            'u.first_name',
            'u.last_name'
        ))
            ->from(array('projects', 'p'))
            ->join(array('projects_users', 'pu'))
            ->on('pu.project_id', '=', 'p.project_id')
            ->join(array('accounts_users', 'au'))
            ->on('au.account_id', '=', 'p.account_id')
            ->on('au.user_id', '=', 'pu.user_id')
            ->on('au.status', '!=', DB::expr("'left'"))
            ->on('au.status', '!=', DB::expr("'removed'"))
            ->join(array('users', 'u'))
            ->on('pu.user_id', '=', 'u.user_id')
            ->where('p.project_id', 'IN', (array)$project_id)
            ->execute($this->_db);

        $data = array();
        foreach ($result as $row)
        {
            if ( ! isset($data[$row['project_id']]))
            {
                $data[$row['project_id']] = array_intersect_key($row, array_flip(array('project_id', 'account_id', 'name', 'description', 'status')));
                $data[$row['project_id']]['users'] = array();
            }

            array_push($data[$row['project_id']]['users'], array_intersect_key($row,array_flip(array('user_id', 'first_name', 'last_name'))));
        }

        return array_values($data);
    }

    /**
     * Add the given user to one or more projects
     *
     * @param   int|array       $project_id
     * @param   int             $user_id
     */
    public function add_user($project_id, $user_id)
    {
        $project_ids = (array)$project_id;

        $query = DB::insert('projects_users');
        foreach ($project_ids as $project_id)
        {
            $query->values(array($project_id, $user_id, 0));
        }

        $sql = $query->compile().' ON DUPLICATE KEY UPDATE project_id=project_id';

        DB::query(Database::INSERT, $sql)->execute($this->_db);
    }

    /**
     * Remove the given user from one or more projects
     *
     * @param   int|array   $project_id
     * @param   int         $user_id
     */
    public function remove_user($project_id, $user_id)
    {
        // Remove user from project
        DB::delete('projects_users')
            ->where('user_id', '=', $user_id)
            ->and_where('project_id', 'IN', (array)$project_id)
            ->execute($this->_db);
    }

    /**
     * Check if the given user is linked to one or more projects
     *
     * @param   int|array       $project_id
     * @param   int             $user_id
     * @return  bool
     */
    public function is_user_linked($project_id, $user_id)
    {
        $project_id = (array)$project_id;

        // Check if user is added to all of the given projects
        return count($project_id) == DB::select(array(DB::expr('COUNT("*")'), 'total_count'))
            ->from('projects_users')
            ->where('project_id', 'IN', $project_id)
            ->where('user_id', '=', DB::expr($user_id))
            ->execute($this->_db)
            ->get('total_count');
    }

    /**
     * Star/Un-star the given project
     *
     * @param   int     $project_id
     * @param   int     $user_id
     * @param   bool    $star
     */
    public function star($project_id, $user_id, $star = TRUE)
    {
        DB::update('projects_users')
            ->set(array('starred' => (int)$star))
            ->where('project_id', '=', DB::expr($project_id))
            ->where('user_id', '=', DB::expr($user_id))
            ->execute($this->_db);
    }

    /**
     * Get the users on this project
     *
     * @param   int     $project_id
     * @return  array
     */
    public function get_users($project_id)
    {
        return DB::select_array(array(
            'u.user_id',
            'u.first_name',
            'u.last_name',
            'ui.username',
            'ui.email'
        ))
            ->from(array('users', 'u'))
            ->join(array('projects_users', 'pu'))
            ->on('pu.user_id', '=', 'u.user_id')
            ->on('pu.project_id', '=', DB::expr($project_id))
            ->join(array('accounts_users', 'au'))
            ->on('au.account_id', '=', 'pu.project_id')
            ->on('au.user_id', '=', 'pu.user_id')
            ->on('au.status', '!=', DB::expr("'left'"))
            ->on('au.status', '!=', DB::expr("'removed'"))
            ->join(array('user_identities', 'ui'))
            ->on('ui.user_id', '=', 'u.user_id')
            ->execute($this->_db)
            ->as_array();
    }

    /**
     * Get the number of users on the given project
     *
     * @param   int     $project_id
     * @return  int
     */
    public function get_user_count($project_id)
    {
        return count($this->get_users($project_id));
    }

    /**
     * Get the projects a user is added to on a given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_projects($account_id, $user_id)
    {
        $result = DB::select_array(
            array(
                'p.project_id',
                'p.account_id',
                'p.name',
                'p.description',
                'p.archived',
                'p.deleted',
                'pu1.starred',
                'u.user_id',
                'u.first_name',
                'u.last_name'
            ))
            ->from(array('projects', 'p'))
            ->join(array('projects_users', 'pu1'))
            ->on('pu1.project_id', '=', 'p.project_id')
            ->on('pu1.user_id', '=', DB::expr($user_id))
            ->join(array('projects_users', 'pu2'))
            ->on('p.project_id', '=', 'pu2.project_id')
            ->join(array('users', 'u'))
            ->on('u.user_id', '=', 'pu2.user_id')
            ->where('p.account_id', '=', DB::expr($account_id))
            ->execute($this->_db);

        $projects = array();
        foreach ($result as $row)
        {
            if ( ! isset($projects[$row['project_id']]))
            {
                $projects[$row['project_id']] = array_intersect_key($row, array_flip(array('project_id', 'account_id', 'name', 'description', 'status', 'starred')));
                $projects[$row['project_id']]['users'] = array();
            }

            array_push($projects[$row['project_id']]['users'], array_intersect_key($row,array_flip(array('user_id', 'first_name', 'last_name'))));
        }

        return array_values($projects);
    }

    /**
     * Get the number of projects a user is added to on a given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  int
     */
    public function get_user_project_count($account_id, $user_id)
    {
        return count($this->get_user_projects($account_id, $user_id));
    }

    /**
     * Get the ids of projects the user is linked to (on all accounts)
     *
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_project_ids($user_id)
    {
        $result = DB::select('p.project_id')
            ->from(array('projects', 'p'))
            ->join(array('projects_users', 'pu'))
            ->on('pu.project_id', '=', 'p.project_id')
            ->on('pu.user_id', '=', DB::expr($user_id))
            ->as_assoc()
            ->execute($this->_db);

        $project_ids = array();
        foreach ($result as $row)
        {
            array_push($project_ids, $row['project_id']);
        }

        return $project_ids;
    }

    /**
     * Do garbage collection
     *
     * @param   int     $start_date   Grace period in days
     */
    public function garbage_collector($start_date)
    {
        // Delete all projects from trash, with a deletion request older than $grace_period
        $sql = "DELETE projects
                FROM projects
                JOIN project_deletion_requests
                ON (project_deletion_requests.project_id = projects.project_id
                AND project_deletion_requests.requested_on < :start_date)
                WHERE projects.deleted = 1";

        DB::query(Database::DELETE, $sql)
            ->bind(':start_date', $start_date)
            ->execute($this->_db);
    }

    /**
     * Begin a transaction
     */
    public function begin()
    {
        $this->_db->begin();
    }

    /**
     * Commit a transaction
     */
    public function commit()
    {
        $this->_db->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback()
    {
        $this->_db->rollback();
    }
}

// END Kohana_Model_Project