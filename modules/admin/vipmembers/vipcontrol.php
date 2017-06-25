<?php


namespace IPS\vipmembers\modules\admin\vipmembers;

use \IPS\vipmembers\VMember;
use \IPS\Request;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * vipcontrol
 */
class _vipcontrol extends \IPS\Node\Controller
{
    /**
     * Node Class
     */
    protected $nodeClass = 'IPS\vipmembers\VMember';

    public static $modalForms = true;

    /**
     * Execute
     *
     * @return	void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission( 'vipcontrol_manage' );
        parent::execute();
    }

    public function _getRootButtons()
    {
        $buttons = array();
        return $buttons;
    }

    public function view(){
        $node = VMember::load(Request::i()->id);
        if ($node != null){
            $node->view();
        }
    }

}