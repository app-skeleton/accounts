<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Manager extends Account_Service_Manager {

    /**
     * @var ORM                 An instance of the Model_Account class
     */
    protected $_account_model;

    /**
     * Create a new user and account
     *
     * @param   int     $user_id
     * @param   array   $user_values
     * @param   array   $account_values
     * @param   string  $plan
     * @throws  Auth_Exception
     * @throws  Validation_Exception
     * @throws  Account_Exception
     * @throws  Exception
     * @return  array
     */
    public function create_account($user_id, $user_values, $account_values, $plan)
    {
        // Initialize models data
        $models_data = array();

        // Get the user manager
        $user_manager = User_Manager::instance();

        // Get the subscription manager
        $subscription_manager = Subscription_Manager::instance();

        // Start the transaction
        $this->begin_transaction();

        try
        {
            if (empty($user_id))
            {
                // Sign up the user
                $models_data = $user_manager->signup_user($user_values);
                $user_id = $models_data['user']['user_id'];

                // Make sure we have an account name
                if (empty($account_values['name']))
                {
                    $account_values['name'] = trim(Arr::get($user_values, 'first_name').' '.Arr::get($user_values, 'last_name'))."'s ".APPNAME;
                }
            }
            else
            {
                // Make sure the user has no account
                if ($this->user_has_account($user_id))
                {
                    throw new Account_Exception(Account_Exception::E_USER_HAS_ACCOUNT);
                }

                // Make sure we have an account name
                if (empty($account_values['name']))
                {
                    $user_data = $user_manager->get_user_data($user_id);
                    $account_values['name'] = trim(Arr::get($user_data, 'first_name').' '.Arr::get($user_data, 'last_name'))."'s ".APPNAME;
                }
            }

            // Prepare account values
            $account_values = array_merge($account_values, array(
                'owner_id' => $user_id,
                'created_by' => $user_id,
                'created_on' => date('Y-m-d H:i:s')
            ));

            // Save the account
            $account_model = ORM::factory('Account')
                ->values($account_values)
                ->save();

            $account_id = $account_model->pk();
            $models_data['account'] = $account_model->as_array();

            // Add the user to the account
            $account_model->add_user($account_id, $user_id, NULL, Model_Account::STATUS_USER_LINKED);

            // Grant permissions to the user on the account
            $permissions = array(
                Model_Account::PERM_OWNER,
                Model_Account::PERM_ACCOUNT_MANAGER,
                Model_Account::PERM_ADMIN,
                Model_Account::PERM_CREATE_PROJECTS
            );

            $account_model->grant_permission($account_id, $user_id, $permissions);

            // Sync cache
            if (self::$use_cache)
            {
                $this->cache()->sync_accounts($user_id);
                $this->cache()->sync_account_permissions($user_id, $account_id, $permissions);
            }

            // Save the subscription
            $models_data['subscription'] = $subscription_manager->create_subscription($account_id, $plan);

            // Everything was going fine, commit
            $this->commit_transaction();

            return $models_data;
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
     * Update an account
     *
     * @param   int     $account_id
     * @param   array   $values
     * @throws  Validation_Exception
     * @throws  Kohana_Exception
     * @return  array
     */
    public function update_account($account_id, $values)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Kohana_Exception(
                'Can not find the account with id :account_id.', array(
                ':account_id' => $account_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        try
        {
            // Update the account
            $account_model
                ->values($values)
                ->save();

            return $account_model->as_array();
        }
        catch (ORM_Validation_Exception $e)
        {
            $errors = $e->errors('models/'.i18n::lang().'/account', FALSE);
            throw new Validation_Exception($errors);
        }
    }

    /**
     * Rename an account
     *
     * @param   int     $account_id
     * @param   string  $name
     */
    public function rename_account($account_id, $name)
    {
        $this->update_account($account_id, array(
            'name' => $name
        ));
    }

    /**
     * Get data about an account
     *
     * @param   int $account_id
     * @throws  Kohana_Exception
     * @return  array
     */
    public function get_account_data($account_id)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Kohana_Exception(
                'Can not find the account with id :account_id.', array(
                ':account_id' => $account_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        return $account_model->as_array();
    }

    /**
     * Delete an account
     *
     * @param   int     $account_id
     * @throws  Kohana_Exception
     */
    public function delete_account($account_id)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Kohana_Exception(
                'Can not find the account with id :account_id.', array(
                ':account_id' => $account_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        // Delete account
        $account_model->delete();
    }

    /**
     * Return the user id of the owner
     *
     * @param   int     $account_id
     * @throws  Kohana_Exception
     * @return  mixed
     */
    public function get_account_owner_id($account_id)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Kohana_Exception(
                'Can not find the account with id :account_id.', array(
                ':account_id' => $account_id
            ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
        }

        // Return owner id
        return $account_model->get('owner_id');
    }

    /**
     * Get account owner
     *
     * @param   int     $account_id
     * @return  array
     */
    public function get_account_owner_data($account_id)
    {
        return $this->account_model()->get_account_owner_data($account_id);
    }

    /**
     * Make the given user the owner of the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @throws  Exception
     */
    public function change_account_owner($account_id, $user_id)
    {
        // Start the transaction
        $this->begin_transaction();

        try
        {
            // Get the current owner
            $owner_data = $this->get_account_owner_data($account_id);

            // Make the given user the new owner
            $this->update_account($account_id, array(
                'owner_id' => $user_id
            ));

            // Add the `owner` permission to the given user
            $this->grant_permission($account_id, $user_id, Model_Account::PERM_OWNER);

            // Remove the `owner` permission from the previous owner
            $this->revoke_permission($account_id, $owner_data['user_id'], Model_Account::PERM_OWNER);

            // Everything is fine, commit
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
     * Add a user to the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @param   int     $inviter_id
     * @param   string  $status
     * @throws  Exception
     */
    public function add_user($account_id, $user_id, $inviter_id, $status)
    {
        // Begin transaction
        $this->begin_transaction();

        try
        {
            $this->account_model()->add_user($account_id, $user_id, $inviter_id, $status);
            self::$use_cache && $this->cache()->sync_accounts($user_id);

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
     * Remove a user from the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @param   int     $remover_id
     * @param   string  $leaving_message
     * @throws  Account_Exception
     * @throws  Exception
     */
    public function remove_user($account_id, $user_id, $remover_id, $leaving_message = NULL)
    {
        $self_remove = $remover_id == $user_id;
        $send_leaving_email = $self_remove && ! empty($leaving_message);
        $status = $self_remove
            ? Model_Account::STATUS_USER_LEFT
            : Model_Account::STATUS_USER_REMOVED;

        // Get the user manager
        $user_manager = User_Manager::instance();

        // Get data about the account owner
        $owner_data = $this->get_account_owner_data($account_id);

        // Make sure the user is not the account owner
        if ($user_id == $owner_data['user_id'])
        {
            throw new Account_Exception(Account_Exception::E_OWNER_CAN_NOT_BE_REMOVED);
        }

        // Check if the user removed himself and wants to send a leaving email to the account owner
        if ($send_leaving_email)
        {
            // Prepare data for the leaving email
            $invitee_data = $user_manager->get_user_data($user_id);

            $data = array(
                'account_owner_data'    => $owner_data,
                'invitee_data'          => $invitee_data,
                'message'               => $leaving_message
            );
        }

        // Begin transaction
        $this->begin_transaction();

        try
        {
            // Update user status on the account
            $this->set_user_status($account_id, $user_id, $status);

            // Revoke all permissions from the user on this account
            $this->revoke_all_permissions($account_id, $user_id);

            // Remove all invitation links for the user on this account
            ORM::factory('Account_Invitation_Link')->delete_all($account_id, $user_id);

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

        if ($send_leaving_email)
        {
            // Send the leaving email
            $leaving_email = Account_Leaving_Email::factory($owner_data['email'], $data);
            $leaving_email->send();
        }
    }

    /**
     * Get data about the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_data($account_id, $user_id)
    {
        return $this->account_model()->get_user_data($account_id, $user_id);
    }

    /**
     * Get the accounts of the given user
     *
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_accounts($user_id)
    {
        return $this->account_model()->get_user_accounts($user_id);
    }

    /**
     * Get the account ids the given user is linked to
     *
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_account_ids($user_id)
    {
        if (self::$use_cache)
        {
            $user_account_ids = $this->cache()->load_accounts($user_id);

            // Check if data from cache is not NULL
            if ( ! isset($user_account_ids))
            {
                // Load account ids from DB
                $user_account_ids = $this->account_model()->get_user_account_ids($user_id);

                // Sync the cache
                $this->cache()->sync_accounts($user_id, $user_account_ids);
            }
        }
        else
        {
            // Load account ids from DB
            $user_account_ids = $this->account_model()->get_user_account_ids($user_id);
        }

        return $user_account_ids;
    }

    /**
     * Set the status of the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @param   string  $status
     * @throws  Exception
     */
    public function set_user_status($account_id, $user_id, $status)
    {
        $this->begin_transaction();

        try
        {
            $this->account_model()->set_user_status($account_id, $user_id, $status);
            self::$use_cache && $this->cache()->sync_accounts($user_id);

            // Everything was going fine, commit
            $this->commit_transaction();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();
        }
    }

    /**
     * Get the status of the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  mixed
     */
    public function get_user_status($account_id, $user_id)
    {
        return $this->account_model()->get_user_status($account_id, $user_id);
    }

    /**
     * Check if a user is linked to the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  bool
     */
    public function is_user_linked($account_id, $user_id)
    {
        return $this->get_user_status($account_id, $user_id) == Model_Account::STATUS_USER_LINKED;
    }

    /**
     * Check if a user is invited to the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  bool
     */
    public function is_user_invited($account_id, $user_id)
    {
        return $this->get_user_status($account_id, $user_id) == Model_Account::STATUS_USER_INVITED;
    }

    /**
     * Check if a user was removed the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  bool
     */
    public function is_user_removed($account_id, $user_id)
    {
        return $this->get_user_status($account_id, $user_id) == Model_Account::STATUS_USER_REMOVED;
    }

    /**
     * Check if a user left the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  bool
     */
    public function is_user_left($account_id, $user_id)
    {
        return $this->get_user_status($account_id, $user_id) == Model_Account::STATUS_USER_LEFT;
    }

    /**
     * Get data about the user who invited the given user to the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_inviter_data($account_id, $user_id)
    {
       return $this->account_model()->get_user_inviter_data($account_id, $user_id);
    }

    /**
     * Get the teammates of the given user on the given account (teammates have at least one project in common)
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_teammates($account_id, $user_id)
    {
        return $this->account_model()->get_user_teammates($account_id, $user_id);
    }

    /**
     * Get the number of teammates of the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  int
     */
    public function get_user_teammates_count($account_id, $user_id)
    {
        return $this->account_model()->get_user_teammates_count($account_id, $user_id);
    }

    /**
     * Grant one or more permissions to the given user on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     * @param   string|array    $permission
     * @throws  Exception
     */
    public function grant_permission($account_id, $user_id, $permission)
    {
        // Make sure `permission` is an array
        $permission = (array) $permission;

        // Some permissions automatically implies others
        if (in_array(Model_Account::PERM_ADMIN, $permission))
        {
            $permission = array_unique(array_merge($permission, array(
                Model_Account::PERM_CREATE_PROJECTS
            )));
        }

        if (in_array(Model_Account::PERM_OWNER, $permission))
        {
            $permission = array_unique(array_merge($permission, array(
                Model_Account::PERM_CREATE_PROJECTS,
                Model_Account::PERM_ADMIN,
                Model_Account::PERM_ACCOUNT_MANAGER
            )));
        }

        // Begin transaction
        $this->begin_transaction();

        try
        {
            $this->account_model()->grant_permission($account_id, $user_id, $permission);
            self::$use_cache && $this->cache()->sync_account_permissions($user_id, $account_id);

            // Everything is fine, commit
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
     * Revoke one or more permissions from the given user on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     * @param   string|array    $permission
     * @throws  Exception
     */
    public function revoke_permission($account_id, $user_id, $permission)
    {
        // Begin transaction
        $this->begin_transaction();

        try
        {
            $this->account_model()->revoke_permission($account_id, $user_id, $permission);
            self::$use_cache && $this->cache()->sync_account_permissions($user_id, $account_id);

            // Everything is fine, commit
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
     * Revoke all permission from the given user on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     * @throws  Exception
     */
    public function revoke_all_permissions($account_id, $user_id)
    {
        // Begin transaction
        $this->begin_transaction();

        try
        {
            $this->account_model()->revoke_all_permissions($account_id, $user_id);
            self::$use_cache && $this->cache()->sync_account_permissions($user_id, $account_id, array());

            // Everything is fine, commit
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
     * Get the permissions the given user has on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_permissions($account_id, $user_id)
    {
        if (self::$use_cache)
        {
            $permissions = $this->cache()->load_account_permissions($user_id, $account_id);

            if ( ! isset($permissions))
            {
                // Load from DB
                $permissions = $this->account_model()->get_permissions($account_id, $user_id);

                // Save to cache
                $this->cache()->sync_account_permissions($user_id, $account_id, $permissions);
            }
        }
        else
        {
            // Load from DB
            $permissions = $this->account_model()->get_permissions($account_id, $user_id);
        }

        return $permissions;
    }

    /**
     * Check if the given user has one ore more permissions on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     * @param   string|array    $permission
     * @return  bool
     */
    public function has_permission($account_id, $user_id, $permission)
    {
        $permission = (array)$permission;
        $permissions = $this->get_permissions($account_id, $user_id);

        return array_diff($permission, $permissions) == array();
    }

    /**
     * Check if the given user has access to the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  bool
     */
    public function has_access($account_id, $user_id)
    {
        $account_ids = $this->get_user_account_ids($user_id);
        return in_array($account_id, $account_ids);
    }

    /**
     * Check if there is an account created by the given user
     *
     * @param   int     $user_id
     * @return  bool
     */
    public function user_has_account($user_id)
    {
        return $this->account_model()->user_has_account($user_id);
    }

    /**
     * Do garbage collection
     */
    public function garbage_collector()
    {
        $this->account_model()->garbage_collector(time());
    }

    /**
     * Return an instance of the Model_Account class
     *
     * @return ORM
     */
    public function account_model()
    {
        if ( ! isset($this->_account_model))
        {
            $this->_account_model = ORM::factory('Account');
        }

        return $this->_account_model;
    }
}

// END Kohana_Account_Manager
