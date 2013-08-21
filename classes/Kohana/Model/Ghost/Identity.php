<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Ghost_Identity extends Model_Identity {

    /**
     * Define validation rules
     *
     * @return  array
     */
    public function rules()
    {
        return array();
    }

    /**
     * Define filters
     *
     * @return  array
     */
    public function filters()
    {
        return array();
    }
}

// END Kohana_Model_Ghost_Identity