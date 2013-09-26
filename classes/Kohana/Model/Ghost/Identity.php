<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Ghost_Identity extends ORM {

    protected $_table_name = 'user_identities';

    protected $_primary_key = 'identity_id';

    protected $_table_columns = array(
        'identity_id' => array(),
        'user_id' => array(),
        'password' => array(),
        'email' => array(),
        'status' => array()
    );
}

// END Kohana_Model_Ghost_Identity
