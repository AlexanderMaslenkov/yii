<?php

class CampaignController extends Controller{
;
    public $formId = 'campaign-backer-form';

    public function actionAdminList($campaignId){
        $user = $this->getCurrentUserObj();
        $model = Campaign::model()->ownerScope($user->getId())->findByPk($campaignId);
        if(!$model){
            $this->redirect('/ptaAdmin/fundraising/campaign');
        }
        $payments = ExpressPayment::model()->completedScope()->campaignScope($model->id)->newestFirstScope()->withContactScope()->findAll();
        $behalfOf = false;
        foreach($payments as $p){
            if(!empty($p->behalfOfPayment)){
                $behalfOf = true;
                break;
            }
        }
        $newBacker = new ExpressPayment();
        $newBacker->amount = Campaign::DEFAULT_AMOUNT_VALUE;

        $paymentTypes = ExpressPayment::model()->getPaymentTypes();
        Yii::app()->clientScript->registerCssFile(Yii::app()->getTheme()->getBaseUrl().'/css/contacts.css');
        $this->render('/ptaCampaign/backersList', array(
            'campaign' => $model,
            'newBacker' => $newBacker,
            'payments' => $payments,
            'paymentTypes' => $paymentTypes,
            'behalfOf' => $behalfOf
        ));
    }
    public function actionAdminAddBacker($campaignId)
    {
        $perkId = Yii::app()->request->params('perkId');
        $user = $this->getCurrentUserObj();
        $campaign = Campaign::model()->ownerScope($user->getId())->findByPk($campaignId);
        if (!$campaign) {
            $this->redirect('/ptaAdmin/fundraising/campaign');
        }
        $payment = new ExpressPayment('CampaignAsManual');
        $payment->setFundraisingCampaign($campaign);
        if (Yii::app()->request->isAjaxRequest) {
            $this->performAjaxValidation($payment, $this->formId);
        }
        if (Yii::app()->request->isPostRequest) {

            if (!empty($perkId) && $perk = CampaignPerk::model()->findByPk($perkId)) {
                $payment->campaignPerkId = $perk->id;
            }
            $payment->saveAsManual();
        }
        $this->redirect('/ptaAdmin/campaign/backers/' . $campaign->id);
    }
    public function actionAdminAddAjaxBacker()
    {
        if (Yii::app()->request->isPostRequest) {
            $perkId = Yii::app()->request->params('perkId');
            $payment = new ExpressPayment('CampaignAsManual');
            $campaignId = Yii::app()->request->getParam('campaignId', null);
            if ($campaignId) {
                $campaign = Campaign::model()->ownerScope($this->getCurrentUserObj()->getId())->findByPk($campaignId);
            } else {
                $campaign = null;
            }
            if (empty($campaignId) || empty($campaign)) {
                throw new CHttpException(404, 'Not found');
            }
            $payment->setFundraisingCampaign($campaign);
            $payment->setAttributes($_POST[$payment->getPostContainerName()]);
            if (Yii::app()->request->isAjaxRequest) {
                $this->performAjaxValidation($payment, 'campaign-add-backer-form');
            }
            if (!empty($perkId) && $perk = CampaignPerk::model()->findByPk($perkId)) {
                $payment->campaignPerkId = $perk->id;
            }
            if ($payment->saveAsManual()) {
                echo json_encode(['msg' => 'Successfully added backer!', 'success' => true]);
                Yii::app()->end();
            }
            echo json_encode($payment->getErrors());
            Yii::app()->end();
        }
    }
}
