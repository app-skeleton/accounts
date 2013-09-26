<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Invitation_Manager extends Account_Service_Manager {

    /**
     * Create and send an invitation
     *
     * @param   string|array    $emails
     * @param   int             $account_id
     * @param   array           $project_ids
     * @param   array           $permissions
     * @param   int             $inviter_id
     * @throws  Validation_Exception
     * @throws  Exception
     */
    public function invite($emails, $account_id, $project_ids, $permissions, $inviter_id)
    {
        $user_manager       = User_Manager::instance();
        $project_manager    = Project_Manager::instance();
        $account_manager    = Account_Manager::instance();

        $emails = (array)$emails;

        // Check for invalid emails
        if (empty($emails))
        {
            throw new Validation_Exception(array(), 'No emails specified.');
        }

        $invalid_emails = array();
        foreach ($emails as $email)
        {
            if ( ! Valid::email($email))
            {
                array_push($invalid_emails, $email);
            }
        }

        if ( ! empty($invalid_emails))
        {
            throw new Validation_Exception($invalid_emails);
        }

        // Get data about the inviter
        $inviter_data = $user_manager->get_user_data($inviter_id);

        // Get data about the account
        $account_data = $account_manager->get_account_data($account_id);

        // All emails are valid, start sending invitations
        foreach ($emails as $email)
        {
            // Begin transaction
            $this->begin_transaction();

            try
            {
                // Check if a user with this email already exists
                $invitee_data = $user_manager->get_user_data_by('email', $email);
                $invitee_id = ! empty($invitee_data)
                    ? $invitee_data['user_id']
                    : NULL;

                if ( ! $invitee_id)
                {
                    // Create a ghost user
                    $ghost_user_model = ORM::factory('Ghost_User')
                        ->values(array('first_name' => $email))
                        ->save();

                    // Create a ghost identity
                    ORM::factory('Ghost_Identity')
                        ->values(array(
                            'user_id' => $ghost_user_model->pk(),
                            'email' => $email,
                            'status' => Model_Identity::STATUS_INVITED
                        ))
                        ->save();

                    $invitee_id = $ghost_user_model->pk();
                }

                // Grant the given permissions to the user
                if ( ! empty($permissions))
                {
                    $account_manager->grant_permission($account_id, $invitee_id, $permissions);
                }

                // Get user status on the given account
                $user_status = $account_manager->get_user_status($account_id, $invitee_id);

                // Check if user is already linked
                $is_linked = $user_status == Model_Account::STATUS_USER_LINKED;

                // If user is already linked to the account, and no projects specified or user is added to all of the specified projects,
                // we don't need to send an invitation
                if ($is_linked && (empty($project_ids) || $project_manager->is_user_linked($project_ids, $invitee_id)))
                {
                    // Commit changes
                    $this->commit_transaction();

                    // Jump to next email address
                    continue;
                }

                // Check if user is already invited
                $is_invited = $user_status == Model_Account::STATUS_USER_INVITED;

                if ( ! $is_linked && ! $is_invited)
                {
                    // Add the user to the account
                    $account_manager->add_user($account_id, $invitee_id, $inviter_id, Model_Account::STATUS_USER_INVITED);
                }

                if ( ! empty($project_ids))
                {
                    // Add the user to the projects
                    $project_manager->add_user($project_ids, $invitee_id);
                }

                // Check if an invitation link was already generated for this user and account
                $invitation_link = ORM::factory('Account_Invitation_Link')
                    ->where('invitee_id', '=', $invitee_id)
                    ->and_where('account_id', '=', $account_id)
                    ->find();

                if ( ! $invitation_link->loaded())
                {
                    // No existing link, generate a new one
                    $config = Kohana::$config->load('account')->get('account_invitation');
                    $secure_key = Text::random('alnum', 32);
                    $invitation_link
                        ->set('account_id', $account_id)
                        ->set('inviter_id', $inviter_id)
                        ->set('invitee_id', $invitee_id)
                        ->set('email', $email)
                        ->set('secure_key', $secure_key)
                        ->set('expires_on', date('Y-m-d H:i:s', time() + $config['link_lifetime'] * 24 * 3600))
                        ->save();
                }

                // Create the data array
                $data = array(
                    'is_linked'     => $is_linked,
                    'account_data'  => $account_data,
                    'inviter_data'  => $inviter_data,
                    'projects'      => ! empty($project_ids) ? $project_manager->get_project_data($project_ids) : array(),
                    'accept_url'    => URL::map('invitation.accept', array($account_id, $invitation_link->get('secure_key'))),
                    'decline_url'   => URL::map('invitation.decline', array($account_id, $invitation_link->get('secure_key'))),
                );

                // Send the invitation email
                $invitation_email = Account_Invitation_Email::factory($email, $data);
                $invitation_email->send();

                // Everything was going fine, commit
                $this->commit_transaction();
            }
            catch (Exception $e)
            {
                // Something went wrong, rollback
                $this->rollback_transaction();

                // Re-throw the exception
                throw $e;
            }
        }
    }

    /**
     * Accept an invitation by signing up
     *
     * @param   int     $account_id
     * @param   string  $secure_key
     * @param   array   $signup_values
     * @throws  Validation_Exception
     * @throws  Account_Exception
     * @throws  Exception
     * @return  array
     */
    public function accept($account_id, $secure_key, $signup_values)
    {
        // Check if secure key is valid
        $invitation_link_model = ORM::factory('Account_Invitation_Link')
            ->where('account_id', '=', DB::expr($account_id))
            ->where('secure_key', '=', $secure_key)
            ->where('expires_on', '>', date('Y-m-d H:i:s'))
            ->find();

        if ( ! $invitation_link_model->loaded())
        {
            throw new Account_Exception(Account_Exception::E_INVITATION_LINK_INVALID);
        }

        // Get the user manager
        $user_manager = User_Manager::instance();

        // Get the account manager
        $account_manager = Account_Manager::instance();

        // Set email address signup values
        $signup_values['email'] = $invitation_link_model->get('email');

        // Begin transaction
        $this->begin_transaction();

        try
        {
            // Signup the user
            $models_data = $user_manager->signup_user($signup_values);

            // Set user's status on the account
            $account_manager->set_user_status($account_id, $models_data['user']['user_id'], Model_Account::STATUS_USER_LINKED);

            // Delete the link
            $invitation_link_model->delete();

            // Everything was going fine, commit
            $this->commit_transaction();

            return $models_data;
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Accept an invitation by logging in
     *
     * @param   int     $account_id
     * @param   string  $secure_key
     * @param   int     $user_id
     * @throws  Account_Exception
     * @throws  Exception
     */
    public function claim($account_id, $secure_key, $user_id)
    {
        // Check if invitation's secret key is valid
        $invitation_link_model = ORM::factory('Account_Invitation_Link')
            ->where('account_id', '=', DB::expr($account_id))
            ->where('secure_key', '=', $secure_key)
            ->where('expires_on', '>', date('Y-m-d H:i:s'))
            ->find();

        if ( ! $invitation_link_model->loaded())
        {
            throw new Account_Exception(Account_Exception::E_INVITATION_LINK_INVALID);
        }

        // Make sure the invitation link was created for this user
        if ($invitation_link_model->get('invitee_id') != $user_id)
        {
            throw new Account_Exception(Account_Exception::E_INVITATION_EMAIL_MISMATCH);
        }

        // Get the account manager
        $account_manager = Account_Manager::instance();

        // Begin the transaction
        $this->begin_transaction();

        try
        {
            // Update user's status on the account
            $account_manager->set_user_status($account_id, $user_id, Model_Account::STATUS_USER_LINKED);

            // Delete the link
            $invitation_link_model->delete();

            // Everything was going fine, commit
            $this->commit_transaction();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Decline an invitation
     *
     * @param   int     $account_id
     * @param   string  $secure_key
     * @param   string  $message
     * @throws  Account_Exception
     * @throws  Exception
     */
    public function decline($account_id, $secure_key, $message = NULL)
    {
        // Check if invitation's secret key is valid
        $invitation_link_model = ORM::factory('Account_Invitation_Link')
            ->where('account_id', '=', DB::expr($account_id))
            ->where('secure_key', '=', $secure_key)
            ->where('expires_on', '>', date('Y-m-d H:i:s'))
            ->find();

        if ( ! $invitation_link_model->loaded())
        {
            throw new Account_Exception(Account_Exception::E_INVITATION_LINK_INVALID);
        }

        $user_manager = User_Manager::instance();
        $account_manager = Account_Manager::instance();

        $invitee_id = $invitation_link_model->get('invitee_id');
        $inviter_id = $invitation_link_model->get('inviter_id');
        $email = $invitation_link_model->get('email');

        // Begin transaction
        $this->begin_transaction();

        try
        {
            // Decline the invitation
            $account_manager->set_user_status($account_id, $invitee_id, Model_Account::STATUS_USER_LEFT);

            // Delete the link
            $invitation_link_model->delete();

            // Everything was going fine, commit
            $this->commit_transaction();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $this->rollback_transaction();

            // Re-throw the exception
            throw $e;
        }

        // Prepare inviter data
        $inviter_data = $user_manager->get_user_data($inviter_id);

        $data = array(
            'invitee_email'         => $email,
            'inviter_data'          => $inviter_data,
            'message'               => $message
        );

        // Send an email to the inviter
        $invitation_refusal_email = Account_Invitation_Refusal_Email::factory($inviter_data['email'], $data);
        $invitation_refusal_email->send();
    }

    /**
     * Get data about an invitation, by secure key
     *
     * @param   int     $account_id
     * @param   string  $secure_key
     * @throws  Account_Exception
     * @return  array
     */
    public function get_invitation_data($account_id, $secure_key)
    {
        $invitation_link_model = ORM::factory('Account_Invitation_Link')
            ->where('account_id', '=', DB::expr($account_id))
            ->where('secure_key', '=', $secure_key)
            ->where('expires_on', '>', date('Y-m-d H:i:s'))
            ->find();

        if ( ! $invitation_link_model->loaded())
        {
            throw new Account_Exception(Account_Exception::E_INVITATION_LINK_INVALID);
        }

        return $invitation_link_model->as_array();
    }

    /**
     * Garbage collector
     */
    public function garbage_collector()
    {
        ORM::factory('Account_Invitation_Link')->garbage_collector(time());
    }
}

// END Kohana_Account_Invitation_Manager
