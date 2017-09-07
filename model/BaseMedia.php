<?php
class BaseMedia extends CActiveRecord{

    const MAIN_MEDIA_YES = 1;
    const MAIN_MEDIA_NO = 0;
    public $type;
    public $youtubeHash;
    public $uploadFieldName;
    public $media;
    /**
     * @var Media
     */
    protected $mediaModel;
    public function init(){
        $this->mediaModel = new Media;
        $this->type = $this->mediaModel->type;
    }
    protected function getMediaModel(){
        if(!empty($this->mediaId)){
            $this->mediaModel = Media::model()->findByPk($this->mediaId);
            if(!$this->mediaModel){
                $this->mediaModel = new Media;
            }
        }else{
            $this->mediaModel = new Media;
        }
    }
    protected function afterFind(){
        $this->getMediaModel();
        $this->type = $this->mediaModel->type;
        $this->youtubeHash = $this->mediaModel->youtubeHash;
        parent::afterFind();
    }

    protected function beforeSave(){
        if(isset($this->mediaId) && $this->mediaModel->getIsNewRecord()){
            $this->getMediaModel();
        }
        $this->checkNewMedia();
        $this->mediaModel->uploadFieldName = $this->uploadFieldName;
        $this->mediaModel->type = $this->type;
        $this->mediaModel->youtubeHash = $this->youtubeHash;
        if($this->mediaModel->save()){
            $this->mediaId = $this->mediaModel->id;
            return parent::beforeSave();
        }else{
            $this->addErrors($this->mediaModel->getErrors());
        }
        return false;
    }
    /**
     * @return boolean Whether the media is a video (uploaded file or direct YouTube link)
     */
    public function isVideo(){
        return $this->mediaModel->isVideo();
    }
    /**
     * @return boolean Whether the media is an image
     */
    public function isImage(){
        return $this->mediaModel->isImage();
    }
    /**
     * @return boolean Whether video processing is completed
     */
    public function isVideoCompleted(){
        return $this->mediaModel->isVideoCompleted();
    }
    /**
     * Get image.
     * @param string $type File size type
     * @return string Path to file
     */
    public function getImage($type = 'original'){
        return $this->mediaModel->getImage($type);
    }
    /**
     * @return string Media thumbnail
     */
    public function getThumbnail(){
        return $this->mediaModel->getThumbnail();
    }
    /**
     * @return string Media thumbnail
     */
    public function getInnerThumbnail(){
        return $this->mediaModel->getInnerThumbnail();
    }
    /**
     * @return string Media thumbnail
     */
    public function getRegularThumbnail(){
        return $this->mediaModel->getRegularThumbnail();
    }
    /**
     * @return string Media thumbnail
     */
    public function getOriginalImage(){
        return $this->mediaModel->getOriginalImage();
    }
    public function hasImage(){
        return $this->mediaModel->hasImage();
    }
    public function setNewMedia(){
        if(!$this->mediaModel->getIsNewRecord()){
            $this->mediaModel = new Media;
        }
    }
    protected function checkNewMedia(){
        preg_match(Media::YOUTUBE_PATTERN, $this->youtubeHash, $matches);
        if($this->type != $this->mediaModel->type || (CUploadedFile::getInstanceByName($this->uploadFieldName.'[media]') && $this->type == Media::TYPE_IMAGE) || ($this->type == Media::TYPE_YOUTUBE && isset($matches[1]) && $matches[1] != $this->mediaModel->youtubeHash)){
            $this->setNewMedia();
        }
    }
}
