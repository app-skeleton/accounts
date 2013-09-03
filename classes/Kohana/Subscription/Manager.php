<?php defined('SYSPATH') or die('No direct script access.');
/*
 * @package		Accounts Module
 * @author      Pap Tamas
 * @copyright   (c) 2011-2013 Pap Tamas
 * @website		https://bitbucket.org/paptamas/kohana-accounts
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Subscription_Manager {

    /**
     * @var Subscription_Manager    A singleton instance
     */
    protected static $_instance;

    /**
     * @var ORM                     An instance of the Model_Subscription class
     */
    protected $_subscription_model;

    /**
     * @var bool                    Whether to cache subscription data (it's is frequently queried)
     */
    public static $use_cache = TRUE;

    /**
     * Create a subscription
     *
     * @param   array $values
     * @throws  Validation_Exception
     * @throws  Exception
     * @return  array
     */
    public function create_subscription($values)
    {
        $subscription_model = ORM::factory('Subscription');

        try
        {
            $values['expired'] = 0;
            $values['paused'] = 0;
            $values['canceled'] = 0;

            // Begin transaction
            $subscription_model->begin();

            // Create the subscription
            $subscription_model
                ->values($values)
                ->save();

            if (self::$use_cache)
            {
                // Save subscription to cache
                Subscription_Cache::instance()->save($subscription_model->get('account_id'), $subscription_model->as_array());
            }

            // Everything is fine, commit
            $subscription_model->commit();

            return $subscription_model->as_array();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $subscription_model->rollback();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Update a subscription
     *
     * @param   int     $account_id
     * @param   array   $values
     * @throws  Validation_Exception
     * @throws  Subscription_Exception
     * @throws  Exception
     * @return  array
     */
    public function update_subscription($account_id, $values)
    {
        $subscription_model = ORM::factory('Subscription')
            ->where('account_id', '=', DB::expr($account_id))
            ->find();

        if ( ! $subscription_model->loaded())
        {
            throw new Subscription_Exception('Can not load subscription for account id: '.$account_id);
        }

        // Begin transaction
        $subscription_model->begin();

        try
        {
            // Update the subscription
            $subscription_model
                ->values($values)
                ->save();

            if (self::$use_cache)
            {
                // Save subscription to cache
                Subscription_Cache::instance()->save($account_id, $subscription_model->as_array());
            }

            // Everything is fine, commit
            $subscription_model->commit();

            return $subscription_model->as_array();
        }
        catch (ORM_Validation_Exception $e)
        {
            // Validation failed, rollback
            $subscription_model->rollback();

            $errors = $e->errors('models/'.i18n::lang().'/subscription', FALSE);
            throw new Validation_Exception($errors);
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $subscription_model->rollback();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Get data about a subscription
     *
     * @param   int     $account_id
     * @throws  Subscription_Exception
     * @return  array
     */
    public function get_subscription_data($account_id)
    {
        $subscription_cache = self::$use_cache ? Subscription_Cache::instance() : NULL;

        if ( ! self::$use_cache || ($subscription_data = $subscription_cache->load($account_id)) === NULL)
        {
            $subscription_model = ORM::factory('Subscription')
                ->where('account_id', '=', DB::expr($account_id))
                ->find();

            if ( ! $subscription_model->loaded())
            {
                throw new Subscription_Exception('Can not load subscription for account id: '.$account_id);
            }

            $subscription_data = $subscription_model->as_array();

            if (self::$use_cache)
            {
                // Save data to cache
                $subscription_cache->save($account_id, $subscription_data);
            }
        }

        return $subscription_data;
    }

    /**
     * Pause a subscription
     *
     * @param   int     $account_id
     */
    public function pause_subscription($account_id)
    {
        $this->update_subscription($account_id, array('paused' => 1));
    }

    /**
     * Cancel a subscription
     *
     * @param   int     $account_id
     * @param   int     $requested_by
     * @throws  Subscription_Exception
     * @throws  Exception
     */
    public function cancel_subscription($account_id, $requested_by)
    {
        $subscription_model = ORM::factory('Subscription')
            ->where('account_id', '=', DB::expr($account_id))
            ->find();

        if ( ! $subscription_model->loaded())
        {
            throw new Subscription_Exception('Can not load subscription for account id: '.$account_id);
        }

        // Begin transaction
        $subscription_model->begin();

        try
        {
            // Update the `canceled` status
            $subscription_model
                ->set('canceled', 1)
                ->save();

            // Create an account deletion request
            ORM::factory('Account_Deletion_Request')
                ->set('account_id', $account_id)
                ->set('requested_by', $requested_by)
                ->set('requested_on', date('Y-m-d H:i:s'))
                ->save();

            if (self::$use_cache)
            {
                // Update the cache
                Subscription_Cache::instance()->save($account_id, $subscription_model->as_array());
            }

            // Everything is fine, commit
            $subscription_model->commit();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $subscription_model->rollback();

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Restore a subscription
     *
     * @param   int     $account_id
     * @throws  Subscription_Exception
     * @throws  Exception
     */
    public function restore_subscription($account_id)
    {
        $subscription_model = ORM::factory('Subscription')
            ->where('account_id', '=', DB::expr($account_id))
            ->find();

        if ( ! $subscription_model->loaded())
        {
            throw new Subscription_Exception('Can not load subscription for account id: '.$account_id);
        }

        // Begin transaction
        $subscription_model->begin();

        try
        {
            // Restore subscription
            $subscription_model
                ->set('expired', 0)
                ->set('paused', 0)
                ->set('canceled', 0)
                ->save();

            // Delete the deletion request for this account
            ORM::factory('Account_Deletion_Request')->delete_all($account_id);

            if (self::$use_cache)
            {
                // Update the cache
                Subscription_Cache::instance()->save($account_id, $subscription_model->as_array());
            }

            // Everything is fine, commit
            $subscription_model->commit();
        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $subscription_model->rollback();

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Check if the subscription for the given account is active
     *
     * @param   int     $account_id
     * @return  bool
     */
    public function is_subscription_active($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);

        return ($subscription_data['expired'] == 0 && $subscription_data['paused'] == 0 && $subscription_data['canceled'] == 0);
    }

    /**
     * Check if the subscription for the given account is expired (and also the grace period ended)
     *
     * @param   int     $account_id
     * @return  bool
     */
    public function is_subscription_expired($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);

        // Get grace period
        $grace_period = Kohana::$config->load('account')->get('subscription_expiration_grace_period');

        $now = time();
        $expires_on = strtotime($subscription_data['expires_on']);

        return ($subscription_data['expired'] == 1) || ($now > $expires_on + $grace_period * 24 *3600);
    }

    /**
     * Check if the subscription for the given account is in the grace period
     *
     * @param   int     $account_id
     * @return  bool
     */
    public function is_subscription_in_grace_period($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);

        // Get grace period length
        $grace_period = Kohana::$config->load('account')->get('subscription_expiration_grace_period');

        $now = time();
        $expires_on = strtotime($subscription_data['expires_on']);

        return ($now > $expires_on) && ($now <= $expires_on + $grace_period * 24 *3600);
    }

    /**
     * Check if the subscription for the given account is paused
     *
     * @param   int     $account_id
     * @return  bool
     */
    public function is_subscription_paused($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);

        return $subscription_data['paused'] == 1;
    }

    /**
     * Check if the subscription for the given account is canceled
     *
     * @param   int     $account_id
     * @return  bool
     */
    public function is_subscription_canceled($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);

        return $subscription_data['canceled'] == 1;
    }

    /**
     * Get the expiration time of the subscription for the current account
     *
     * @param   int     $account_id
     * @return  bool
     */
    public function get_subscription_expiration_time($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);

        return strtotime($subscription_data['expires_on']);
    }

    /**
     * Extend the subscription for the current account
     *
     * @param   int     $account_id
     * @param   int     $expires_on     In timestamp format
     * @param   int     $payment_id
     * @throws  Subscription_Exception
     * @throws  Exception
     */
    public function extend_subscription($account_id, $expires_on, $payment_id = NULL)
    {
        $subscription_model = ORM::factory('Subscription')
            ->where('account_id', '=', DB::expr($account_id))
            ->find();

        if ( ! $subscription_model->loaded())
        {
            throw new Subscription_Exception('Can not load subscription for account id: '.$account_id);
        }

        // Begin transaction
        $subscription_model->begin();

        try
        {
            // Set a new expiration date for the subscription
            $subscription_model
                ->set('expires_on', date('Y-m-d H:i:s', $expires_on))
                ->save();

            // Create a subscription event
            ORM::factory('Subscription_Event')
                ->set('subscription_id', $subscription_model->get('subscription_id'))
                ->set('from_plan', $subscription_model->get('plan'))
                ->set('to_plan', $subscription_model->get('plan'))
                ->set('date', date('Y-m-d H:i:s'))
                ->set('expires_on', date('Y-m-d H:i:s', $expires_on))
                ->set('payment_id', $payment_id)
                ->save();

            if (self::$use_cache)
            {
                // Update the cache
                Subscription_Cache::instance()->save($account_id, $subscription_model->as_array());
            }

            // Everything is fine, commit
            $subscription_model->commit();

        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $subscription_model->rollback();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Change the subscription plan for the current account
     *
     * @param   int     $account_id
     * @param   string  $plan
     * @param   int     $expires_on     In timestamp format
     * @param   int     $payment_id
     * @throws  Subscription_Exception
     * @throws  Exception
     */
    public function change_subscription_plan($account_id, $plan, $expires_on, $payment_id = NULL)
    {
        $subscription_model = ORM::factory('Subscription')
            ->where('account_id', '=', DB::expr($account_id))
            ->find();

        if ( ! $subscription_model->loaded())
        {
            throw new Subscription_Exception('Can not load subscription for account id: '.$account_id);
        }

        // Begin transaction
        $subscription_model->begin();

        try
        {
            $subscription_data = $this->get_subscription_data($account_id);

            // Set a new expiration date and plan for the subscription
            $subscription_model
                ->set('plan', $plan)
                ->set('expires_on', date('Y-m-d H:i:s', $expires_on))
                ->save();

            // The update was successful, create a subscription event
            ORM::factory('Subscription_Event')
                ->set('subscription_id', $subscription_model->get('subscription_id'))
                ->set('from_plan', $subscription_model->get('plan'))
                ->set('to_plan', $plan)
                ->set('date', date('Y-m-d H:i:s'))
                ->set('expires_on', date('Y-m-d H:i:s', $expires_on))
                ->set('payment_id', $payment_id)
                ->save();

            if (self::$use_cache)
            {
                // Update the cache
                Subscription_Cache::instance()->save($account_id, $subscription_model->as_array());
            }

            // Everything is fine, commit
            $subscription_model->commit();

        }
        catch (Exception $e)
        {
            // Something went wrong, rollback
            $subscription_model->rollback();

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Get the subscription plan limits for the given account
     *
     * @param   int     $account_id
     * @return  array
     */
    public function get_subscription_plan_limits($account_id)
    {
        // Get subscription data
        $subscription_data = $this->get_subscription_data($account_id);
        $config = Kohana::$config->load('account/plans');

        return isset($subscription_data)
            ? $config[$subscription_data['plan']]['limits']
            : NULL;
    }

    /**
     * Get subscription events for the given account
     *
     * @param   int     $account_id
     */
    public function get_subscription_events($account_id)
    {
        return ORM::factory('Subscription_Event')
            ->get_subscription_events($account_id);
    }

    /**
     * Set all the subscriptions with the grace period ended `expired`
     */
    public function supervise_subscriptions()
    {
        // Get grace period
        $grace_period = Kohana::$config->load('account')->get('subscription_expiration_grace_period');

        // Calculate start time
        $start_time = time() - $grace_period * 24 * 3600;

        $this->subscription_model()->supervise_subscriptions($start_time);
    }

    /**
     * Return an instance of the Model_Subscription class
     *
     * @return ORM
     */
    public function subscription_model()
    {
        if ( ! isset($this->_subscription_model))
        {
            $this->_subscription_model = ORM::factory('Subscription');
        }

        return $this->_subscription_model;
    }

    /**
     * Returns a singleton instance of the class
     *
     * @return  Subscription_Manager
     */
    public static function instance()
    {
        if ( ! Subscription_Manager::$_instance instanceof Subscription_Manager)
        {
            Subscription_Manager::$_instance = new Subscription_Manager();
        }

        return Subscription_Manager::$_instance;
    }
}

// END Kohana_Subscription_Manager
