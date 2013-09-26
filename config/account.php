<?php defined('SYSPATH') OR die('No direct access allowed.');

return array(
    // Grace period before we actually delete all the data for a canceled account (in days)
    'account_cancellation_grace_period' => 30 * 24 * 3600,

    // Grace period before we set an expiring subscription expired
    'subscription_expiration_grace_period' => 5 * 24 * 3600,

    // Grace period before we delete items from trash
    'trash_grace_period' => 30 * 24 * 3600,

    // Account invitation settings
    'account_invitation' => array(
        'email' => array(
            'subject' => array(
                'en-us' => array(
                    'no_project' => "You've been invited to ".APPNAME,
                    'one_project' => "You've been added to the {{project_name}} project on ".APPNAME,
                    'multiple_projects' => "You've been added to {{project_count}} projects on ".APPNAME,
                    'refusal' => "{{invitee_email}} declined your invitation to ".APPNAME,
                )
            ),
            'sender' => array(
                'email' => 'noreply@appdomain.com',
                'name'  => APPNAME
            )
        ),

        // Invitation link lifetime in days
        'link_lifetime' => 7 * 24 * 3600
    ),

    // Account leaving settings
    'account_leaving' => array(
        'email' => array(
            'subject' => array(
                'en-us' => '{{invitee_name}} left your account'
            ),
            'sender' => array(
                'email' => 'noreply@appdomain.com',
                'name'  => APPNAME
            )
        )
    )
);
