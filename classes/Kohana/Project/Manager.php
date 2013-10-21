<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Project_Manager extends Account_Service_Manager {

    /**
     * Singleton instance
     *
     * @var Project_Manager         Singleton instance of the Project Manager
     */
    protected static $_instance;

    /**
     * @var ORM                     An instance of the Model_Project class
     */
    protected $_project_model;

    /**
     * Create a project
     *
     * @param   array   $values
     * @throws  Validation_Exception
     * @throws  Exception
     * @return  array
     */
    public function create_project($values)
    {
        // Get the account manager
        $account_manager = Account_Manager::instance();

        // Begin transaction
        $this->begin_transaction();

        try
        {
            $values = array_merge($values, array(
                'archived' => 0,
                'deleted' => 0
            ));

            // Save project
            $project_model = ORM::factory('Project')
                ->values($values)
                ->save();

            // Get the project id
            $project_id = $project_model->pk();
            $user_id = $project_model->get('created_by');
            $account_id = $project_model->get('account_id');

            // Get the account owner
            $account_owner_id = $account_manager->get_account_owner_id($account_id);

            // Add the user and the account owner to the project
            $this->add_user($project_id, $user_id);
            if ($user_id != $account_owner_id)
            {
                $this->add_user($project_id, $account_owner_id);
            }

            // Everything was going fine, commit
            $this->commit_transaction();

            return $project_model->as_array();
        }
        catch (ORM_Validation_Exception $e)
        {
            // Validation failed, rollback
            $this->rollback_transaction();

            $errors = $e->errors('models/'.i18n::lang().'/project', FALSE);
            throw new Validation_Exception($errors);
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();

            // Re-throw the exception
            throw $e;
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
            throw new Kohana_Exception(
                'Can not find the project with id: :project_id.', array(
                ':project_id' => $project_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        try
        {
            $project_model
                ->values($values)
                ->save();

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
     * @throws  Exception
     */
    public function delete_project($project_id, $permanently = FALSE)
    {
        $project_model = ORM::factory('Project', $project_id);

        if ( ! $project_model->loaded())
        {
            throw new Kohana_Exception(
                'Can not find the project with id: :project_id.', array(
                ':project_id' => $project_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        // Begin transaction
        $this->begin_transaction(FALSE);

        try
        {
            if ( ! $permanently)
            {
                // Just move the project to trash
                $project_model
                    ->set('deleted', 1)
                    ->save();

                // Get the grace period
                $grace_period = Kohana::$config->load('account')->get('trash_grace_period');

                // Create a project deletion request
                ORM::factory('Project_Deletion_Request')
                    ->set('project_id', $project_id)
                    ->set('due_on', date('Y-m-d H:i:s', time() + $grace_period))
                    ->save();
            }
            else
            {
                // Delete the project permanently
                $project_model->delete();
            }

            // Everything was going fine, commit
            $this->commit_transaction(FALSE);
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction(FALSE);

            // Re-throw the exception
            throw $e;
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
            throw new Kohana_Exception(
                'Can not find the project with id: :project_id.', array(
                ':project_id' => $project_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        $project_model
            ->set('archived', 1)
            ->save();
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
            throw new Kohana_Exception(
                'Can not find the project with id: :project_id.', array(
                ':project_id' => $project_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        // Begin transaction
        $this->begin_transaction(FALSE);

        try
        {
            $project_model
                ->set('archived', 0)
                ->set('deleted', 0)
                ->save();

            // Remove any deletion requests for this project
            ORM::factory('Project_Deletion_Request')->delete_all($project_id);

            // Everything was going fine, commit
            $this->commit_transaction(FALSE);
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction(FALSE);
        }
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
        // Begin transaction
        $this->begin_transaction();

        try
        {
            // Add user to the project(s)
            $this->project_model()->add_user($project_id, $user_id);

            if (self::$use_cache)
            {
                $this->cache()->sync_projects($user_id);
            }

            // Everything was going fine, commit
            $this->commit_transaction();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();

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
        // Begin transaction
        $this->begin_transaction();

        try
        {
            $this->project_model()->remove_user($project_id, $user_id);

            if (self::$use_cache)
            {
                $this->cache()->sync_projects($user_id);
            }

            // Everything was going fine, commit
            $this->commit_transaction();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Check if a user is linked to one or more projects (alias for is_user_linked)
     *
     * @param   int|array   $project_id
     * @param   int         $user_id
     * @return  bool
     */
    public function is_user_linked($project_id, $user_id)
    {
        return $this->has_access($project_id, $user_id);
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
            $project_ids = $this->cache()->load_projects($user_id);

            if ( ! isset($project_ids))
            {
                // Load from database
                $project_ids = $this->project_model()->get_user_project_ids($user_id);

                // Sync cache
                $this->cache()->sync_projects($user_id, $project_ids);
            }
        }
        else
        {
            // Load from database
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
        $this->project_model()->garbage_collector(time());
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
     * Create a singleton instance of the class
     *
     * @return	Project_Manager
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
