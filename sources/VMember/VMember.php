<?php

namespace IPS\vipmembers;

use \IPS\Member;
use \IPS\Member\Group;
use \IPS\Db;
use \IPS\Http\Url;
use \IPS\DateTime;
use \IPS\Theme;
use \IPS\Output;


/* To prevent PHP errors (extending class does not exist) revealing path */
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class _VMember extends \IPS\Node\Model
{

    protected static $multitons;
    public static $databaseColumnId = 'promotion_id';
    public static $databaseIdFields = array('member_id');
    public static $databaseTable = 'vipmembers_promotions';
    public static $databasePrefix = '';
    public static $nodeTitle = 'vipmembers_node';
    public static $nodeSortable = true;
    public static $modalForms = true;

    public function get__title()
    {
        if (!$this->member_id) {
            return '';
        }
        return Member::load($this->member_id)->real_name;
    }


    public static function search($column, $query, $order = NULL, $where = array())
    {
        $memberIds = array();
        foreach (Db::i()->select(Member::$databaseColumnId, Member::$databaseTable,
            array_merge(array(array("name LIKE CONCAT( '%', ?, '%' )", $query)), array()), null) as $k => $data)
        {
            $memberIds[] = $data;
        }
        $nodes = array();
        foreach (Db::i()->select('*', self::$databaseTable, 'member_id IN (' . implode(',', $memberIds) . ')', null) as $k => $data)
        {
            $nodes[$k] = self::constructFromData($data);
        }
        return $nodes;
    }

    public static function findByMember($member){
        return self::findByMemberId($member->member_id);
    }

    public static function findByMemberId($memberId){
        $result = Db::i()->select('*', self::$databaseTable, array('member_id=?', $memberId));
        if ($result->count() > 0){
            return self::constructFromData($result->first());
        }
        return null;
    }

    public function getButtons($url, $subnode = FALSE)
    {
        $buttons = parent::getButtons($url, $subnode);
        unset($buttons['copy']);
        $resultButtons['view'] = array(
            'icon'	=> 'eye',
            'title'	=> 'vipmembers_view',
            'link'	=> Url::internal( "app=vipmembers&module=vipmembers&controller=vipcontrol&do=view&id={$this->promotion_id}"),
            'data'	=> ( self::$modalForms
                ? array( 'ipsDialog' => '', 'ipsDialog-title' => Member::loggedIn()->language()->addToStack('vipmembers_view') )
                : array() )
        );
        $buttons = array_merge($resultButtons, $buttons);
        return $buttons;
    }

    public function form($form){
            $form->add(new \IPS\Helpers\Form\Date('promotion_ends',
                    ($this->promotion_ends == null ? 0 : $this->promotion_ends), true, array(
                        'unlimited' => 0,
                        'unlimitedLang' => 'vipmembers_infinity',
                        'min' => new DateTime(date('Y-m-d')))
                    )
            );
    }

    public function url()
    {
        if( $this->_url === NULL )
        {
            $this->_url = Url::internal( "app=vipmembers&module=vipmembers&controller=vipcontrol&do=view&id={$this->promotion_id}");
        }

        return $this->_url;
    }

    public function saveForm( $values )
    {
        $values['promotion_ends'] = ($values['promotion_ends'] == 0 ? null:$values['promotion_ends']->format('Y-m-d'));
        parent::saveForm( $values );
    }

    public function view(){
        try {
            $member = Member::load($this->member_id);
            $group = Group::load($member->member_group_id);
            $form = Theme::i()->getTemplate( 'vmember', 'vipmembers', 'admin' )->view($this, $member, $group);
            Output::i()->output = $form;
        }
         catch (\Exception $ex){
             Output::i()->output = $ex->getMessage();
         }
    }

    public function delete(){
        $member = Member::load($this->member_id);
        $primaryGroupId = $this->old_group_id;
        $member->member_group_id = $primaryGroupId;
        $member->save();
        //TODO сообщение об отключении VIP
        parent::delete();
    }


}