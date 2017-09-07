<?php
/**
 * This is the model class for table "{{raffles}}".
 *
 * The followings are the available columns in table '{{raffles}}':
 * @property integer $id
 * @property integer $userId
 * @property string $name
 * @property string $createdBy
 * @property string $shortDescription
 * @property string $description
 * @property string $slug
 * @property bool $isDraft
 * @property bool $isHidden
 * @property string $paypal_email
 * @property string $terms
 * @property float $ticketPrice
 * @property integer $ticketQuantity
 * @property string $drawingDate
 * @property bool $isTemplate
 * @property string $createdAt
 * @property string $updatedAt
 * @property bool $isDrawnPrizes
 * @property integer $numViews
 * @property string $payment_address
 * @property string $accounting_code
 * @property integer $approval
 * @property string $tax_id
 * @property bool $drawingType
 *
 * @see Raffle::scopes()
 * @method Raffle published()
 * @method Raffle frontActive()
 *
 * @see Raffle::scopes()
 * @method Raffle withoutTest()
 * @method Raffle active()
 *
 */
class Raffle extends CActiveRecord{
    /**
     * @var string
     */
    public $publicationDate;
    public $completedSteps;
    public $ticketQuantityType;

    /**
     * Draft options
     */
    const DRAFT_YES = 1;
    const DRAFT_NO = 0;

    /**
     * Drawn Prizes options
     */
    const DRAWN_PRIZES_YES = 1;
    const DRAWN_PRIZES_NO = 0;
    const DRAWN_PRIZES_IN_PROCESS = 2;
    const DRAWN_PRIZES_ERROR = -1;
    /** Ticket quantity type options */
    const TICKET_QUANTITY_UNLIMITED = 0;
    const TICKET_QUANTITY_LIMITED = 1;
    /** Drawing type */
    const DRAWING_TYPE_MANUAL = 0;
    const DRAWING_TYPE_AUTO = 1;
    const URL_PREFIX = '/r/';
    const FUNDRAISING_LABEL = 'Drawing';
    const FUNDRAISING_LABEL_MANY = 'Drawings';
    public $idTemplate;
    public $isCompleted;
    protected $changeTaxId = false;
    /**
     * Returns the static model of the specified AR class.
     * @param string $className Active record class name.
     * @return Raffle the static model class
     */
    public static function model($className = __CLASS__){
        return parent::model($className);
    }
    /**
     * @param mixed $pk
     * @param string $condition
     * @param array $params
     * @return Raffle
     */
    public function findByPk($pk, $condition = '', $params = array())
    {
        return parent::findByPk($pk, $condition, $params);
    }
    /**
     * @param $param
     * @return Raffle
     * @throws CHttpException
     */
    public function load($param)
    {
        $model = null;
        if (!$model) {
            $model = FundraisingAliasHistory::model()->getRaffleByAlias($param, false);
        }
        if (is_numeric($param)) {
            $model = $this->findByPk($param);
        }
        if (!$model) {
            throw new CHttpException(404, 'Not found');
        }
        return $model;
    }
    /**
     * @return string the associated database table name
     */
    public function tableName(){
        return '{{raffles}}';
    }
    /**
     * Init
     */
    public function init(){
        $this->completedSteps = 0;
        $this->ticketQuantityType = self::TICKET_QUANTITY_UNLIMITED;
        $this->ticketQuantity = '';
        $this->drawingDate = date('m/d/Y');
        $this->isDraft = self::DRAFT_NO;
        $this->isHidden = self::HIDDEN_NO;
        $this->isTemplate = self::TEMPLATE_NO;
        $this->isDrawnPrizes = self::DRAWN_PRIZES_NO;
        $this->drawingType = self::DRAWING_TYPE_AUTO;
    }
    /**
     * @return array validation rules for model attributes.
     */
    public function rules(){
        return array(
            array('name, createdBy, shortDescription, description, ticketPrice, drawingDate, payment_address', 'required'),
            array('paypal_email', 'email'),
            array('ticketQuantity', 'numerical'),
            array('ticketPrice', 'numerical', 'min' => 0.01, 'allowEmpty' => false),
            array('isDraft, isTemplate', 'boolean'),
            array('completedSteps, ticketQuantityType, tax_id, isCompleted, approval, approvalNote, approvalNoteDate, drawingType, thanksMessage, accounting_code', 'safe'),
            array('name, createdBy, drawingDate, ticketPrice, paypal_email', 'filter', 'filter' => 'trim'),
            ['paypal_email', 'safe'],
        );
    }
    /**
     * @return array Relational rules.
     */
    public function relations(){
        return array(
            'mainMedia' => array(self::HAS_ONE, 'RaffleMedia', 'raffleId', 'scopes' => 'isMain'),
            'additionalMedia' => array(self::HAS_MANY, 'RaffleMedia', 'raffleId', 'scopes' => 'additional'),
            'payments' => array(self::HAS_MANY, 'ExpressPayment', 'raffleId'),
            'prizes' => array(self::HAS_MANY, 'RafflePrize', 'raffleId'),
            'prizesCount' => array(self::STAT, 'RafflePrize', 'raffleId', 'select' => 'COUNT(*)'),
            'deals' => array(self::HAS_MANY, 'RaffleDeal', 'raffleId'),
            'tickets' => array(self::HAS_MANY, 'RaffleTicket', 'raffleId'),
            'ticketsSold' => array(self::STAT, 'ExpressPayment', 'raffleId', 'select' => 'SUM(numTickets)', 'condition' => 'status = :status', 'params' => array(':status' => ExpressPayment::STATUS_COMPLETED)),
            'user' => array(self::BELONGS_TO, 'User', 'userId'),
            'winners' => array(self::HAS_MANY, 'RaffleTicket', 'raffleId', 'condition' => 'prizeId IS NOT NULL and isWinner = :winner', 'params' => array(':winner' => RaffleTicket::WINNER_YES)),
            'rafflePayment' => array(self::HAS_MANY, 'ExpressPayment', 'raffleId')
        );
    }

