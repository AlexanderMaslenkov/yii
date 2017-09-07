<?php
Yii::import('ext.yii-ckeditor.CKEditorWidget');

class VolunteerController extends Controller{

    const PAGE_SIZE = 15;
    public function filters(){
        return array(
            'accessControl',
        );
    }
    public function accessRules(){
        return array(
            array('deny',
                  'actions'=>array('index', 'drafts', 'create', 'edit', 'delete', 'preview', 'previewBecome'),
                  'users'=>array('?'),
            ),
        );
    }
    public function actionIndex(){

        $user = Yii::app()->getUser();
        $activeEvents = VolunteerEvents::model()->activeScope()->userScope($user->id)->newestFirst()->noDraft()->findAll();
        $completedEvents = VolunteerEvents::model()->completedScope()->userScope($user->id)->newestFirst()->findAll();

        $this->render('/ptaVolunteer/eventsList', array(
            'activeEvents' => $activeEvents,
            'completedEvents' => $completedEvents,
            'user' => $user,
        ));
    }
    public function actionDrafts(){
        $this->tab_active = 'my_drafts';
        $user = Yii::app()->getUser();
        $eventDrafts = VolunteerEvents::model()->draft()->userScope($user->id)->newestFirst()->findAll();
        $this->render('/ptaVolunteer/eventDraftsList', array(
            'eventDrafts' => $eventDrafts,
            'user' => $user
            ,));
    }
    public function actionVolunteers($eventId){
        $user = Yii::app()->getUser();
        $model = VolunteerEvents::model()->userScope($user->id)->findByPK($eventId);
        if(!$model){
            $this->redirect('ptaAdmin/volunteer/events');
        }

        $volunteers = new CActiveDataProvider('Volunteers', [
            'pagination'=>false,
            'criteria'=>array(
                'condition'=>'eventId=:eventId',
                'params'=>array(
                    ':eventId'=>$model->id
                )
            )
        ]);
        $this->render('/ptaVolunteer/volunteersList', array(
            'model' => $model,
            'volunteers' => $volunteers,
            'action' => 'List Volunteers'
        ));
    }

    public function actionUpdateFieldSlot(){
        $volunteerId = Yii::app()->request->params('volunteerId');
        $model = VolunteerEventSlot::model()->findByPk($volunteerId);
        $model->scenario = 'update';
        return $model->save();
    }

