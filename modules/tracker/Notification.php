<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

require_once BASE_PATH.'/modules/api/library/APIEnabledNotification.php';

/**
 * Notification manager for the tracker module.
 *
 * @property Tracker_ScalarModel $Tracker_Scalar
 * @property Tracker_TrendModel $Tracker_Trend
 */
class Tracker_Notification extends ApiEnabled_Notification
{
    /** @var string */
    public $moduleName = 'tracker';

    /** @var array */
    public $_models = array('User');

    /** @var array */
    public $_moduleModels = array('Scalar', 'Trend');

    /** @var array */
    public $_moduleComponents = array('Api');

    /** @var string */
    public $moduleWebroot = null;

    /** @var string */
    public $webroot = null;

    /** Initialize the notification process. */
    public function init()
    {
        $this->webroot = Zend_Controller_Front::getInstance()->getBaseUrl();
        $this->moduleWebroot = $this->webroot.'/'.$this->moduleName;

        $this->addCallBack('CALLBACK_CORE_GET_COMMUNITY_VIEW_TABS', 'communityViewTabs');
        $this->addCallBack('CALLBACK_CORE_GET_COMMUNITY_DELETED', 'communityDeleted');
        $this->addCallBack('CALLBACK_CORE_COMMUNITY_DELETED', 'communityDeleted');
        $this->addCallBack('CALLBACK_CORE_ITEM_DELETED', 'itemDeleted');
        $this->addCallBack('CALLBACK_CORE_USER_DELETED', 'userDeleted');

        $this->addTask('TASK_TRACKER_SEND_THRESHOLD_NOTIFICATION', 'sendEmail', 'Send threshold violation email');
        $this->addTask(
            'TASK_TRACKER_DELETE_TEMP_SCALAR',
            'deleteTempScalar',
            'Delete an unofficial/temporary scalar value'
        );
        $this->enableWebAPI($this->moduleName);
    }

    /**
     * Show the trackers tab on the community view page.
     *
     * @param array $args associative array of parameters including the key "community"
     * @return array
     */
    public function communityViewTabs($args)
    {
        /** @var CommunityDao $communityDao */
        $communityDao = $args['community'];

        return array('Trackers' => $this->moduleWebroot.'/producer/list?communityId='.$communityDao->getKey());
    }

    /**
     * When a community is deleted, we must delete all associated producers.
     *
     * @todo
     * @param array $args associative array of parameters including the key "community"
     */
    public function communityDeleted($args)
    {
        // TODO: Implement communityDeleted().
    }

    /**
     * When an item is deleted, we must delete associated item2scalar records.
     *
     * @todo
     * @param array $args associative array of parameters including the key "item"
     */
    public function itemDeleted($args)
    {
        // TODO: Implement itemDeleted().
    }

    /**
     * When a user is deleted, we should delete their threshold notifications.
     *
     * @todo
     * @param array $args associative array of parameters including the key "userDao"
     */
    public function userDeleted($args)
    {
        // TODO: Implement userDeleted().
    }

    /**
     * Delete temporary (unofficial) scalars after n hours, where n is specified as
     * a module configuration option.
     *
     * @param array $params associative array of parameters including the key "scalarId"
     */
    public function deleteTempScalar($params)
    {
        /** @var int $scalarId */
        $scalarId = $params['scalarId'];

        /** @var Tracker_ScalarDao $scalarDao */
        $scalarDao = $this->Tracker_Scalar->load($scalarId);
        $this->Tracker_Scalar->delete($scalarDao);
    }

    /**
     * Send an email to the user that a threshold was crossed.
     *
     * @param array $params associative array of parameters including the keys "notification" and "scalar"
     */
    public function sendEmail($params)
    {
        /** @var array $notification */
        $notification = $params['notification'];

        /** @var UserDao $userDao */
        $userDao = $this->User->load($notification['recipient_id']);

        if ($userDao === false) {
            $this->getLogger()->warn(
                'Attempting to send threshold notification to user id '.$notification['recipient_id'].': No such user.'
            );

            return;
        }

        /** @var array $scalar */
        $scalar = $params['scalar'];

        /** @var Tracker_TrendDao $trendDao */
        $trendDao = $this->Tracker_Trend->load($scalar['trend_id']);

        if ($trendDao === false) {
            $this->getLogger()->warn(
                'Attempting to send threshold notification for trend id '.$scalar['trend_id'].': No such trend.'
            );

            return;
        }

        $producerDao = $trendDao->getProducer();
        $fullUrl = UtilityComponent::getServerURL().$this->webroot;
        $email = $userDao->getEmail();
        $subject = 'Tracker Dashboard Threshold Notification';
        $body = 'Hello,<br/><br/>This email was sent because a submitted scalar value exceeded a threshold that you specified.<br/><br/>';
        $body .= '<b>Community:</b> <a href="'.$fullUrl.'/community/'.$producerDao->getCommunityId(
            ).'">'.htmlspecialchars($producerDao->getCommunity()->getName(), ENT_QUOTES, 'UTF-8').'</a><br/>';
        $body .= '<b>Producer:</b> <a href="'.$fullUrl.'/'.$this->moduleName.'/producer/view?producerId='.$producerDao->getKey(
            ).'">'.htmlspecialchars($producerDao->getDisplayName(), ENT_QUOTES, 'UTF-8').'</a><br/>';
        $body .= '<b>Trend:</b> <a href="'.$fullUrl.'/'.$this->moduleName.'/trend/view?trendId='.$trendDao->getKey(
            ).'">'.htmlspecialchars($trendDao->getMetric()->getDisplayName(), ENT_QUOTES, 'UTF-8').'</a><br/>';
        $body .= '<b>Value:</b> '.htmlspecialchars($scalar['value'], ENT_QUOTES, 'UTF-8');

        Zend_Registry::get('notifier')->callback(
            'CALLBACK_CORE_SEND_MAIL_MESSAGE',
            array(
                'to' => $email,
                'subject' => $subject,
                'html' => $body,
                'event' => 'tracker_threshold_crossed',
            )
        );
    }
}
