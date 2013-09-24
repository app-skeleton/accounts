<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Exception extends Kohana_Exception {

    /**
     * Error codes
     */
    const E_USER_HAS_ACCOUNT            = 51;
    const E_INVITATION_LINK_INVALID     = 52;
    const E_ACCESS_DENIED               = 53;
    const E_INSUFFICIENT_PERMISSIONS    = 54;
    const E_SUBSCRIPTION_IS_CANCELED    = 55;
    const E_SUBSCRIPTION_IS_PAUSED      = 56;
    const E_SUBSCRIPTION_IS_EXPIRED     = 57;
    const E_OWNER_CAN_NOT_BE_REMOVED    = 58;

    /**
     * @var array   Default error messages
     */
    public static $default_error_messages = array(
        51  => 'The user has an account already, and can not create a new one.',
        52  => 'The specified invitation link is expired on invalid.',
        53  => 'The user has no access to the specified resource.',
        54  => 'The user has no sufficient permissions to perform this action.',
        55  => 'The specified account is canceled.',
        56  => 'The specified account is paused.',
        57  => 'The specified account is expired.',
        58  => 'The account owner can not be removed from the account.'
    );

    /**
     * Construct
     *
     * @param   int         $code       The exception code
     * @param   string      $message    Error message
     * @param   array       $variables  Translation variables
     * @param   array       $data       Data associated with the exception
     */
    public function __construct($code, $message = NULL, array $variables = NULL, $data = NULL)
    {
        parent::__construct($message, $variables, $code, NULL, $data);
    }
}

// END Kohana_Account_Exception