    public function scopes(){
        return array(
            'draft' => array(
                'condition' => 'isDraft = :draft',
                'params' => array(':draft' => self::DRAFT_YES),
            ),
            'noDraft' => array(
                'condition' => 'isDraft = :draft',
                'params' => array(':draft' => self::DRAFT_NO),
            ),
            'published' => array(
                'condition' => 'isDraft = :draft AND isHidden = :isHidden',
                'params' => array(':draft' => self::DRAFT_NO, ':isHidden' => self::HIDDEN_NO),
            ),
            'unPublished' => array(
                'condition' => 'isDraft = :draft OR isHidden = :isHidden',
                'params' => array(':draft' => self::DRAFT_YES, ':isHidden' => self::HIDDEN_YES),
            ),
            'completed' => array(
                'condition' => 'drawingDate < :date AND isDraft = :draft',
                'params' => array(':date' => date('Y-m-d'), ':draft' => self::DRAFT_NO),
            ),
            'ptaActive' => array(
                'condition' => 'drawingDate >= :now AND isDraft = :draft AND isHidden = :isHidden',
                'params' => array(':now' => date('Y-m-d'), ':draft' => self::DRAFT_NO, ':isHidden' => self::HIDDEN_NO),
            ),
            'ptaCompleted' => array(
                'condition' => 'isDraft = :draft AND isHidden = :isHidden AND (drawingDate < :date OR isDrawnPrizes != :drawnPrizes  )',
                'params' => array(':draft' => self::DRAFT_NO, ':date' => date('Y-m-d'),':drawnPrizes' => self::DRAWN_PRIZES_NO, ':isHidden' => self::HIDDEN_NO),
            ),
            'noDrawnPrizes' => array(
                'condition' => 'isDrawnPrizes = :drawnPrizes AND isTemplate='.self::TEMPLATE_NO.' AND isDraft='.self::DRAFT_NO,
                'params' => array(':drawnPrizes' => self::DRAWN_PRIZES_NO)
            ),
            'autoDrawn' => array(
                'condition' => 'drawingType = :drawingType',
                'params' => array(':drawingType' => self::DRAWING_TYPE_AUTO)
            ),
            'finalCountdown' => array(
                'condition' => 'drawingDate < :date',
                'params' => array(':date' => date('Y-m-d', strtotime('now +7days'))),
            ),
            'newThisWeek' => array(
                'condition' => 't.createdAt >= :date',
                'params' => array(':date' => date('Y-m-d', strtotime('now -7days'))),
            ),
        );
    }

