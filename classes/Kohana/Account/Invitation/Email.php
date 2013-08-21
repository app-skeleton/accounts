<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Invitation_Email {

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
        $project_count = isset($this->_data['projects'])
            ? count($this->_data['projects'])
            : 0;

        switch ($project_count)
        {
            case 0:
                $type = 'no_project';
                break;
            case 1:
                $type = 'one_project';
                break;
            default:
                $type = 'multiple_projects';
                break;
        }

        $view = View::factory('accounts/email/'.i18n::lang().'/invitation/'.$type, $this->_data);
        $config = Kohana::$config->load('account')->get('account_invitation');
        $subject = strtr($config['email']['subject'][i18n::lang()][$type], array(
            '{{project_name}}' => ! empty($this->_data['projects']) ? $this->_data['projects'][0]['name'] : NULL,
            '{{project_count}}' => count($this->_data['projects'])
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
     * @return  Account_Invitation_Email
     */
    public static function factory($email, $data)
    {
        return new Account_Invitation_Email($email, $data);
    }
}

// END Kohana_Account_Invitation_Email
