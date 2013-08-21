<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Invitation_Refusal_Email {

    /**
     * @var string  The email address to send the invitation to
     */
    protected $_email;

    /**
     * @var array   The data to render the email template with
     */
    protected $_data;

    /**
     * Construct
     *
     * @param   string  $email
     * @param   array   $data
     */
    protected function __construct($email, $data)
    {
        $this->_email = $email;
        $this->_data = $data;
    }

    /**
     * Send the invitation email
     */
    public function send()
    {
        $view = View::factory('accounts/email/'.i18n::lang().'/invitation/refusal', $this->_data);
        $config = Kohana::$config->load('account')->get('account_invitation');
        $subject = strtr($config['email']['subject'][i18n::lang()]['refusal'], array(
            '{{invitee_email}}' => $this->_data['invitee_email'],
        ));

        Email::factory($subject, $view->render(), 'text/html')
            ->to($this->_email)
            ->from($config['email']['sender']['email'], $config['email']['sender']['name'])
            ->send();
    }

    /**
     * Returns a new instance of the class.
     *
     *
     * @param   string  $email
     * @param   array   $data
     * @return  Account_Invitation_Refusal_Email
     */
    public static function factory($email, $data)
    {
        return new Account_Invitation_Refusal_Email($email, $data);
    }
}

// END Kohana_Account_Invitation_Refusal_Email