    /**
     * After Find
     * @return mixed
     */
    protected function afterFind(){
        $this->isCompleted = (($this->isDraft == self::DRAFT_NO && $this->drawingDate < date('Y-m-d')) || $this->isDrawnPrizes == self::DRAWN_PRIZES_YES) ? true : false;
        $this->drawingDate = date('m/d/Y H:i', strtotime($this->drawingDate));
        parent::afterFind();
    }
    /**
     * Before Validate
     * @return mixed
     */
    protected function beforeValidate(){
        if($this->scenario != "approval" && isset($_POST['Raffle']['active_method'])){
            if($_POST['Raffle']['active_method'] == "americascharities"){
                $requiredValidator = CValidator::createValidator('required', $this, 'tax_id');
                $this->getValidatorList()->add($requiredValidator);
                unset($requiredValidator);
            }
        }
        if($this->ticketQuantityType == self::TICKET_QUANTITY_LIMITED){
            $requiredValidator = CValidator::createValidator('required', $this, 'ticketQuantity');
            $numericValidator = CValidator::createValidator('numerical', $this, 'ticketQuantity', array('min' => 1, 'allowEmpty' => false));
            $this->getValidatorList()->add($requiredValidator);
            $this->getValidatorList()->add($numericValidator);
            unset($requiredValidator, $numericValidator);
        }
        return parent::beforeValidate();
    }
    /**
     * Before Save
     * @return bool
     */
    public function isActive(){
        if($this->isDraft == self::DRAFT_NO && $this->isTemplate == self::TEMPLATE_NO
            && strtotime($this->drawingDate) > time() && $this->isDrawnPrizes == self::DRAWN_PRIZES_NO){
            return true;
        }
        return false;
    }
    public function getAlias(){
        return $this->slug;
    }
    public function setAlias($string)
    {
        $this->slug = $string;
        return $this;
    }
    public function isCompleted(){
        if($this->isTemplate == self::TEMPLATE_NO && $this->isDraft == self::DRAFT_YES && strtotime($this->drawingDate) < time()){
            return true;
        }
        return false;
    }
    /**
     * @return boolean Whether campaign is isDraft
     */
    public function isPublished() {
        if( $this->isDraft == self::DRAFT_NO && $this->isHidden == self::HIDDEN_NO ) return true;
        return false;
    }
    /**
     * @return boolean Whether model has been isDraft (activated)
     */
    public function setPublish(){
        $this->isDraft = self::DRAFT_NO;
        $this->isHidden = self::HIDDEN_NO;
        return $this->save(false);
    }
    /**
     * @return boolean Whether model has been isDraft
     */
    public function setUnPublish(){
        if ( $this->getAmountSoldTickets() > 0 ) {
            $this->isDraft = self::DRAFT_NO;
            $this->isHidden = self::HIDDEN_YES;
        } else {
            $this->isDraft = self::DRAFT_YES;
            $this->isHidden = self::HIDDEN_NO;
        }
        return $this->save(false);
    }
    public function setPublished($isPublished){
        if( $isPublished ){
            return $this->setPublish();
        }else{
            return $this->setUnPublish();
        }
    }
    public function addView(){
        ++$this->numViews;
        if($this->ticketQuantity != -1){
            $this->ticketQuantityType = self::TICKET_QUANTITY_LIMITED;
        }
        $this->update(array('numViews'));
    }
    /**
     * @return float Total sold tickets
     */
    public function getAmountSoldTickets(){
        return $this->ticketsSold * $this->ticketPrice;
    }
    public function getRemainingTickets(){
        if($this->ticketQuantity != -1){
            return (float)$this->ticketQuantity - $this->ticketsSold;
        }else{
            return 'Unlimited';
        }
    }
    /**
     * Get backers count.
     * @return integer Number of campaign backers
     */
    public function getBackersCount()
    {
        return ExpressPayment::model()->raffleScope($this->id)->completedScope()->count();
    }
    public function getTicketsCount()
    {
        return ExpressPayment::model()->completedScope()->raffleScope($this->id)->getTotalTickets();
    }
    public function getTotalRaised()
    {
        return ExpressPayment::model()->completedScope()->raffleScope($this->id)->getAmountSum();
    }
    /**
     * @return CActiveDataProvider The data provider that returns campaign backers
     */
    public function getBackers(){
        return ExpressPayment::model()->raffleScope($this->id)->completedScope()->search();
    }
    /**
     * Returns an object with ->d (days left) and ->h (hours left)
     * @return array
     */
    public function getTimeLeft(){
        if ( $this->drawingType == self::DRAWING_TYPE_MANUAL ){
            return 'manual';
        }
        $todayDate = date_create();
        $drawDate = date_create($this->drawingDate);
        $diff = date_diff($todayDate, $drawDate);
        return $diff;
    }

