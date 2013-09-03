<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Manager {

    /**
     * @var Account_Manager     A singleton instance
     */
    protected static $_instance;

    /**
     * @var ORM                 An instance of the Model_Account class
     */
    protected $_account_model;

    /**
     * @var bool                Whether to cache the users accounts, and user account permissions (these are most frequently queried)
     */
    public static $use_cache = TRUE;

    /**
     * Status constants
     */
    const STATUS_USER_INVITED       = 'invited';
    const STATUS_USER_LINKED        = 'linked';
    const STATUS_USER_REMOVED       = 'removed';
    const STATUS_USER_LEFT          = 'left';
    const PERM_OWNER                = 'owner';
    const PERM_ADMIN                = 'admin';
    const PERM_ACCOUNT_MANAGER      = 'account_manager';
    const PERM_CREATE_PROJECTS      = 'create_projects';

    /**
     * Create an account
     *
     * @param   int     $user_id
     * @param   array   $values
     * @throws  Exception
     * @throws  User_Validation_Exception
     * @throw   ORM_Validation_Exception
     * @return  array
     */
    public function create_account($user_id, $values)
    {
        $account_model = ORM::factory('Account');

        if ( ! isset($user_id))
        {
            // We have to create a new user
            $user_errors = array();
            $identity_errors = array();

            // Create the user and identity models
            $user_model = ORM::factory('User');
            $identity_model = ORM::factory('Identity');

            // Get user validation errors
            $user_model->values($values);

            try
            {
                $user_model->check();
            }
            catch (ORM_Validation_Exception $e)
            {
                $user_errors = $e->errors('models/'.i18n::lang().'/user', FALSE);
            }

            // Get identity validation errors
            $identity_model->values($values);

            try
            {
                $identity_model->check($identity_model->get_password_validation($values));
            }
            catch (ORM_Validation_Exception $e)
            {
                $identity_errors = $e->errors('models/'.i18n::lang().'/user', FALSE);
                if (isset($identity_errors['_external']))
                {
                    $identity_external_errors = $identity_errors['_external'];
                    unset($identity_errors['_external']);
                    $identity_errors = array_merge($identity_errors, $identity_external_errors);
                }
            }

            // Merge user and identity validation errors
            $errors = array_merge($user_errors, $identity_errors);

            // If validation fails, throw an exception
            if ($errors)
            {
                throw new User_Validation_Exception($errors);
            }

            // No validation errors, make sure we have an account name
            $account_name = ! empty($values['account_name'])
                ? $values['account_name']
                : trim($values['first_name'].' '.$values['last_name'])."'s ".APPNAME;
        }
        else
        {
            // User already exists, make sure we have an account name
            if (empty($values['account_name']))
            {
                $user_manager = User_Manager::instance();
                $user_data = $user_manager->get_user_data($user_id);
                $account_name = trim($user_data['first_name'].' '.$user_data['last_name'])."'s ".APPNAME;
            }
            else
            {
                $account_name = $values['account_name'];
            }
        }

        // Start the transaction
        $account_model->begin();
        $models_data = array();

        try
        {
            if ( ! isset($user_id))
            {
                // Save the user
                $user_model->save();
                $user_id = $user_model->pk();
                $models_data['user'] = $user_model->as_array();

                // Setup identity
                $identity_model->set('user_id', $user_id);
                $identity_model->set('status', 'active');

                // Save the identity
                $identity_model->save();
                $models_data['identity'] = $identity_model->as_array();
            }

            $account_values = array(
                'name' => $account_name,
                'owner_id' => $user_id,
                'created_by' => $user_id,
                'created_on' => date('Y-m-d H:i:s')
            );

            // Create the account
            $account_model
                ->values($account_values)
                ->save();

            $account_id = $account_model->pk();
            $models_data['account'] = $account_model->as_array();

            // Add the user to the account
            $account_model->add_user($account_id, $user_id, NULL, self::STATUS_USER_LINKED);

            // Grant permissions to the user on the account
            $account_model->grant_permission($account_id, $user_id, array(
                self::PERM_OWNER,
                self::PERM_ACCOUNT_MANAGER,
                self::PERM_ADMIN,
                self::PERM_CREATE_PROJECTS
            ));

            // Everything was going fine, commit
            $account_model->commit();

            return $models_data;
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $account_model->rollback();

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
     * @throws  Account_Exception
     * @return  array
     */
    public function update_account($account_id, $values)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Account_Exception('Can not load account with id: '.$account_id);
        }

        try
        {
            // Update the account
            $account_model->values($values)->save();

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
     * @throws  Account_Exception
     * @return  array
     */
    public function get_account_data($account_id)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Account_Exception('Can not load account with id: '.$account_id);
        }

        return $account_model->as_array();
    }

    /**
     * Delete an account
     *
     * @param   int     $account_id
     * @throws  Account_Exception
     */
    public function delete_account($account_id)
    {
        $account_model = ORM::factory('Account', $account_id);

        if ( ! $account_model->loaded())
        {
            throw new Account_Exception('Can not load account with id: '.$account_id);
        }

        // Delete account
        $account_model->delete();
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
        $account_model = $this->account_model();
        $account_model->begin();
        $account_cache = self::$use_cache ? Account_Cache::instance() : NULL;

        try
        {
            // Get the current owner
            $owner_data = $this->get_account_owner_data($account_id);

            // Make the given user the new owner
            $this->update_account($account_id, array(
                'owner_id' => $user_id
            ));

            // Add the `owner` permission to the given user
            $account_model->grant_permission($account_id, $user_id, self::PERM_OWNER);

            if (self::$use_cache)
            {
                $account_cache->grant_account_permission($user_id, $account_id, self::PERM_OWNER);
            }

            // Remove the 'owner' permission from the previous owner
            $account_model->revoke_permission($account_id, $owner_data['user_id'], self::PERM_OWNER);

            if (self::$use_cache)
            {
                $account_cache->revoke_account_permission($owner_data['user_id'], $account_id, self::PERM_OWNER);
            }

            // Everything is fine, commit
            $account_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the database, rollback
            $account_model->rollback();

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
        $account_model = $this->account_model();
        $account_model->begin();
        $account_model->add_user($account_id, $user_id, $inviter_id, $status);

        try
        {
            if (self::$use_cache)
            {
                $account_cache = Account_Cache::instance();
                $account_cache->add_account($user_id, $account_id);
            }

            // Everything is fine, commit
            $account_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the cache, rollback
            $account_model->rollback();

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
        $status = $self_remove ? self::STATUS_USER_LEFT : self::STATUS_USER_REMOVED;

        // Get data about the account owner
        $owner_data = $this->get_account_owner_data($account_id);

        // Make sure the user is not the account owner
        if ($user_id == $owner_data['user_id'])
        {
            throw new Account_Exception('The account owner can not be removed.');
        }

        // Check if the user removed himself and wants to send a leaving email to his inviter
        if ($send_leaving_email)
        {
            // Prepare data for the leaving email
            $invitee_data = User_Manager::instance()->get_user_data($user_id);

            $data = array(
                'account_owner_data'    => $owner_data,
                'invitee_data'          => $invitee_data,
                'message'               => $leaving_message
            );
        }

        $account_model = $this->account_model();
        $account_model->begin();
        $account_cache = self::$use_cache ? Account_Cache::instance() : NULL;

        try
        {
            // Update user status
            $account_model->set_user_status($account_id, $user_id, $status);

            // Remove all invitation links for the user on this account
            ORM::factory('Account_Invitation_Link')->delete_all($account_cache, $user_id);

            if (self::$use_cache)
            {
                // Update the cache
                $account_cache->remove_account($user_id, $account_id);
            }

            // Revoke all permission from the user on this account
            $account_model->revoke_all_permissions($account_id, $user_id);

            if (self::$use_cache)
            {
                $account_cache->revoke_all_account_permission($user_id, $account_id);
            }

            // Everything is fine, commit
            $account_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the database, rollback
            $account_model->rollback();

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
            $account_cache = Account_Cache::instance();
            $account_cache->update_only(FALSE);
            $user_account_ids = $account_cache->load_accounts($user_id);

            // Check if the data from cache is an empty array (in case of nonexistent data)
            if ( ! isset($user_account_ids))
            {
                // Load from DB
                $user_account_ids = $this->account_model()->get_user_account_ids($user_id);

                // Save to cache
                $account_cache->save_accounts($user_id, $user_account_ids);
            }
        }
        else
        {
            // Load from DB
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
     */
    public function set_user_status($account_id, $user_id, $status)
    {
        $this->account_model()->set_user_status($account_id, $user_id, $status);
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
        return $this->get_user_status($account_id, $user_id) == self::STATUS_USER_LINKED;
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
        return $this->get_user_status($account_id, $user_id) == self::STATUS_USER_INVITED;
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
        return $this->get_user_status($account_id, $user_id) == self::STATUS_USER_REMOVED;
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
        return $this->get_user_status($account_id, $user_id) == self::STATUS_USER_LEFT;
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
        $account_model = $this->account_model();
        $account_model->begin();
        $account_model->grant_permission($account_id, $user_id, $permission);

        try
        {
            if (self::$use_cache)
            {
                $account_cache = Account_Cache::instance();
                $account_cache->grant_account_permission($user_id, $account_id, $permission);
            }

            // Everything is fine, commit
            $account_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the cache, rollback
            $account_model->rollback();

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
        $account_model = $this->account_model();
        $account_model->begin();
        $account_model->revoke_permission($account_id, $user_id, $permission);

        try
        {
            if (self::$use_cache)
            {
                $account_cache = Account_Cache::instance();
                $account_cache->revoke_account_permission($user_id, $account_id, $permission);
            }

            // Everything is fine, commit
            $account_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the cache, rollback
            $account_model->rollback();

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
        $account_model = $this->account_model();
        $account_model->begin();
        $account_model->revoke_all_permissions($account_id, $user_id);

        try
        {
            if (self::$use_cache)
            {
                $account_cache = Account_Cache::instance();
                $account_cache->revoke_all_account_permissions($user_id, $account_id);
            }

            // Everything is fine, commit
            $account_model->commit();
        }
        catch (Exception $e)
        {
            // Problem with the cache, rollback
            $account_model->rollback();

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
            $account_cache = Account_Cache::instance();
            $account_cache->update_only(FALSE);
            $user_permissions = $account_cache->load_account_permissions($user_id, $account_id);

            if ( ! isset($user_permissions))
            {
                // Load from DB
                $user_permissions = $this->account_model()->get_permissions($account_id, $user_id);

                // Save to cache
                $account_cache->save_account_permissions($user_id, $account_id, $user_permissions);
            }
        }
        else
        {
            // Load from DB
            $user_permissions = $this->account_model()->get_permissions($account_id, $user_id);
        }

        return $user_permissions;
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
     * Check if there is an account created by the user with the given email address
     *
     * @param   int     $email
     * @return  bool
     */
    public function unique_email($email)
    {
        return $this->account_model()->unique_email($email);
    }

    /**
     * Do garbage collection
     */
    public function garbage_collector()
    {
        // Get the grace period
        $grace_period = Kohana::$config->load('account')->get('account_cancellation_grace_period');

        // Calculate date limit
        $start_date = date('Y-m-d H:i:s', time() - $grace_period * 24 * 3600);

        $this->account_model()->garbage_collector($start_date);
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

    /**
     * Returns a singleton instance of the class
     *
     * @return  Account_Manager
     */
    public static function instance()
    {
        if ( ! Account_Manager::$_instance instanceof Account_Manager)
        {
            Account_Manager::$_instance = new Account_Manager();
        }

        return Account_Manager::$_instance;
    }
}

// END Kohana_Account_Manager
