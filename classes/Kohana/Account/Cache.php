<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Cache  {

    /**
     * @var Account_Cache       A singleton instance
     */
    protected static $_instance;

    /**
     * @var string              The key prefixes to use with caching
     */
    public static $prefix = 'usr_acc';

    /**
     * @var int                 The lifetime of cache keys
     */
    public static $expiration = 3600;

    /**
     * @var object              The cache client
     */
    protected $_client;

    /**
     * @var bool                Whether to save to cache, only if previous version of data exists
     */
    protected $_update_only = TRUE;

    /**
     * Construct
     */
    protected function __construct()
    {
        $this->_client = Redis_Client::instance();
    }

    /**
     * Add a new account id to the user data
     *
     * @param   int     $user_id
     * @param   int     $account_id
     */
    public function add_account($user_id, $account_id)
    {
        $account_ids = (array)$this->load_accounts($user_id);
        array_push($account_ids, $account_id);
        $account_ids = array_values(array_unique($account_ids));

        $this->save_accounts($user_id, $account_ids);
    }

    /**
     * Remove an account id from the user data
     *
     * @param   int     $user_id
     * @param   int     $account_id
     */
    public function remove_account($user_id, $account_id)
    {
        $account_ids = (array)$this->load_accounts($user_id);
        $account_ids = array_values(array_diff($account_ids, array($account_id)));

        $this->save_accounts($user_id, $account_ids);
    }

    /**
     * Save the user accounts in the cache
     *
     * @param   int     $user_id
     * @param   array   $account_ids
     */
    public function save_accounts($user_id, $account_ids)
    {
        $this->_save($user_id, 'a', $account_ids);
    }

    /**
     * Load the user accounts from cache
     *
     * @param   int     $user_id
     * @return  mixed
     */
    public function load_accounts($user_id)
    {
        return $this->_load($user_id, 'a');
    }

    /**
     * Give new permissions to the given user on the given account
     *
     * @param   int     $user_id
     * @param   int     $account_id
     * @param   array   $permissions
     */
    public function grant_account_permission($user_id, $account_id, $permissions)
    {
        $account_permissions = (array)$this->load_account_permissions($user_id, $account_id);
        $account_permissions = array_values(array_unique(array_merge($account_permissions, (array)$permissions)));

        $this->save_account_permissions($user_id, $account_id, $account_permissions);
    }

    /**
     * Revoke permissions from the given user on the given account
     *
     * @param   int     $user_id
     * @param   int     $account_id
     * @param   array   $permissions
     */
    public function revoke_account_permission($user_id, $account_id, $permissions)
    {
        $account_permissions = (array)$this->load_account_permissions($user_id, $account_id);
        $account_permissions = array_values(array_diff($account_permissions, (array)$permissions));

        $this->save_account_permissions($user_id, $account_id, $account_permissions);
    }

    /**
     * Revoke all permissions from the given user on the given account
     *
     * @param   int     $user_id
     * @param   int     $account_id
     */
    public function revoke_all_account_permissions($user_id, $account_id)
    {
        $this->save_account_permissions($user_id, $account_id, array());
    }

    /**
     * Save the user accounts in the cache
     *
     * @param   int     $user_id
     * @param   int     $account_id
     * @param   array   $permissions
     */
    public function save_account_permissions($user_id, $account_id, $permissions)
    {
        $this->_save($user_id, 'a'.$account_id, $permissions);
    }

    /**
     * Load the user accounts from cache
     *
     * @param   int     $user_id
     * @param   int     $account_id
     * @return  mixed
     */
    public function load_account_permissions($user_id, $account_id)
    {
        return $this->_load($user_id, 'a'.$account_id);
    }

    /**
     * Add new projects to the given user
     *
     * @param   int         $user_id
     * @param   int|array   $project_id
     */
    public function add_project($user_id, $project_id)
    {
        $project_ids = (array)$this->load_projects($user_id);
        $project_ids = array_values(array_unique(array_merge($project_ids, (array)$project_id)));

        $this->save_projects($user_id, $project_ids);
    }

    /**
     * Remove one or more projects from the given user
     *
     * @param   int         $user_id
     * @param   int|array   $project_id
     */
    public function remove_project($user_id, $project_id)
    {
        $project_ids = (array)$this->load_projects($user_id);
        $project_ids = array_values(array_diff($project_ids, (array)$project_id));

        $this->save_projects($user_id, $project_ids);
    }

    /**
     * Save the user projects in the cache
     *
     * @param   int     $user_id
     * @param   array   $project_ids
     */
    public function save_projects($user_id, $project_ids)
    {
        $this->_save($user_id, 'p', $project_ids);
    }

    /**
     * Load the user projects from cache
     *
     * @param   int     $user_id
     * @return  mixed
     */
    public function load_projects($user_id)
    {
        return $this->_load($user_id, 'p');
    }


    /**
     * Save data to cache
     *
     * @param   int     $user_id
     * @param   string  $field
     * @param   array   $data
     */
    protected function _save($user_id, $field, $data)
    {
        if ( ! $this->update_only() || $this->data_exists($user_id))
        {
            $key = self::$prefix.$user_id;
            $this->_client->hSet($key, $field, json_encode($data));

            if (self::$expiration)
            {
                $this->_client->expire($key, self::$expiration);
            }
        }
    }

    /**
     * Load data from cache
     *
     * @param   int     $user_id
     * @param   string  $field
     * @return  mixed
     */
    protected function _load($user_id, $field)
    {
        $key = self::$prefix.$user_id;
        $data = $this->_client->hGet($key, $field);

        return $data !== FALSE ? json_decode($data) : NULL;
    }

    /**
     * Check if cached data for the given user exists
     *
     * @param   int     $user_id
     * @return  bool
     */
    public function data_exists($user_id)
    {
        $key = self::$prefix.$user_id;
        return $this->_client->exists($key);
    }

    /**
     * Delete data for the given user from cache
     *
     * @param   int     $user_id
     */
    public function delete_data($user_id)
    {
        $key = self::$prefix.$user_id;
        $this->_client->del($key);
    }

    /**
     * Enter multi-mode
     */
    public function multi()
    {
        $this->_client->multi();
    }

    /**
     * Execute queued commands
     */
    public function exec()
    {
        $this->_client->exec();
    }

    /**
     * Cancel a transaction
     */
    public function discard()
    {
        $this->_client->discard();
    }

    /**
     * Set/Get the `update_ony` property
     *
     * @param   bool    $update_only
     * @return  bool
     */
    public function update_only($update_only = NULL)
    {
        if (isset($update_only))
        {
            $this->_update_only = $update_only;
        }

        return $this->_update_only;
    }

    /**
     * Returns a singleton instance of the class
     *
     * @return  Account_Cache
     */
    public static function instance()
    {
        if ( ! Account_Cache::$_instance instanceof Account_Cache)
        {
            Account_Cache::$_instance = new Account_Cache();
        }

        return Account_Cache::$_instance;
    }
}

// END Kohana_Account_Cache