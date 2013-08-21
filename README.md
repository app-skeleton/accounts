## API

### Account_Manager

- create_account [x]
- update_account [x]
- get_account_data [x]
- delete_account [x]
- get_account_owner_data [x]
- change_account_owner [x]

- add_user [x]
- remove_user [x]
- get_user_data [x]
- get_user_accounts [x]
- get_user_account_ids [x]
- set_user_status [x]
- get_user_status [x]
- is_user_linked [x]
- is_user_invited [x]
- is_user_removed [x]
- is_user_left [x]
- get_user_inviter_data [x]
- get_user_teammates [x]
- get_user_teammates_count [x]

- grant_permission [x]
- revoke_permission [x]
- get_permissions [x]
- has_permission [x]
- has_access [x]

- garbage_collector [x]


### Account_Cache

- add_account [x]
- remove_account [x]
- save_user_accounts [x]
- load_user_accounts [x]
- grant_account_permission [x]
- revoke_account_permission [x]
- save_user_account_permissions [x]
- load_user_account_permissions [x]
- add_project [x]
- remove_project [x]
- save_user_projects [x]
- load_user_projects [x]
- delete_data [x]


### Subscription_Manager

- create_subscription [x]
- update_subscription [x]
- get_subscription_data [x]
- pause_subscription [x]
- cancel_subscription [x]
- restore_subscription [x]
- is_subscription_active [x]
- is_subscription_expired [x]
- is_subscription_in_grace_period [x]
- is_subscription_paused [x]
- is_subscription_canceled [x]
- get_subscription_expiration_time [x]
- extend_subscription [x]
- change_subscription_plan [x]
- get_plan_limits [x]
- get_subscription_events [x]

- supervise_subscriptions [x]


### Project_Manager

- create_project [x]
- update_project [x]
- get_project_data [x]
- delete_project [x]
- archive_project [x]
- restore_project [x]

- add_user [x]
- remove_user [x]
- is_user_linked [x]

- star_project [x]
- un_star_project [x]

- get_users [x]
- get_users_count [x]
- get_user_projects [x]

- has_access [x]
- garbage_collector [x]


### Account_Invitation_Manager

- invite [x]
- accept_invitation [x]
- decline_invitation [x]
- get_invitation_link_data [x]

- garbage_collector [x]


### Braintree_Manager

- create_client []
- update_client []
- delete_client []

- create_payment []
- process_notification []

- create_subscription []
- update_subscription []
- cancel_subscription []

HINT! (payment_table: payment_id, payment_provider, transaction_id)