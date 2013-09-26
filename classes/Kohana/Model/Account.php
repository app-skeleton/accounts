<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Model_Account extends ORM {

    protected $_table_name = 'accounts';

    protected $_primary_key = 'account_id';

    protected $_table_columns = array(
        'account_id' => array(),
        'name' => array(),
        'owner_id' => array(),
        'created_by' => array(),
        'created_on' => array()
    );

    /**
     * Status constants
     */
    const STATUS_USER_INVITED       = 'INVITED';
    const STATUS_USER_LINKED        = 'LINKED';
    const STATUS_USER_REMOVED       = 'REMOVED';
    const STATUS_USER_LEFT          = 'LEFT';

    /**
     * Permission constants
     */
    const PERM_OWNER                = 'OWNER';
    const PERM_ADMIN                = 'ADMIN';
    const PERM_ACCOUNT_MANAGER      = 'ACCOUNT_MANAGER';
    const PERM_CREATE_PROJECTS      = 'CREATE_PROJECTS';

    /**
     * Defines validation rules
     *
     * @return  array
     */
    public function rules()
    {
        return array(
            'name' => array(
                array('not_empty')
            )
        );
    }

    /**
     * Defines filters
     *
     * @return  array
     */
    public function filters()
    {
        return array(
            'name' => array(
                array('trim')
            )
        );
    }

    /**
     * Get data about the owner of the given account
     *
     * @param   int     $account_id
     * @return  array
     */
    public function get_account_owner_data($account_id)
    {
        $user_data = DB::select_array(array(
            'u.user_id',
            'u.first_name',
            'u.last_name',
            'ui.email'
        ))
            ->from(array('accounts', 'a'))
            ->join(array('users', 'u'))
            ->on('u.user_id', '=', 'a.owner_id')
            ->join(array('user_identities', 'ui'))
            ->on('ui.user_id', '=', 'u.user_id')
            ->where('a.account_id', '=', DB::expr($account_id))
            ->as_assoc()
            ->execute($this->_db)
            ->current();

        return $user_data ?: NULL;
    }

    /**
     * Add the given user to the given account
     *
     * @param   int         $account_id
     * @param   int         $user_id
     * @param   int         $inviter_id
     * @param   string      $status
     */
    public function add_user($account_id, $user_id, $inviter_id, $status)
    {
        $query = DB::insert('accounts_users')
            ->values(array($account_id, $user_id, $inviter_id, $status));

        // Get the sql query
        $sql = $query->compile().' ON DUPLICATE KEY UPDATE account_id=account_id';

        DB::query(Database::INSERT, $sql)->execute($this->_db);
    }

    /**
     * Get data about the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_data($account_id, $user_id)
    {
        $user_data = DB::select_array(array(
            'u.user_id',
            'u.first_name',
            'u.last_name',
            'ui.email',
            'au.status',
            array(DB::expr('a.owner_id = u.user_id'), 'is_owner'),
            array('u2.user_id', 'inviter_id'),
            array('u2.first_name', 'inviter_first_name'),
            array('u2.last_name', 'inviter_last_name')
        ))
            ->from(array('users', 'u'))
            ->join(array('user_identities', 'ui'))
            ->on('ui.user_id', '=', 'u.user_id')
            ->join(array('accounts_users', 'au'))
            ->on('au.account_id', '=', DB::expr($account_id))
            ->on('au.user_id', '=', 'u.user_id')
            ->join(array('accounts', 'a'))
            ->on('a.account_id', '=', 'au.account_id')
            ->join(array('users', 'u2'), 'LEFT')
            ->on('u2.user_id', '=', 'au.inviter_id')
            ->where('u.user_id', '=', DB::expr($user_id))
            ->as_assoc()
            ->execute($this->_db)
            ->current();

        return $user_data ?: NULL;
    }

    /**
     * Get the accounts the given user is linked to
     *
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_accounts($user_id)
    {
        return DB::select_array(array(
            'a.account_id',
            'a.name',
            'a.owner_id',
            'a.created_by',
            'a.created_on'
        ))
            ->from(array('accounts', 'a'))
            ->join(array('accounts_users', 'au'))
            ->on('au.account_id', '=', 'a.account_id')
            ->on('au.user_id', '=', DB::expr($user_id))
            ->on('au.status', '=', DB::expr("'".Model_Account::STATUS_USER_LINKED."'"))
            ->execute($this->_db)
            ->as_array();
    }

    /**
     * Get the account ids the given user is linked to
     *
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_account_ids($user_id)
    {
        $accounts = $this->get_user_accounts($user_id);
        $account_ids = array();

        foreach ($accounts as $account)
        {
            array_push($account_ids, $account['account_id']);
        }

        return $account_ids;
    }

    /**
     * Set the status of the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @param   string  $status
     */
    public function set_user_status($account_id, $user_id, $status)
    {
        DB::update('accounts_users')
            ->set(array('status' => $status))
            ->where('account_id', '=', DB::expr($account_id))
            ->where('user_id', '=', DB::expr($user_id))
            ->execute($this->_db);
    }

    /**
     * Get the status of the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  mixed
     */
    public function get_user_status($account_id, $user_id)
    {
        return DB::select('status')
            ->from('accounts_users')
            ->where('account_id', '=', DB::expr($account_id))
            ->where('user_id', '=', DB::expr($user_id))
            ->execute($this->_db)
            ->get('status');
    }

    /**
     * Get data about the user who invited the given user to the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_inviter_data($account_id, $user_id)
    {
        $user_data = DB::select_array(array(
            'u.user_id',
            'u.first_name',
            'u.last_name',
            'ui.email'
        ))
            ->from(array('users', 'u'))
            ->join(array('user_identities', 'ui'))
            ->on('ui.user_id', '=', 'u.user_id')
            ->join(array('accounts_users', 'au'))
            ->on('au.inviter_id', '=', 'u.user_id')
            ->on('au.user_id', '=', DB::expr($user_id))
            ->on('au.account_id', '=', DB::expr($account_id))
            ->as_assoc()
            ->execute($this->_db)
            ->current();

        return $user_data ?: NULL;
    }

    /**
     * Get the teammates of the given user on the given account (teammates have at least one project in common)
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_user_teammates($account_id, $user_id)
    {
        return DB::select_array(array(
            'u.user_id',
            'u.first_name',
            'u.last_name',
            array(DB::expr('a.owner_id = u.user_id'), 'is_owner')
        ))
            ->distinct(TRUE)
            ->from(array('projects', 'p'))
            ->join(array('projects_users', 'pu1'))
            ->on('pu1.project_id', '=', 'p.project_id')
            ->on('pu1.user_id', '=', DB::expr($user_id))
            ->join(array('projects_users', 'pu2'))
            ->on('pu2.project_id', '=', 'pu1.project_id')
            ->join(array('accounts_users', 'au'))
            ->on('au.account_id', '=', 'p.account_id')
            ->on('au.user_id', '=', 'pu2.user_id')
            ->on('au.status', '=', DB::expr("'".Model_Identity::STATUS_ACTIVE."'"))
            ->join(array('users', 'u'))
            ->on('u.user_id', '=', 'pu2.user_id')
            ->join(array('accounts', 'a'))
            ->on('a.account_id', '=', 'au.account_id')
            ->where('p.account_id', '=', DB::expr($account_id))
            ->execute()
            ->as_array();
    }

    /**
     * Get the number of teammates of the given user on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  int
     */
    public function get_user_teammates_count($account_id, $user_id)
    {
        return count($this->get_user_teammates($account_id, $user_id));
    }

    /**
     * Grant one or more permissions to the given user on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     * @param   string|array    $permissions
     */
    public function grant_permission($account_id, $user_id, $permissions)
    {
        $permissions = (array)$permissions;

        $query = DB::insert('account_permissions');
        foreach ($permissions as $permission)
        {
            $query->values(array($account_id, $user_id, $permission));
        }

        // Get the sql query
        $sql = $query->compile().' ON DUPLICATE KEY UPDATE account_id=account_id';

        DB::query(Database::INSERT, $sql)->execute($this->_db);
    }

    /**
     * Revoke one or more permissions from the given user on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     * @param   string|array    $permission
     */
    public function revoke_permission($account_id, $user_id, $permission)
    {
        DB::delete('account_permissions')
            ->where('account_id', '=', $account_id)
            ->and_where('user_id', '=', $user_id)
            ->and_where('permission', 'IN', (array)$permission)
            ->execute($this->_db);
    }

    /**
     * Revoke all permissions from the given user on the given account
     *
     * @param   int             $account_id
     * @param   int             $user_id
     */
    public function revoke_all_permissions($account_id, $user_id)
    {
        DB::delete('account_permissions')
            ->where('account_id', '=', $account_id)
            ->and_where('user_id', '=', $user_id)
            ->execute($this->_db);
    }

    /**
     * Get all the permissions the given user has on the given account
     *
     * @param   int     $account_id
     * @param   int     $user_id
     * @return  array
     */
    public function get_permissions($account_id, $user_id)
    {
        $result = DB::select('permission')
            ->from('account_permissions')
            ->where('account_id', '=', $account_id)
            ->and_where('user_id', '=', $user_id)
            ->execute($this->_db);

        $permissions = array();
        foreach ($result as $row)
        {
            array_push($permissions, $row['permission']);
        }

        return $permissions;
    }

    /**
     * Check if there is an account created by the given user
     *
     * @param   int     $user_id
     * @return  bool
     */
    public function user_has_account($user_id)
    {
        return (bool) DB::select(array(DB::expr('COUNT("*")'), 'total_count'))
            ->from($this->_table_name)
            ->where('created_by', '=', DB::expr($user_id))
            ->execute($this->_db)
            ->get('total_count');
    }

    /**
     * Do garbage collection
     *
     * @param   int     $time
     */
    public function garbage_collector($time)
    {
        // Delete all canceled accounts, with a deletion request older than $grace_period
        $sql = "DELETE accounts
                FROM accounts
                JOIN subscriptions
                ON (subscriptions.account_id = accounts.account_id
                AND subscriptions.canceled = 1)
                JOIN account_deletion_requests
                ON (account_deletion_requests.account_id = accounts.account_id
                AND account_deletion_requests.requested_on < :start_date)";

        DB::query(Database::DELETE, $sql)
            ->bind(':start_date', date('Y-m-d H:i:s', $time))
            ->execute($this->_db);
    }
}

// END Kohana_Model_Account
