<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Ghost_User extends ORM {

    protected $_table_name = 'users';

    protected $_primary_key = 'user_id';

    protected $_table_columns = array(
        'user_id' => array(),
        'first_name' => array(),
        'last_name' => array(),
        'timezone' => array()
    );
}

// END Kohana_Model_Ghost_User
