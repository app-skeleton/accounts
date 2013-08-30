<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Subscription_Cache  {

    /**
     * @var Subscription_Cache      A singleton instance
     */
    protected static $_instance;

    /**
     * @var string                  The key prefix to use with caching
     */
    public static $prefix = 'subscr';

    /**
     * @var int                 The lifetime of cache keys
     */
    public static $expiration = 3600;

    /**
     * @var object              The cache client
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
     * Load data from cache
     *
     * @param   int     $account_id
     * @return  mixed
     */
    public function load($account_id)
    {
        $key = self::$prefix.$account_id;
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
     * Save data to cache
     *
     * @param   int     $account_id
     * @param   array   $data
     */
    public function save($account_id, $data)
    {
        unset($data['account_id']);
        $key = self::$prefix.$account_id;
        $this->_client->hMset($key, $data);

        if (self::$expiration)
        {
            $this->_client->expire($key, self::$expiration);
        }
    }

    /**
     * Delete data from cache
     *
     * @param   $account_id
     */
    public function delete($account_id)
    {
        $key = self::$prefix.$account_id;
        $this->_client->delete($key);
    }

    /**
     * Returns a singleton instance of the class
     *
     * @return  Account_Cache
     */
    public static function instance()
    {
        if ( ! Subscription_Cache::$_instance instanceof Subscription_Cache)
        {
            Subscription_Cache::$_instance = new Subscription_Cache();
        }

        return Subscription_Cache::$_instance;
    }
}

// END Kohana_Subscription_Cache