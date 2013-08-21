<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Subscription_Event extends ORM {

    protected $_table_name = 'subscription_events';

    protected $_primary_key = 'event_id';

    protected $_table_columns = array(
        'event_id' => array(),
        'subscription_id' => array(),
        'from_plan' => array(),
        'to_plan' => array(),
        'date' => array(),
        'expires_on' => array(),
        'payment_id' => array()
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
     * Get all the subscription events for the given account
     *
     * @param   int     $account_id
     * @return  array
     */
    public function get_subscription_events($account_id)
    {
        return DB::select_array(array(
            'se.event_id',
            'se.subscription_id',
            'se.from_plan',
            'se.to_plan',
            'se.date',
            'se.expires_on',
            'se.payment_id'
        ))
            ->from(array('subscriptions', 's'))
            ->join(array('subscription_events', 'se'))
            ->on('se.subscription_id', '=', 's.subscription_id')
            ->where('s.account_id', '=', DB::expr($account_id))
            ->execute($this->_db)
            ->as_array();

    }
}

// END Kohana_Model_Subscription_Event