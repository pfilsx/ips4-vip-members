<?php


namespace IPS\vipmembers\modules\admin\vipmembers;

use \IPS\Member\Group;
use \IPS\Member;
use \IPS\vipmembers\VMember;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('vipmembers_settings');

        $form = new \IPS\Helpers\Form;
        $sender = \IPS\Member::load(\IPS\Settings::i()->vipmembers_notify_user_id);

        $form->addHeader('vipmembers_general_settings');
        $form->add(new \IPS\Helpers\Form\Member('vipmembers_notify_user_id', $sender));

        $form->addHeader( 'vipmembers_notify_on_promote_block');
        $form->add( new \IPS\Helpers\Form\YesNo( 'vipmembers_send_notify_on_promote',
            \IPS\Settings::i()->vipmembers_send_notify_on_promote));
        $form->add(new \IPS\Helpers\Form\Text('vipmembers_notify_on_promote_title',
            \IPS\Settings::i()->vipmembers_notify_on_promote_title));

        $form->add(new \IPS\Helpers\Form\TextArea('vipmembers_notify_on_promote',
            \IPS\Settings::i()->vipmembers_notify_on_promote, false, array(), null, null,
            \IPS\Member::loggedIn()->language()->addToStack('vipmembers_notify_help')));

        $form->add(new \IPS\Helpers\Form\TextArea('vipmembers_notify_on_promote_permanent',
            \IPS\Settings::i()->vipmembers_notify_on_promote_permanent, false, array(), null, null,
            \IPS\Member::loggedIn()->language()->addToStack('vipmembers_notify_permanent')));


        $form->addHeader( 'vipmembers_notify_on_downgrade_block');
        $form->add( new \IPS\Helpers\Form\YesNo( 'vipmembers_send_notify_on_downgrade',
            \IPS\Settings::i()->vipmembers_send_notify_on_downgrade));
        $form->add(new \IPS\Helpers\Form\Text('vipmembers_notify_on_downgrade_title',
            \IPS\Settings::i()->vipmembers_notify_on_downgrade_title));
        $form->add(new \IPS\Helpers\Form\TextArea('vipmembers_notify_on_downgrade',
            \IPS\Settings::i()->vipmembers_notify_on_downgrade,false, array(), null, null,
            \IPS\Member::loggedIn()->language()->addToStack('vipmembers_notify_help')));

        if ( $values = $form->values() )
        {
//            $notification = new \IPS\Notification( \IPS\Application::load('vipmembers'), 'vipmembers_notify', \IPS\vipmembers\VMember::load(1), array('test' => 1) );
//            $notification->recipients->attach( \IPS\Member::load(2) );
//            $notification->send();
            if (isset($values['vipmembers_notify_user_id']) && $values['vipmembers_notify_user_id'] instanceof \IPS\Member){
                $values['vipmembers_notify_user_id'] = $values['vipmembers_notify_user_id']->member_id;
            }
            $form->saveAsSettings($values);
        }

        \IPS\Output::i()->output = $form;

	}
}