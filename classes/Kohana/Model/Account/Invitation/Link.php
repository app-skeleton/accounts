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
     * Get data about the invitation with the given secure key
     *
     * @param   string  $secure_key
     */
    public function get_invitation_data($secure_key)
    {
        return DB::select_array(array(
            'ai.link_id',
            'ai.account_id',
            'ai.inviter_id',
            'ai.invitee_id',
            'ai.secure_key',
            'ai.email',
            'ai.expires_on',
            array('u.first_name', 'inviter_first_name'),
            array('u.last_name', 'inviter_last_name'),
            array('ui.username', 'inviter_username'),
            array('ui.email', 'inviter_email'),
            array('ui.status', 'inviter_status')
        ))
            ->from(array('account_invitation_links', 'ai'))
            ->join(array('users', 'u'))
            ->on('ai.inviter_id', '=', 'u.user_id')
            ->join(array('user_identities', 'ui'))
            ->on('ui.user_id', '=', 'u.user_id')
            ->where('ai.secure_key', '=', $secure_key)
            ->as_assoc()
            ->execute($this->_db)
            ->current();
    }

    /**
     * Generate an account invitation link
     *
     * @param   int     $account_id
     * @param   int     $inviter_id
     * @param   int     $invitee_id
     * @param   string  $email
     * @return  Model_Account_Invitation_Link
     */
    public function generate($account_id, $inviter_id, $invitee_id, $email)
    {
        $config = Kohana::$config->load('account')->get('account_invitation');

        $this
            ->set('account_id', $account_id)
            ->set('inviter_id', $inviter_id)
            ->set('invitee_id', $invitee_id)
            ->set('email', $email)
            ->set('secure_key', Text::random('alnum', 32))
            ->set('expires_on', date('Y-m-d H:i:s', time() + $config['link_lifetime']));

        return $this;
    }

    /**
     * Return secure_key
     *
     * @return  string
     */
    public function secure_key()
    {
        return $this->get('secure_key');
    }

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