    /**
     * Ensures the scope includes only raffles owned by provided userId
     * @param $userId
     * @return $this
     */
    public function userScope($userId){
        $this->getDbCriteria()->mergeWith(array(
            'condition' => 'userId = :user',
            'params' => array(':user' => (int)$userId),
        ));
        return $this;
    }
    public function userIdsScope($userIds){
        $this->getDbCriteria()->addInCondition('t.userId', $userIds);
        return $this;
    }
    public function hasMainMedia(){
        if(empty($this->mainMedia)){
            return false;
        }else{
            return true;
        }
    }

    public function setCompletedStep($step){
        if(!is_int($step)){
            throw new CException('Step must be an integer');
        }
        if(empty($this->completedSteps) || $step > $this->completedSteps){
            $this->completedSteps = (int)$step;
        }
    }
    /**
     * @param null $term
     * @param int $limit
     * @return Raffle[]
     */
    public function searchByTerm($term = null, $limit = 0){
        if($term == null){
            return array();
        }
        $searchByQuery = new SearchByQuery();
        return $searchByQuery->search($term,$this,$limit);
    }
    private function getUserRaffles(CDbCriteria $criteria, $sort, $page){
        $criteria->with = array(
            'rafflePayment' => array(
                'select' => 'SUM(amount) AS totalAmount',
                'group' => 't.id',
                'on' => 'status = "'.ExpressPayment::STATUS_COMPLETED.'"',
            )
        );

        $criteria->together = true;
        $criteria = $this->onlyApproved($criteria);
        $nameFilter = Yii::app()->request->getParam('nameFilter') ? Yii::app()->request->getParam('nameFilter') : false;
        if( $nameFilter ){
            $criteria->addCondition("t.name LIKE '%{$nameFilter}%'");
        }
        $count = Raffle::frontActive()->count($criteria);
        if( !$nameFilter ) {
            $criteria->limit = Fundraising::PUBLIC_PAGE_SIZE;
            $criteria->offset = $page*Fundraising::PUBLIC_PAGE_SIZE;
        }
        return array($count, Raffle::frontActive()->findAll($criteria));
    }

    public function aggregatorRaffles($childIds, $sort = '', $page = 0){
        $criteria = new CDbCriteria;
        $criteria->addInCondition('t.userId', $childIds);
        return $this->getUserRaffles($criteria, $sort, $page);
    }

    public function getIdsByUser($childIds){
        $criteria = new CDbCriteria;
        $criteria->addInCondition('t.userId', $childIds);
        $campaigns = Raffle::frontActive()->findAll($criteria);
        $ids = [];
        if($campaigns){
            foreach($campaigns as $c){
                $ids[]=$c->id;
            }
        }
        return $ids;
    }

    public function userRaffles(User $user, $sort = '', $page = 0){
        $criteria = new CDbCriteria;
        $criteria->addInCondition('t.userId', $user->getSelfAndChildIds());
        return $this->getUserRaffles($criteria, $sort, $page);
    }

    public function getRaffleUrl(){
        return Yii::app()->createUrl('/r/' . $this->slug);
    }

    public function getRaffleAbsoluteUrl(){
        return Yii::app()->createAbsoluteUrl('/r/' . $this->slug);
    }

    public function isOwner( $userId ){
        return $this->userId == $userId;
    }

