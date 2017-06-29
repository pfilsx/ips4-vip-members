<?php


namespace IPS\vipmembers\modules\admin\vipmembers;

use \IPS\vipmembers\VMember;
use \IPS\Helpers\Form;
use \IPS\Member\Group;
use \IPS\Output;
use \IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * addvip
 */
class _addvip extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'addvip_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
        Output::i()->title = Member::loggedIn()->language()->addToStack('vipmembers_add');

        $form = new Form;

        $form->addHeader( 'vipmembers_add' );

        $memberInput = new \IPS\Helpers\Form\Member('vipmembers_member', null, true);
        $groupInput = new \IPS\Helpers\Form\Select( 'vipmembers_group', null, true, array(
            'options' =>  Group::groups()
        ));
        $timeInput = new \IPS\Helpers\Form\Number( 'vipmembers_time', 1, true,
            array( 'unlimited' => 0, 'unlimitedLang' => 'vipmembers_infinity', 'min' => 1));

        $form->add($memberInput);
        $form->add($groupInput);

        $form->add($timeInput);

        if ($values = $form->values())
        {
            if (($valResult = $this->_validate($values)) !== true){
                $form->addMessage($valResult, 'ipsMessage ipsMessage_error');
                $memberInput->setValue();
                $groupInput->setValue();
                $timeInput->setValue();
            }
            else if ($values['vipmembers_member']->member_group_id == $values['vipmembers_group']){
                $form->addMessage('vipmembers_already_in_group', 'ipsMessage ipsMessage_error');
            } else {
                $member = $values['vipmembers_member'];

                if (VMember::findByMember($member) != null){
                    $form->addMessage('vipmembers_already_exists', 'ipsMessage ipsMessage_error');
                } else {
                    $promotion = new VMember();
                    $promotion->member_id = $member->member_id;
                    $promotion->old_group_id = $member->member_group_id;
                    $promotion->vip_group_id = $values['vipmembers_group'];
                    $promotion->promotion_ends = $values['vipmembers_time'] == 0
                        ? null
                        : date('Y-m-d', (strtotime(date('d.m.Y')) + $values['vipmembers_time']*24*60*60));
                    $promotion->save();
                    $url = \IPS\Http\Url::internal( "app=vipmembers&module=vipmembers&controller=addvip&fromPromote=true" );
                    \IPS\Output::i()->redirect( $url, 'completed');
                }
            }
        }
        Output::i()->output = $form;
	}

	protected function _validate($values){
	    if (!isset($values['vipmembers_member']->member_id)){
	        return 'vipmembers_no_member_id';
        }
        if (!isset($values['vipmembers_group'])){
	        return 'vipmembers_no_group_id';
        }
        if (!isset($values['vipmembers_time'])){
            return 'vipmembers_no_time';
        }
        return true;
    }
}