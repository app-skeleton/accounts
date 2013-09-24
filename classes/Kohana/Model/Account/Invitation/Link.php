<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Account_Invitation_Link extends ORM {

    protected $_table_name = 'account_invitation_links';

    protected $_primary_key = 'link_id';

    protected $_table_columns = array(
        'link_id' => array(),
        'account_id' => array(),
        'inviter_id' => array(),
        'invitee_id' => array(),
        'secure_key' => array(),
        'email' => array(),
        'expires_on' => array(),
    );

    /**
     * Delete all account invitation links for the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     */
    public function delete_all($account_id, $user_id)
    {
        DB::delete($this->_table_name)
            ->where('account_id', '=', DB::expr($account_id))
            ->where('invitee_id', '=', DB::expr($user_id))
            ->execute();
    }

    /**
     * Garbage collector
     *
     * @param   int     $start_time
     */
    public function garbage_collector($start_time)
    {
        DB::delete($this->_table_name)
            ->where('expires_on', '<', date('Y-m-d H:i:s', $start_time))
            ->execute($this->_db);
    }
}

// END Kohana_Model_Account_Invitation_Link
