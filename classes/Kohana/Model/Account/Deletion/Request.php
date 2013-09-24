<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Account_Deletion_Request extends ORM {

    protected $_table_name = 'account_deletion_requests';

    protected $_primary_key = 'request_id';

    protected $_table_columns = array(
        'request_id' => array(),
        'account_id' => array(),
        'requested_by' => array(),
        'due_on' => array()
    );

    /**
     * Delete all account deletion requests for the given project
     *
     * @param   int     $account_id
     */
    public function delete_all($account_id)
    {
        DB::delete($this->_table_name)
            ->where('account_id', '=', DB::expr($account_id))
            ->execute();
    }
}

// END Kohana_Model_Account_Deletion_Request
