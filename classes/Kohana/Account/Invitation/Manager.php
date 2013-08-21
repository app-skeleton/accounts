<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Account_Invitation_Manager {

    /**
     * @var Account_Invitation_Manager     A singleton instance
     */
    protected static $_instance;

    /**
     * Create and send an invitation
     *
     * @param   string      $email
     * @param   int         $account_id
     * @param   array       $project_ids
     * @param   array       $permission
     * @param   int         $inviter_id
     * @throws  Validation_Exception|Exception
     */
    public function invite($email, $account_id, $project_ids, $permission, $inviter_id)
    {
        $user_manager       = User_Manager::instance();
        $project_manager    = Project_Manager::instance();
        $account_manager    = Account_Manager::instance();

        $use_cache          = Account_Manager::$use_cache;
        $account_cache      = $use_cache ? Account_Cache::instance() : NULL;

        $account_model      = $account_manager->account_model();
        $project_model      = $project_manager->project_model();

        $emails = (array)$email;

        // Get data about the inviter
        $inviter_data = $user_manager->get_user_data($inviter_id);

        // Get data about the account
        $account_data = $account_manager->get_account_data($account_id);

        foreach ($emails as $email)
        {
            if ( ! Valid::email($email))
            {
                throw new Validation_Exception(array(), 'Invalid email address.');
            }

            // Begin database transaction
            $account_model->begin();

            try
            {
                // Check if a user with this email already exists
                $invitee_id = $user_manager->get_user_id_by('email', $email);

                if ( ! $invitee_id)
                {
                    // Create a ghost user
                    $ghost_user_model = ORM::factory('Ghost_User')
                        ->values(array('first_name' => $email))
                        ->save();

                    // Create a ghost identity
                    ORM::factory('Ghost_Identity')
                        ->values(array(
                            'email' => $email,
                            'user_id' => $ghost_user_model->pk(),
                            'status' => 'pending'
                        ))
                        ->save();

                    $invitee_id = $ghost_user_model->pk();
                }

                // Grant the given permissions to the user
                $account_model->grant_permission($account_id, $invitee_id, $permission);

                // Update the user's permissions in cache
                if ($use_cache)
                {
                    $account_cache->grant_account_permission($invitee_id, $account_id, $permission);
                }

                // Get user status
                $user_status = $account_model->get_user_status($account_id, $invitee_id);

                // Check if user is already linked
                $is_linked = $user_status == Account_Manager::STATUS_USER_LINKED;

                // If user is already linked to the account, and no projects specified or user is added to all of the specified projects,
                // we don't need to send an invitation
                if ($is_linked && (empty($project_ids) || $project_manager->is_user_linked($project_ids, $invitee_id)))
                {
                    // Commit changes
                    $account_model->commit();

                    // Jump to next email address
                    continue;
                }

                // Check if user is already invited
                $is_invited = $user_status == Account_Manager::STATUS_USER_INVITED;

                if ( ! $is_linked && ! $is_invited)
                {
                    // Add the user to the account
                    $account_model->add_user($account_id, $invitee_id, $inviter_id, Account_Manager::STATUS_USER_INVITED);

                    // Update the user's accounts in cache
                    if ($use_cache)
                    {
                        $account_cache->add_account($invitee_id, $account_id);
                    }
                }

                if ( ! empty($project_ids))
                {
                    // Add the user to the projects
                    $project_model->add_user($project_ids, $invitee_id);

                    // Update the user's projects in cache
                    if ($use_cache)
                    {
                        $account_cache->add_project($invitee_id, $project_ids);
                    }
                }

                // Check if an invitation link was already generated for this user and account
                $invitation_link = ORM::factory('Account_Invitation_Link')
                    ->where('invitee_id', '=', $invitee_id)
                    ->and_where('account_id', '=', $account_id)
                    ->find();

                if ( ! $invitation_link->loaded())
                {
                    // No existing link, generate a new one
                    $invitation_link
                        ->generate($account_id, $inviter_id, $invitee_id, $email)
                        ->save();
                }

                // Commit database changes
                $account_model->commit();

                // Get the secure key
                $secure_key = $invitation_link->secure_key();

                // Create the data array
                $data = array(
                    'is_linked'     => $is_linked,
                    'account_data'  => $account_data,
                    'inviter_data'  => $inviter_data,
                    'projects'      => ! empty($project_ids) ? $project_manager->get_project_data($project_ids) : array(),
                    'accept_url'    => URL::map('accounts.invitations.accept', array($secure_key)),
                    'decline_url'   => URL::map('accounts.invitations.decline', array($secure_key))
                );

                // Send the invitation email
                $invitation_email = Account_Invitation_Email::factory($email, $data);
                $invitation_email->send();
            }
            catch (Exception $e)
            {
                // Roll back database changes
                $account_model->rollback();

                // Re-throw the exception
                throw $e;
            }
        }
    }

    /**
     * Accept an invitation
     *
     * @param   string  $secure_link
     * @throws  Account_Invitation_Link_Exception
     */
    public function accept($secure_link)
    {
        // Load the invitation link
        $link = ORM::factory('Account_Invitation_Link')
            ->where('secure_key', '=', $secure_link)
            ->find();

        if ( ! $link->loaded())
        {
            throw new Account_Invitation_Link_Exception('Invalid secure key.');
        }

        // Accept the invitation
        Account_Manager::instance()->set_user_status($link->get('account_id'), $link->get('invitee_id'), Account_Manager::STATUS_USER_LINKED);


        // Delete the link
        $link->delete();
    }

    /**
     * Decline an invitation
     *
     * @param   string  $secure_link
     * @param   string  $message
     * @throws  Account_Invitation_Link_Exception
     */
    public function decline($secure_link, $message = NULL)
    {
        // Load the invitation link
        $link = ORM::factory('Account_Invitation_Link')
            ->where('secure_key', '=', $secure_link)
            ->find();

        if ( ! $link->loaded())
        {
            throw new Account_Invitation_Link_Exception('Invalid secure key.');
        }

        // Decline the invitation
        Account_Manager::instance()->set_user_status($link->get('account_id'), $link->get('invitee_id'), Account_Manager::STATUS_USER_LEFT);

        // Get inviter name and email
        $inviter_data = User_Manager::instance()->get_user_data($link->get('inviter_id'));

        $data = array(
            'invitee_email'         => $link->get('email'),
            'inviter_data'          => $inviter_data,
            'message'               => $message
        );

        // Send an email to the inviter
        $invitation_refusal_email = Account_Invitation_Refusal_Email::factory($inviter_data['email'], $data);
        $invitation_refusal_email->send();

        // Delete the link
        $link->delete();
    }

    /**
     * Get data about an invitation, by secure key
     *
     * @param   string  $secure_key
     * @throws  Account_Invitation_Link_Exception
     * @return  array
     */
    public function get_invitation_data($secure_key)
    {
        $invitation_data = ORM::factory('Account_Invitation_Link')
            ->get_invitation_data($secure_key);

        if (empty($invitation_data) || strtotime($invitation_data['expires_on']) < time())
        {
            throw new Account_Invitation_Link_Exception('Invalid secure key.');
        }

        return $invitation_data;
    }

    /**
     * Garbage collector
     */
    public function garbage_collector()
    {
        ORM::factory('Account_Invitation_Link')
            ->garbage_collector(time());
    }

    /**
     * Returns a singleton instance of the class.
     *
     * @return  Account_Invitation_Manager
     */
    public static function instance()
    {
        if ( ! Account_Invitation_Manager::$_instance instanceof Account_Invitation_Manager)
        {
            Account_Invitation_Manager::$_instance = new Account_Invitation_Manager();
        }

        return Account_Invitation_Manager::$_instance;
    }
}

// END Kohana_Account_Invitation_Manager