    public function onlyApproved( $criteria = false ){
        $arr = array(
            'join' => 'LEFT JOIN {{user_childs}} uc ON uc.childId = t.userId
                       LEFT JOIN {{users}} pto ON pto.user_id = t.userId ',
            'condition' => '( (uc.needApproval<>' . UserChilds::CHECK_APPROVAL_CHILD_YES . ')
                                OR t.approval=' . Fundraising::APPROVAL_APPROVED . ' )',
        );
        if( $criteria ){
            $criteria->mergeWith($arr);
            return $criteria;
        }else{
            $this->getDbCriteria()->mergeWith($arr);
            return $this;
        }
    }
    public function beforeDelete()
    {
        FundraisingAliasHistory::model()->deleteRaffleAliases($this);
        return parent::beforeDelete();
    }
    /**
     * @return string
     */
    public function getPaypalEmail()
    {
        return $this->paypal_email;
    }
    /**
     * @param string $paypal_email
     * @return Raffle
     */
    public function setPaypalEmail($paypal_email)
    {
        $this->paypal_email = $paypal_email;
        return $this;
    }
    /**
     * @return string
     */
    public function getPaymentAddress()
    {
        return $this->payment_address;
    }
    /**
     * @param string $payment_address
     * @return Raffle
     */
    public function setPaymentAddress($payment_address)
    {
        $this->payment_address = $payment_address;
        return $this;
    }
    /**
     * @return string
     */
    public function getPublicationDate()
    {
        return $this->publicationDate;
    }
    /**
     * @param string $publicationDate
     * @return Raffle
     */
    public function setPublicationDate($publicationDate)
    {
        $this->publicationDate = $publicationDate;
        return $this;
    }

    public function getStatistic($childIds)
    {
        $rafflesQuery = Yii::app()->db->createCommand()
            ->select('COUNT(t.id) AS numRaffles,  SUM(t.numViews) AS totalViews, SUM(p.totalAmount) AS totalRaised, drawingDate')
            ->from('{{raffles}} t')
            ->join('{{users}} usr', 't.userId = usr.user_id')
            ->leftJoin('(SELECT SUM(amount) AS totalAmount, raffleId  FROM {{express_payments}} WHERE status = :status AND raffleId IS NOT NULL GROUP BY raffleId) p', 'p.raffleId = t.id', array(':status' => ExpressPayment::STATUS_COMPLETED))
            ->leftJoin('{{user_childs}} uc', 'uc.childId = t.userId')
            ->where(array('in', 't.userId', $childIds))
            ->andWhere('t.isDraft = :isDraft AND t.isTemplate = :isTemplate', array(
                ':isDraft' => Raffle::DRAFT_NO,
                ':isTemplate' => Raffle::TEMPLATE_NO . ''
            ))
            ->andWhere('t.approval=:approval OR (usr.level=:level AND uc.needApproval=:needApproval)', [
                ':needApproval' => UserChilds::CHECK_APPROVAL_CHILD_NO,
                ':approval' => Fundraising::APPROVAL_APPROVED,
                ':level' => User::LEVEL_2ND
            ])
            ->group("t.userId");
        return $rafflesQuery->queryAll();
    }
    public function getCountDonors($childIds)
    {
        $raffleDonorsQuery = Yii::app()->db->createCommand()
            ->select('COUNT(p.email) as countDonors, p.email AS email, SUM(p.amount) AS amount')
            ->from('{{raffles}} t')
            ->join('{{users}} usr', 't.userId = usr.user_id')
            ->leftJoin('{{express_payments}} as p', 'p.raffleId=t.id AND p.status=:status AND raffleId IS NOT NULL', array(':status' => ExpressPayment::STATUS_COMPLETED))
            ->leftJoin('{{user_childs}} uc', 'uc.childId = t.userId')
            ->where(array('in', 't.userId', $childIds))
            ->andWhere('isDraft = :isDraft AND isTemplate = :isTemplate', array(
                ':isDraft' => Raffle::DRAFT_NO,
                ':isTemplate' => Raffle::TEMPLATE_NO . ''
            ))
            ->andWhere('t.approval=:approval OR (usr.level=:level AND uc.needApproval=:needApproval)', [
                ':needApproval' => UserChilds::CHECK_APPROVAL_CHILD_NO,
                ':approval' => Fundraising::APPROVAL_APPROVED,
                ':level' => User::LEVEL_2ND
            ]);
        return $raffleDonorsQuery->queryAll();
    }

    public function savePublication()
    {
        if ($this->getPublicationDate() || $this->isDraft == self::DRAFT_YES || $this->isTemplate == self::TEMPLATE_YES) {
            return true;
        }
        $this->setPublicationDate(date('Y-m-d H:i:s'));
        $this->save();
        Yii::app()->notifier->RaffleCreated($this);
        return true;
    }

}