    public function actionEdit($eventId){
        $request = Yii::app()->request;

        if ($request->isAjaxRequest){
            $step = (isset($request->params('toStep')));
            $this->validateSteps($step);
        }
        $model = $this->loadUserModelAlias($eventId);
        if($request->isPostRequest){
            if($this->saveEvent($model)){

                    Yii::app()->user->setFlash('success', 'Successfully created signup draft');
                    $this->redirect('/ptaAdmin/volunteer/event/drafts');
            }
        }
        if($model->mainMedia){
            $mainMedia = $model->mainMedia;
        }else{
            $mainMedia = new VolunteerMedia;
        }

        $slotsEvent= new CActiveDataProvider('VolunteerEventSlot', array(
            'criteria'=>array(
                'condition'=>'eventId=:eventId',
                'params'=>array(
                    ':eventId'=>$model->id
                )
            )
        ));
        // Forms
        $slotForm = new VolunteerEventSlot();
        $timeSlotForm = new VolunteerTimeSlotsForm();
        $recurringDaysForm = new VolunteerRecurringDaysForm();
        // Dates
        $datesEvent= new CActiveDataProvider('VolunteerEventDate', array(
            'pagination' => [
                'pageSize' => self::PAGE_SIZE,
                'route'=>'ptaVolunteer/AjaxGridDates/eventId/'.$model->id
            ],
            'criteria'=>array(
                'condition'=>'eventId=:eventId',
                'params'=>array(
                    ':eventId'=>$model->id
                )
            )
        ));
        $slots = VolunteerEventSlot::getSlotsEvent($model->id);
        $user = $this->getCurrentUserObj();
        $newStatus = ApprovalEvent::getNewStatus($user, false, Fundraising::TYPE_VOLUNTEER_EVENT, $model->id, $model);

        $this->render('/ptaVolunteer/eventForm', array(
            'model' => $model,
            'newStatus' => $newStatus,
            'mainMedia' => $mainMedia,
            'action' =>$action,
            'additionalMedia'=> [],
            'slotsEvent'=> $slotsEvent,
            'slots'=> json_encode($slots),
            'datesEvent' => $datesEvent,
            'slotForm' => $slotForm,
            'timeSlotForm' => $timeSlotForm,
            'recurringDaysForm' => $recurringDaysForm,
            'isNew' => false
        ));
    }
    public function actionGetSlotsSource($eventId){
        $slots = VolunteerEventSlot::getSlotsEvent($eventId);
        echo json_encode($slots);
    }
    public function actionAjaxAdd($eventId, $type){
        switch($type){
            case 'slot':
                $this->addNewSlotEvent($eventId);
                break;
            case 'timeSlot':
                $this->addNewDates($eventId, 'timeSlot');
                break;
            case 'recurringDays':
                $this->addNewDates($eventId, 'recurringDays');
                break;
            default:
                echo json_encode(["error"=>"Unknown type date"]);
                break;
        }
        Yii::app()->end();
    }
    public function actionAjaxGridSlots($eventId){
        $this->checkAjax($eventId);
        $slotsEvent=new CActiveDataProvider('VolunteerEventSlot', [
            'criteria'=>array(
                'condition'=>'eventId=:eventId',
                'params'=>array(
                    ':eventId'=>$eventId
                )
            )
        ]);
        $this->renderPartial('/ptaVolunteer/grids/_gridSlots',array(
            'slotsEvent'=>$slotsEvent,
            'eventId'=>$eventId
        ));
    }
    public function actionAjaxGridDates($eventId){
        $this->checkAjax($eventId);
        $datesEvent=new CActiveDataProvider('VolunteerEventDate', [
            'pagination' => [
                'pageSize' => 15,
                'route'=>'ptaVolunteer/AjaxGridDates/eventId/'.$eventId
            ],
            'criteria'=>array(
                'condition'=>'eventId=:eventId',
                'params'=>array(
                    ':eventId'=>$eventId,
                )
            )
        ]);
        $allSlots = VolunteerEventSlot::model()->eventScope($eventId)->findAll();
        $slots = [];
        foreach($allSlots as $slot){
            $slots[] = ['value'=>$slot->id, 'text'=>$slot->title];
        }
        $this->renderPartial('/ptaVolunteer/grids/_gridDates',array(
            'datesEvent'=>$datesEvent,
            'eventId'=>$eventId,
            'slots'=>json_encode($slots)
        ),false, false);
    }
    public function actionDeleteEvent($volunteerId){
        $model = VolunteerEvents::model()->findByPk($volunteerId);

        if($model->isActive() && $model->ticketsSold != 0){
            Yii::app()->user->setFlash('error', 'You cannot delete a event that is active and has sold tickets');
            $this->redirect('/ptaAdmin/fundraising/volunteer/event');
        }
        if($model->delete()){
            Yii::app()->user->setFlash('success', 'Successfully deleted event');
            $this->redirect('/ptaAdmin/volunteer/event/drafts');
        }else{
            Yii::app()->user->setFlash('error', 'There was a problem with deleting your event. Please contact edbacker support.');
            $this->redirect('/ptaAdmin/fundraising/volunteer/event');
        }
    }
    public function actionPreview(){
        $request = Yii::app()->request;
        if($request->getParam('id')){
            $model = VolunteerEvents::model()->findByPk($request->getParam('id'));
            $this->layout = '//layouts/preview/volunteer';
            $model->createdBy = $model->createdBy();
            $slots = VolunteerEventSlot::model()->eventScope($model->id)->findAll(['group'=>'title']);
            $this->render('/frontVolunteer/eventView', array(
                'user' => Yii::app()->getUser(),
                'model' => $model,
                'slots' => $slots,
                'isPreview'=>true
            ));
        }
    }
    public function actionPreviewBecome(){
        $request = Yii::app()->request;
        if($request->getParam('id')){
            $model = VolunteerEvents::model()->findByPk($request->getParam('id'));
            $model->createdBy = User::getFullName($model->user_id);
            $user = Yii::app()->getUser();
            $volunteer = new Volunteers;
            $datesEvent = VolunteerEventDate::model()->eventScope($model->id)->findAll();
            $dates = [];
            foreach($datesEvent as $date){

                $slots = [];
                $dates[$date['date']][] = ['id'=>$date['id'], 'startTime'=>$date['startTime'],'endTime'=>$date['endTime'],'location'=>$date['location'], 'slots'=>$slots];
            }
            if(Yii::app()->session->get("volunteer{$model->id}.checked")){
                $checked = Yii::app()->session->get("volunteer{$model->id}.checked");
            }else{
                $checked = [];
            }
            $this->render('/frontVolunteer/eventBecomeVolunteer_step1', array(
                'user' => $user,
                'event' => $model,
                'volunteer'=>$volunteer,
                'success' => "",
                'dates'=>$dates,
                'checked'=> $checked,
                'error' => "",
                'isPreview'=>true
            ));
        }
    }

