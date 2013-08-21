<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Subscription extends ORM {

    protected $_table_name = 'subscriptions';

    protected $_primary_key = 'subscription_id';

    protected $_table_columns = array(
        'subscription_id' => array(),
        'account_id' => array(),
        'plan' => array(),
        'expires_on' => array(),
        'expired' => array(),
        'paused' => array(),
        'canceled' => array()
    );

    /**
     * Defines validation rules
     *
     * @return  array
     */
    public function rules()
    {
        return array();
    }

    /**
     * Defines filters
     *
     * @return  array
     */
    public function filters()
    {
        return array();
    }

    /**
     * Set all the subscriptions with the grace period ended `expired`
     *
     * @param   int     $start_time
     */
    public function supervise_subscriptions($start_time)
    {
        DB::update('subscriptions')
            ->set(array('expired' => 1))
            ->where('expires_on', '<', date('Y-m-d H:i:s', $start_time))
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

// END Kohana_Model_Account