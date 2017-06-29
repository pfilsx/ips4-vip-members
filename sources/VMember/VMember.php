<?php

namespace IPS\vipmembers;

use \IPS\Member;
use \IPS\Member\Group;
use \IPS\Db;
use \IPS\Http\Url;
use \IPS\DateTime;
use \IPS\Theme;
use \IPS\Output;
use \IPS\Settings;
use \IPS\core\Messenger\Conversation;
use \IPS\core\Messenger\Message;
use \IPS\Dispatcher;


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
            $vipGroup = Group::load($this->vip_group_id);
            $group = Group::load($this->old_group_id);
            $form = Theme::i()->getTemplate( 'vmember', 'vipmembers', 'admin' )->view($this, $member, $vipGroup, $group);
            Output::i()->output = $form;
        }
         catch (\Exception $ex){
             Output::i()->output = $ex->getTraceAsString();
         }
    }

    public function save(){
        $member = Member::load($this->member_id);
        $member->member_group_id = $this->vip_group_id;
        $member->save();
        parent::save();
        if (Settings::i()->vipmembers_send_notify_on_promote){
            $this->notifyPromote();
        }
    }

    public function delete(){
        $member = Member::load($this->member_id);
        $primaryGroupId = $this->old_group_id;
        $member->member_group_id = $primaryGroupId;
        $member->save();
        parent::delete();
        if (Settings::i()->vipmembers_send_notify_on_downgrade){
            $this->notifyDowngrade();
        }
    }

    protected function notifyPromote(){
        $notifyTitle = Settings::i()->vipmembers_notify_on_promote_title;
        $notifyContent = $this->prepareNotifyContent($this->promotion_ends ?
            Settings::i()->vipmembers_notify_on_promote : Settings::i()->vipmembers_notify_on_promote_permanent);
        $notifySender = Member::load(Settings::i()->vipmembers_notify_user_id);
        $this->sendNotification($notifySender, $notifyTitle, $notifyContent);
    }

    protected function notifyDowngrade(){
        $notifyTitle = Settings::i()->vipmembers_notify_on_downgrade_title;
        $notifyContent = $this->prepareNotifyContent(Settings::i()->vipmembers_notify_on_downgrade);
        $notifySender = Member::load(Settings::i()->vipmembers_notify_user_id);
        $this->sendNotification($notifySender, $notifyTitle, $notifyContent);
    }

    protected function sendNotification($notifySender, $notifyTitle, $notifyContent){
        if (empty($notifySender) || empty($notifyTitle) || empty($notifyContent)){
            return;
        }
        /* Need to trick \IPS\core\Messenger\Message into thinking we're on the front side, due to checking module permissions */
        $controllerLocation = Dispatcher::i()->controllerLocation;
        Dispatcher::i()->controllerLocation = 'front';
        try
        {
            /* Create conversation */
            $conversation = Conversation::createItem($notifySender, $notifySender->ip_address, \IPS\DateTime::ts(time()));
            $conversation->title = $notifyTitle;
            $conversation->is_system = TRUE;
            $conversation->save();

            /* Add message */
            $message = Message::create($conversation, $notifyContent, TRUE, NULL, FALSE, $notifySender);
            $conversation->first_msg_id = $message->id;

            $conversation->authorize(Member::load($this->member_id));
            $conversation->save();
            $message->sendNotifications();
        }
        catch( \Exception $e )
        {
            // This needs to send notifications about new message to user
        }
        Dispatcher::i()->controllerLocation = $controllerLocation;
    }

    protected function prepareNotifyContent($template){

        if (!$this->member_id || !$this->promotion_ends) {
            return $template;
        }
        $member = Member::load($this->member_id);
        $promotionEnds = explode(' ',$this->promotion_ends)[0];
        $patterns = array(
            '{name}',
            '{ends}'
        );
        $replaces = array(
            $member->real_name,
            $promotionEnds
        );

        $result = str_replace($patterns, $replaces, $template);
        return $result;
    }
}