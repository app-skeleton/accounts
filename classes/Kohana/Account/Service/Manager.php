<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Service_Manager extends Service_Manager {

    /**
     * @var bool                Whether to use cache
     */
    public static $use_cache = TRUE;

    /**
     * @var Account_Cache       An instance of the Account_Cache class
     */
    protected $_cache;

    /**
     * Begin a transaction
     *
     * @param   bool    $cache
     */
    public function begin_transaction($cache = TRUE)
    {
        if ($cache && self::$use_cache && self::$_transaction_counter == 0)
        {
            $this->cache()->multi();
        }

        parent::begin_transaction();
    }

    /**
     * Commit a transaction
     *
     * @param   bool    $cache
     */
    public function commit_transaction($cache = TRUE)
    {
        parent::commit_transaction();

        if ($cache && self::$use_cache && self::$_transaction_counter == 0)
        {
            $this->cache()->exec();
        }
    }

    /**
     * Rollback a transaction
     *
     * @param   bool    $cache
     */
    public function rollback_transaction($cache = TRUE)
    {
        parent::rollback_transaction();

        if ($cache && self::$use_cache && self::$_transaction_counter == 0)
        {
            $this->cache()->discard();
        }
    }

    /**
     * Get the cache instance
     *
     * @return  Account_Cache
     */
    public function cache()
    {
        if ( ! isset($this->_cache))
        {
            $this->_cache = Account_Cache::instance();
        }

        return $this->_cache;
    }
}

// END Kohana_Account_Service_Manager
