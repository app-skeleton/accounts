<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Project_Manager {

    /**
     * @var Project_Manager     A singleton instance
     */
    protected static $_instance;

    /**
     * @var ORM                 An instance of the Model_Project class
     */
    protected $_project_model;

    /**
     * @var bool                Whether to cache the users accounts, and user account permissions (these are most frequently queried)
     */
    public static $use_cache = TRUE;

    /**
     * Create a project
     *
     * @param   array   $values
     * @throws  Validation_Exception
     * @return  array
     */
    public function create_project($values)
    {
        $project_model = ORM::factory('Project');

        try
        {
            $values['archived'] = 0;
            $values['deleted'] = 0;
            $project_model->values($values)->save();

            return $project_model->as_array();
        }
        catch (ORM_Validation_Exception $e)
        {
            $errors = $e->errors('models/'.i18n::lang().'/project', FALSE);
            throw new Validation_Exception($errors);
        }
    }

    /**
     * Update a project
     *
     * @param   int     $project_id
     * @param   array   $values
     * @return  array
     * @throws  Validation_Exception
     * @throws  Kohana_Exception
     */
    public function update_project($project_id, $values)
    {
        $project_model = ORM::factory('Project', $project_id);

        if ( ! $project_model->loaded())
        {
            throw new Kohana_Exception('Can not load project with id: '.$project_id);
        }

        try
        {
            $project_model->values($values)->save();

            return $project_model->as_array();
        }
        catch (ORM_Validation_Exception $e)
        {
            $errors = $e->errors('models/'.i18n::lang().'/project', FALSE);
            throw new Validation_Exception($errors);
        }
    }

    /**
     * Get data about one or more projects
     *
     * @param   int|array   $project_id
     * @return  array
     * @throws  Kohana_Exception
     */
    public function get_project_data($project_id)
    {
        return $this->project_model()->get_project_data($project_id);
    }

    /**
     * Delete a project
     *
     * @param   int     $project_id
     * @param   bool    $permanently
     * @throws  Kohana_Exception
     */
    public function delete_project($project_id, $permanently = FALSE)
    {
        $project_model = ORM::factory('Project', $project_id);

        if ( ! $project_model->loaded())
        {
            throw new Kohana_Exception('Can not load project with id: '.$project_id);
        }

        if ( ! $permanently)
        {
            // Just move the project to trash
            $project_model->set('deleted', 1)->save();

            // Create a project deletion request
            ORM::factory('Project_Deletion_Request')
                ->set('project_id', $project_id)
                ->set('requested_on', date('Y-m-d H:i:s'))
                ->save();
        }
        else
        {
            // Delete the project permanently
            $project_model->delete();
        }
    }

    /**
     * Archive a project
     *
     * @param   int     $project_id
     * @throws  Kohana_Exception
     */
    public function archive_project($project_id)
    {
        $project_model = ORM::factory('Project', $project_id);

        if ( ! $project_model->loaded())
        {
            throw new Kohana_Exception('Can not load project with id: '.$project_id);
        }

        $project_model->set('archived', 1)->save();
    }

    /**
     * Restore a project from trash/archived state
     *
     * @param   int     $project_id
     * @throws  Kohana_Exception
     */
    public function restore_project($project_id)
    {
        $project_model = ORM::factory('Project', $project_id);

        if ( ! $project_model->loaded())
        {
            throw new Kohana_Exception('Can not load project with id: '.$project_id);
        }

        $project_model
            ->set('archived', 0)
            ->set('deleted', 0)
            ->save();

        // Remove any deletion requests for this project
        ORM::factory('Project_Deletion_Request')->delete_all($project_id);
    }

    /**
     * Add the given user to one or more projects
     *
     * @param   int|array       $project_id
     * @param   int             $user_id
     * @throws  Exception
     */
    public function add_user($project_id, $user_id)
    {
        $project_model = $this->project_model();
        $project_model->begin();
        $project_model->add_user($project_id, $user_id);

        try
        {
            if (self::$use_cache)
            {
                $account_cache = Account_Cache::instance();
                $account_cache->add_project($user_id, $project_id);
            }

            // Everything is fine, commit
            $project_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the cache, rollback
            $project_model->rollback();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Remove the given user from one or more projects
     *
     * @param   int     $project_id
     * @param   int     $user_id
     * @throws  Exception
     */
    public function remove_user($project_id, $user_id)
    {
        $project_model = $this->project_model();
        $project_model->begin();
        $project_model->remove_user($project_id, $user_id);

        try
        {
            if (self::$use_cache)
            {
                $account_cache = Account_Cache::instance();
                $account_cache->remove_project($user_id, $project_id);
            }

            // Everything is fine, commit
            $project_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the cache, rollback
            $project_model->rollback();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Check if a user is linked to one or more projects
     *
     * @param   int|array   $project_id
     * @param   int         $user_id
     */
    public function is_user_linked($project_id, $user_id)
    {
        return $this->project_model()->is_user_linked($project_id, $user_id);
    }

    /**
     * Star a project
     *
     * @param   int     $project_id
     * @param   int     $user_id
     */
    public function star($project_id, $user_id)
    {
        $this->project_model()->star($project_id, $user_id, TRUE);
    }

    /**
     * Un-star a project
     *
     * @param   int     $project_id
     * @param   int     $user_id
     */
    public function un_star($project_id, $user_id)
    {
        $this->project_model()->star($project_id, $user_id, FALSE);
    }

    /**
     * Get the users from the given project
     *
     * @param   int     $project_id
     * @return  array
     */
    public function get_users($project_id)
    {
        return $this->project_model()->get_users($project_id);
    }

    /**
     * Get the number of users on the given project
     *
     * @param   int     $project_id
     */
    public function get_user_count($project_id)
    {
        return $this->project_model()->get_user_count($project_id);
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
        return $this->project_model()->get_user_projects($account_id, $user_id);
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
        return $this->project_model()->get_user_project_count($account_id, $user_id);
    }

    /**
     * Get the ids of projects the user is linked to (on all accounts)
     *
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_project_ids($user_id)
    {
        if (self::$use_cache)
        {
            $account_cache = Account_Cache::instance();
            $account_cache->update_only(FALSE);
            $project_ids = $account_cache->load_projects($user_id);

            if ( ! isset($project_ids))
            {
                // Load from DB
                $project_ids = $this->project_model()->get_user_project_ids($user_id);

                // Save to cache
                $account_cache->save_projects($user_id, $project_ids);
            }
        }
        else
        {
            // Load from DB
            $project_ids = $this->project_model()->get_user_project_ids($user_id);
        }

        return $project_ids;
    }


    /**
     * Check if the given user has access to the given project(s)
     *
     * @param   int|array   $project_id
     * @param   int         $user_id
     * @return  bool
     */
    public function has_access($project_id, $user_id)
    {
        $project_id = (array)$project_id;
        $project_ids = $this->get_user_project_ids($user_id);

        return array_diff($project_id, $project_ids) == array();
    }

    /**
     * Do garbage collection
     */
    public function garbage_collector()
    {
        // Get the grace period
        $grace_period = Kohana::$config->load('account')->get('trash_grace_period');

        // Calculate start date
        $start_date = date('Y-m-d H:i:s', time() - $grace_period * 24 * 3600);

        $this->project_model()->garbage_collector($start_date);
    }

    /**
     * Return an instance of the Model_Project class
     *
     * @return ORM
     */
    public function project_model()
    {
        if ( ! isset($this->_project_model))
        {
            $this->_project_model = ORM::factory('Project');
        }

        return $this->_project_model;
    }

    /**
     * Returns a singleton instance of the class.
     *
     * @return  Project_Manager
     */
    public static function instance()
    {
        if ( ! Project_Manager::$_instance instanceof Project_Manager)
        {
            Project_Manager::$_instance = new Project_Manager();
        }

        return Project_Manager::$_instance;
    }
}

// END Kohana_Project_Manager