    public function actionPublish($id, $isPublished){
        $model = $this->loadUserModel($id, Yii::app()->user->getId());
        $model->setPublished($isPublished);
        if ($isPublished && $model->validate()) {
            $this->savePublication($model);
            Yii::app()->user->setFlash('success', VolunteerEvents::FUNDRAISING_LABEL.' "'.$model->name.'" successful '.($isPublished?'published':'unpublished'));
        } else {
            Yii::app()->user->setFlash('error', "Error while ".VolunteerEvents::FUNDRAISING_LABEL." validation, please check that all fields was filled and valid. ".VolunteerEvents::FUNDRAISING_LABEL." is NOT published");
        }
        $this->redirect(array('ptaAdmin/volunteer/events'));
    }

    public function actionUnpublish($id, $isPublished){
        $model = $this->loadUserModel($id, Yii::app()->user->getId());
        $model->setPublished($isPublished);
        if ($isPublished && $model->validate()) {
            $this->savePublication($model);
            Yii::app()->user->setFlash('success',  VolunteerEvents::FUNDRAISING_LABEL.' "'.$model->name.'" successful '.($isPublished?'published':'unpublished'));
        } else {
            Yii::app()->user->setFlash('error', "Error while ".VolunteerEvents::FUNDRAISING_LABEL." validation, please check that all fields was filled and valid. ".VolunteerEvents::FUNDRAISING_LABEL." is NOT published");
        }
        $this->redirect(array('ptaAdmin/volunteer/events'));
    }

    private function ajaxValidate(&$model, &$data, $validationFields=[]){
        $model->setAttributes($data);
        if(!$model->validate($validationFields)){
            $errors = $model->getErrors();
            if(!empty($errors)){
                echo json_encode($errors);
                Yii::app()->end();
            }
        }
    }

    private function saveEvent(VolunteerEvents $model){
        $model->setAttributes($_POST['VolunteerEvents']);
        $user = $this->getCurrentUserObj();
        $model->user_id = $user->user_id;
        $isNew = $model->getIsNewRecord();
        $oldApprovalStatus = $isNew ? null : (int)$model->approval;
        if(!$model->alias){
            $alias = strtolower(str_replace(' ', '-', preg_replace("/[^A-Za-z0-9 ]/", '', $model->name)));
            if(!empty($model->alias)){
                if(!preg_match('#^('.$alias.'_[0-9]*)$#si', $model->alias)){
                    $model->alias = $alias;
                }
            }else{
                $model->alias = $alias;
            }
            while(true){
                $existsAlias = VolunteerEvents::model()->findByAlias($model->alias);
                if($existsAlias){
                    $model->alias .= '_';
                }else{
                    break;
                }
            }
        }
        if( !$model->isDraft ){
            if ($isNew) {
                $model->approval = ApprovalEvent::getNewStatus($user, true, Fundraising::TYPE_VOLUNTEER_EVENT, false, $model);
            } else {
                $model->approval = ApprovalEvent::getNewStatus($user, false, Fundraising::TYPE_VOLUNTEER_EVENT, $model->id, $model);
            }
        }
        if($model->save()){
            if (!empty($_POST['toStep']) && $_POST['toStep'] == 4) {
                $model->savePublication();
            }
            if( !($isNew && $model->approval == Fundraising::APPROVAL_APPROVED) && $model->approval !== $oldApprovalStatus
                && $model->isDraft == Event::DRAFT_NO ){
                $approvalEvent = new ApprovalEvent();
                $approvalEvent->setAttribute('event', ApprovalEvent::EVENT_TYPE_STATUS_CHANGE);
                $approvalEvent->setAttribute('entityType', Fundraising::TYPE_VOLUNTEER_EVENT);
                $approvalEvent->setAttribute('entityId', $model->id);
                $approvalEvent->setAttribute('user_id', $this->userInfo['user_id']);
                $approvalEvent->setAttribute('old_status', $oldApprovalStatus);
                $approvalEvent->setAttribute('new_status', $model->approval);
                $approvalEvent->save();
                Yii::app()->notifier->sendApprovalNotificationByWaiting($model);
            }
            return true;
        }
        return false;
    }
    private function loadUserModelAlias($alias){
        $model = VolunteerEvents::model()->userScope(Yii::app()->getUser()->getId())->findByPk($alias);
        if(!$model){
            Yii::log('VolunteerEvents '.$alias.' edit try exception for user_id ' .Yii::app()->getUser()->getId().'', CLogger::LEVEL_INFO, 'PtaAdmin');
            throw new CHttpException(404, 'Not found');
        }
        return $model;
    }
    /**
     * @param $id
     * @param $userId
     * @return VolunteerEvents
     * @throws CHttpException
     */
    private function loadUserModel($id, $userId){
        $model = VolunteerEvents::model()->userScope($userId)->findByPk($id);
        if(!$model){
            throw new CHttpException(404, 'Not found');
        }
        return $model;
    }
}
