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
     * @var Account_Cache           A singleton instance
     */
    protected static $_instance;

    /**
     * @var string                  The key prefix to use for caching user related data
     */
    public static $user_prefix = 'usr_acc';

    /**
     * @var string                  The key prefix to use for caching subscription related data
     */
    public static $subscr_prefix = 'subscr';

    /**
     * @var int                     The lifetime of cache keys in seconds
     */
    public static $expiration = 7200;

    /**
     * @var object                  The cache client
     */
    protected $_client;

    /**
     * Construct
     */
    protected function __construct()
    {
        $this->_client = Redis_Client::instance();
    }

    /**
     * Sync user account ids with database
     *
     * @param   int     $user_id
     * @param   array   $account_ids
     */
    public function sync_accounts($user_id, $account_ids = NULL)
    {
        $account_ids = isset($account_ids)
            ? $account_ids
            : ORM::factory('Account')->get_user_account_ids($user_id);

        $this->_save_user_data($user_id, 'a', $account_ids);
    }

    /**
     * Load the user accounts from cache
     *
     * @param   int     $user_id
     * @return  mixed
     */
    public function load_accounts($user_id)
    {
        return $this->_load_user_data($user_id, 'a');
    }

    /**
     * Sync user permissions with database
     *
     * @param   int     $user_id
     * @param   int     $account_id
     * @param   array   $permissions
     */
    public function sync_account_permissions($user_id, $account_id, $permissions = NULL)
    {
        $permissions = isset($permissions)
            ? $permissions
            : ORM::factory('Account')->get_permissions($account_id, $user_id);

        $this->_save_user_data($user_id, 'a'.$account_id, $permissions);
    }

    /**
     * Load the user accounts from cache
     *
     * @param   int     $user_id
     * @param   int     $account_id
     * @return  array
     */
    public function load_account_permissions($user_id, $account_id)
    {
        return $this->_load_user_data($user_id, 'a'.$account_id);
    }

    /**
     * Sync user projects with database
     *
     * @param   int     $user_id
     * @param   array   $project_ids
     */
    public function sync_projects($user_id, $project_ids = NULL)
    {
        $project_ids = $project_ids ?: ORM::factory('Project')->get_user_project_ids($user_id);
        $this->_save_user_data($user_id, 'p', $project_ids);
    }

    /**
     * Load the user projects from cache
     *
     * @param   int     $user_id
     * @return  mixed
     */
    public function load_projects($user_id)
    {
        return $this->_load_user_data($user_id, 'p');
    }

    /**
     * Save user related data to cache
     *
     * @param   int     $user_id
     * @param   string  $field
     * @param   array   $data
     */
    protected function _save_user_data($user_id, $field, $data)
    {
        $key = self::$user_prefix.$user_id;
        $this->_client->hSet($key, $field, json_encode($data));

        if (self::$expiration)
        {
            $this->_client->expire($key, self::$expiration);
        }
    }

    /**
     * Load user related data from cache
     *
     * @param   int     $user_id
     * @param   string  $field
     * @return  mixed
     */
    protected function _load_user_data($user_id, $field)
    {
        $key = self::$user_prefix.$user_id;
        $data = $this->_client->hGet($key, $field);

        return $data !== FALSE
            ? json_decode($data)
            : NULL;
    }

    /**
     * Delete data for the given user from cache
     *
     * @param   int     $user_id
     */
    public function delete_user_data($user_id)
    {
        $key = self::$user_prefix.$user_id;
        $this->_client->del($key);
    }

    /**
     * Sync subscription data with database
     *
     * @param   int     $account_id
     * @param   array   $subscription_data
     * @throws  Kohana_Exception
     */
    public function sync_subscription_data($account_id, $subscription_data = NULL)
    {
        if ( ! isset($subscription_data))
        {
            $subscription_model = ORM::factory('Subscription')
                ->where('account_id', '=', $account_id)
                ->find();

            if ( ! $subscription_model->loaded())
            {
                throw new Kohana_Exception(
                    'Can not find the subscription for account id :account_id.', array(
                    ':account_id' => $account_id
                ), Kohana_Exception::E_RESOURCE_NOT_FOUND);
            }

            $subscription_data = $subscription_model->as_array();
        }

        unset($subscription_data['account_id']);
        $key = self::$subscr_prefix.$account_id;
        $this->_client->hMset($key, $subscription_data);

        if (self::$expiration)
        {
            $this->_client->expire($key, self::$expiration);
        }
    }

    /**
     * Load subscription data from cache
     *
     * @param   int     $account_id
     * @return  mixed
     */
    public function load_subscription_data($account_id)
    {
        $key = self::$subscr_prefix.$account_id;
        $data = $this->_client->hGetAll($key);

        if ( ! empty($data))
        {
            $data['account_id'] = $account_id;
        }
        else
        {
            $data = NULL;
        }
        return $data;
    }

    /**
     * Delete subscription data from cache
     *
     * @param   $account_id
     */
    public function delete_subscription_data($account_id)
    {
        $key = self::$subscr_prefix.$account_id;
        $this->_client->delete($key);
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
