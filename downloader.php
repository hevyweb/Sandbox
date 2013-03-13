<?php
/*
  @Class that allow you to upload pictures
  @author Dima Dzyuba
  @authorEmail joomsend@gmail.com
  @created 21.10.2011
  @version 2.0.1
 */

class Downloader {

    private $max_width = 0; //Width of the main file will be not more than this size after resize. If 0 photo will not be resized in width.
    private $max_height = 0; //Height of the main file will be not more than this size after resize. If 0 photo will not be resized in height.
    private $min_width = 0; //Min width of the picture.
    private $min_height = 0; //Min height of picture.
    private $quality = 100; //For jpeg
    private $crop = false; // If this parameter is false, image will be resized, other way - cropped to $maxwidth & $maxheight
    private $watermark = false; //Crete watermark
    private $watermark_file = ""; //Absolute path to watermark
    private $mark_x_coord = -20; //Watermart x coordinate (If > 0 zero is on the left side of the picture, else zero is on the right side)
    private $mark_y_coord = -20; //Watermart y coordinate (If > 0 zero is on the top of the picture, else zero is on the bottom)
    private $datadir = "foto"; //Destination folder
    private $error = array(); //error array
    private $max_weight = 0; //Maximal weight in bytes of the uploaded picture
    private $fileinfo = ""; //contain information about the provided picture
    private $output_type = ""; //if its necessary you can define output type of the image, (jpeg,jpg, gif, png)
    private $filename = ""; //if its necessary you can define output name of the image. No Extention allowed
    private $tumbnails = array(); //an array of the same properties of the class but for thumbnails
    private $valid_extentions = array("gif", "jpg", "png"); //valid extentions of the input file
    private $coords = array(); //Coord of top-left and right-bottom spots from the original picture to cut into smaller ones.
    private $image_cart = array(); //storage for date about created items

    public function __construct($private) {
        $this->initialise($private);
    }

    /**
     * initialize properties of class
     *
     * @param array $privates an array of the parameters : name  of property - > value
     */
    public function initialise($privates = false, $value = false) {
        if (count($privates) > 0) {
            foreach ($privates as $key => $value) {
                $this->$key = $value;
            }
        } else {
            if ($value && $privates) {
                $this->$privates = $value;
            }
        }
    }

    /**
     * Gets certain property of class, all properties or all properties if
     * that poperty is not exists.
     *
     * @param string $param name of the property.
     * @return mixed property or array of properties of class.
     */

    public function getParam($param=null){
        if (!empty($param)){
            if (isset($this->$param))
                return $this->$param;
            else
                return false;
        } else {
            return (array)$this;
        }
    }

    /**
     * check filepath before upload
     *
     * @param string $file name of the parametr trasferred from form
     * @param mixed $id if first parametr is array, means the key of this array
     * @return boolean true|false
     */
    public function upload($file, $id = null) {
        $files=$this->getFilePath($file, $id);
        if (is_array($files)){
            if (isset($files['filename'])){
                return $this->start($files['filename'], $value['filepath']);
            } else {
                $return = array();
                foreach($files as $key=>$value){
                    if (isset($value['filename']) && isset($value['filepath'])){
                        $filename=$this->start($value['filename'], $value['filepath']);
                        if ($filename){
                            $return[$filename]['fileinfo']=$this->fileinfo;
                            $return[$filename]['tumbnails']=$this->tumbnails;
                        }
                    }
                }

                if (count($return)==0){
                    return $this->error($this->speacker("File is not exists."));
                } else {
                    return $return;
                }
            }
        } else {
            return $this->error($this->speacker("File is not exists."));
        }
    }

    /**
     * Method that starts all transformations
     *
     * @param string $filepath pathway to file
     * @return mixed new filename of FALSE in case of error
     */
    private function start($filename, $filepath){
        if (!empty($filepath)) {

            //check folder
            if (!$this->checkDistanation()){
                return $this->error($this->speacker("Destination folder is not exists and cannot be created."));
            }

            //Detect extention
            if (!empty($this->output_type)) {
                $this->output_type = $this->getExtention($filename, $filepath);
            } else {
                return false;
            }

            if (!list($width, $height, $mime, $mix) = getimagesize($filepath)) {
                return $this->error($this->speacker("File is damaged or not uploaded."));
            }

            if (!$size = filesize($filepath) || $size==0) {
                return $this->error($this->speacker("File is damaged or not uploaded."));
            }

            If ($this->max_weight > 0 && $size > $this->max_weight) {
                return $this->error(sprintf($this->speacker("File maximum size should be not more than %s bytes."), $this->max_weight));
            }

            if ($this->min_width > 0 && intval($width) < $this->min_width) {
                return $this->error(sprintf($this->speacker("Minimum image width should be more than %s px."), $this->min_width));
            }

            if ($this->min_height > 0 && intval($height) < $this->min_height) {
                return $this->error(sprintf($this->speacker("Minimum image height should be more than %s px."), $this->min_height));
            }

            $fotoname = $this->render($filepath);
            
            $this->destroyImages();
            
            if ($fotoname===false){
                $this->cleanUp();
            }
            return $fotoname;
        } else {
            $this->error($this->speacker("File is not uploaded."));
            return false;
        }
    }

    /**
     * resize current file
     *
     * @param string $file name on the file uploded and copied into image folder
     * @return boolean true on success
     */
    private function render($file) {
        //detecting region which should be taken
        list($width, $height, $x, $y) = $this->ration($file);
       
        $output = $this->createImage($file, $width, $height, $x, $y, 0);
        
        if ($output) {
            if ($this->watermark && $this->watermark_file != "") {
                    $this->addWatermark($output);
                }
                
            $filename=$this->createNewName();
            $dirname=$this->datadir;
            if ($this->saveImageToFile($output)){
                $thumnails=$this->createThumbnails();
                
                $this->storeImageInfo($filename, $dirname, $width, $height, $thumnails);
                return $filename;
            } else {
                return false;
            }            
        } else {
            return $output;
        }
    }

    /**
     * detect width, height of new image and coordinates for cropping
     * or resizing
     *
     * @param string $file filename
     * @return array(width, height, x, y) positions of left top cornet (if crop)
     */
    private function ration($file) {
        list($width, $height) = getimagesize($file);
        if (!empty($this->coords) && @$this->coords['right']>=0 && $this->coords['top']>=0 && $this->coords['right']-$this->coords['left']<=$width && $this->coords['bottom']-$this->coords['left']<=$height){
            $width = $this->coords['right']-$this->coords['left'];
            $height = $this->coords['bottom']-$this->coords['left'];
            $x=$this->coords['left'];
            $y=$this->coords['top'];
        } else {            
            if ($this->max_width <= 0) {
                $this->max_width = $width;
            }

            if ($this->max_height <= 0) {
                $this->max_height = $height;
            }

            if ($width<=$this->max_width && $height<=$this->max_height){                
                $x=0;
                $y=0;
            } else {
                $ratio_h = $this->max_height / $height;
                $ratio_w = $this->max_width / $width;
                if ($this->crop) {
                    $height = $this->max_height;
                    $width = $re_width;
                    $x = ceil(($width - $re_width / $ratio_h) / 2);
                    $y = ceil(($height - $this->max_height / $ratio_w) / 2);
                } else {
                    if ($ratio_h<$ratio_w){
                        $height = ($this->max_width*$height)/$height;
                        $width = $this->max_width;
                    } else {
                        $height = $this->max_height;
                        $width = ($this->max_height*$width)/$height;
                    }
                    $x = 0;
                    $y = 0;
                }
            }
        }
        return array($width, $height, $x, $y);
    }

    /**
     * generate new image
     *
     * @param int $sm_width distination image width
     * @param int $sm_height distination image height
     * @param int $x destination image top left corner x position
     * @param int $y destination image top left corner y position
     * @param string $file source file
     * @return mixed return source image on success or FALSE
     */
    private function createImage($sm_width, $sm_height, $x, $y, $file=null) {
        list($width, $height) = getimagesize($file);
        
        //create source image
        $function = "ImageCreateFrom".ucfirst($this->$output_type);
        
        if (!isset($this->source)){
            if (!$this->source = @$function($file)){
                return $this->error($this->speacker("During processing the file an error occurred."));
            }
        }
        
        $output = imagecreatetruecolor($sm_width, $sm_height);//create empty canvas
        
        $black = imageColorClosest($output, 0, 0, 0);//defining black color
        
        imagecolorTransparent($output, $black);//setting transparent color

        //copy part of the source image into empty canvas
        if (!imagecopyresampled($output, $this->source, 0, 0, $x, $y, $sm_width, $sm_height, $width, $height)) {
            return $this->error($this->speacker("During processing the file the error occurred."));
        }
        
        return $output;
    }
    
    private function saveImageToFile($source){
        $function="image".$this->output_type;
        if ($this->output_type=="png"){
            $quality=ceil($this->quality/10)-1;
        } else {
            $quality=$this->quality;
        }
            
        if (!$function($source, $this->datadir.$this->filename, $quality)){
            imagedestroy($source);
            return $this->error($this->speacker("Photo wasn't saved. Some error occured."));
        } else {
            imagedestroy($source);
            return true;
        }
    }

    /**
     * return error message. You can rebuild it according to your language class
     *
     * @param string $text error code
     * @return string error message
     */
    private function speacker($text = "") {
        $errors = array(
            "Downloader_dir_is_not_exist" => "Не возможно загрузить файл. Каталог не существует.",
            "Downloader_not_valid" => "Можно загружать файлы формата Jpg/Jpeg, Png, Gif",
            "Downloader_no_size" => "Файл не загружен либо поврежден",
            "Downloader_file_limit_error" => "Максимальный размер файла " . $this->max_weight . " Мб",
            "Downloader_file_width_error" => "Ширина изображения должна быть больше " . $this->min_width . " пикселей",
            "Downloader_file_height_error" => "Высота изображения должна быть больше " . $this->min_height . " пикселей",
            "Downloader_file_server_error" => "Ошибка работы сервера, изображение не загружено",
            "Downloader_file_uploadding_error" => "Загрузите пожалуйста изображение",
            "Downloader_file_processing" => "Файл не загружен, так как содержит вредоносные программы либо поврежден");

        return $errors[$text];
    }

    /**
     * Store date about created file in to 
     *
     * @param string $distination full image path
     * @return boolean
     */
    private function storeImageInfo($filename, $dirname, $width, $height, $thumbnails) {
        $this->image_cart[$filename] = array(
            "folder"    => $dirname,
            "width"     => $width,
            "height"    => $height,
            "thumbnails"=> $thumbnails);
        
        return true;
    }

    /**
     * delete already uploaded pictures from server in case of error
     */
    private function cleanUp() {
        if (isset($this->image_cart) && count($this->image_cart)) {
            foreach ($this->image_cart as $key => $value) {
                @unlink($value['folder'].$key);
                if (count($value['thumbnails'])){
                    foreach($value['thumbnails'] as $file=>$options){
                        @unlink($options['folder'].$file);
                    }
                }
            }
        }
    }

    /**
     * Sets a watermark to the image
     *
     * @param type $dast
     * @param type $sm_width
     * @param type $sm_height
     * @return boolean
     */
    private function addWatermark(&$dast, $sm_width, $sm_height) {
        if (is_file($this->watermark_file)) {
            list($width, $height) = getimagesize($this->watermark_file);
            if ($width > 0 && $height > 0) {
                if ($wm = ImageCreateFromGif($this->watermark_file) || $wm = ImageCreateFromJpeg($this->watermark_file) || $wm = ImageCreateFromPng($this->watermark_file)) {
                    if ($this->mark_x_coord < 0) {
                        $x = $sm_width - $width + $this->mark_x_coord;
                    } else {
                        $x = $this->mark_x_coord;
                    }

                    if ($this->mark_y_coord < 0) {
                        $y = $sm_height - $height + $this->mark_y_coord;
                    } else {
                        $y = $this->mark_y_coord;
                    }
                    if (!imagecopyresampled($dast, $wm, $x, $y, 0, 0, $width, $height, $width, $height)) {
                        @imageDestroy($wm);
                        return $this->error($this->speacker("Watermark is damaged."));
                    } else {
                        @imageDestroy($wm);
                        return true;
                    }
                } else {
                    return $this->error($this->speacker("Watermark is damaged."));
                }
            } else {
                return $this->error($this->speacker("Watermark is damaged."));
            }
        } else {
            return $this->error($this->speacker("Watermark is damaged."));
        }
    }

    /**
     * Gets path to the picture on server
     *
     * @param mixed $file can contain ether string name of uploaded via form
     * file or path to file on server or an array of them.
     * @param mixed $id id of the file in $_FILES array.
     * Can be string or integer.
     * @return mixed depending on what was set can be just path to file,
     * array of them if there was more then one file of FALSE in case of error
     */
    private function getFilePath($file, $id=null){
        if (is_array($file)){
            if ($id !== null) {
                if (isset($_FILES[$file]['tmp_name'][$id])){
                    $file = $_FILES[$file]['tmp_name'][$id];
                } else {
                    return false;
                }
            } else {
                $files=array();
                foreach($file as $key=>$value){
                    $files[]=$this->getFilePath($value);
                }
                if (count($files)){
                    return $files;
                } else {
                    return false;
                }
            }
        } else {
            $file=trim($file);

            if (empty($file)){
                return false;
            }

            if (!strstr($file, DIRECTORY_SEPARATOR)){
                if (isset($_FILES[(string)$file]['tmp_name'])){
                    $file = $_FILES[(string)$file]['tmp_name'];
                } else {
                    return false;
                }
            }
        }

        if (file_exists($file)){
            return $file;
        } else {
            return false;
        }
    }

    /**
     * handle errors
     *
     * @param string $error
     * @return bolean
     */

    private function error($error = "") {
        if (!is_array($this->error)) {
            $this->error = array($error);
        } else {
            $this->error = array_merge(array($error), $this->error);
        }
        return false;
    }
    
    
    private function createNewName(){
        if (!empty($this->filename)){
            if (strstr($this->filename, ".")){
                list($this->filename)=explode(".", $this->filename);
            }
            $sufix="";
            do{
                $name=$this->filename.$sufix;
                $sufix=intval($sufix);
                $sufix++;
            }while(file_exists($this->datadir.$name.".".$this->otput_type));
            
            $this->filename=$name.".".$this->otput_type;
        } else {
            //creating set of chars
            for ($n = 48, $chars = array(); $n <= 122; $n++) {                     
                if (($n >= 48 and $n <= 57) or ($n >= 65 and $n <= 90) or ($n >= 97 and $n <= 122)) {
                    $chars[] = chr($n);
                }
            }
            //generating new random name until it is unique
            do {
                for ($cnt = count($chars) - 1, $rend = 0, $somename = ""; $rend <= 16; $rend++) {
                    $result = mt_rand(0, $cnt);
                    $somename = $somename . $chars[$result];
                }
                $name = $somename . "." . $this->output_type;
            } While (is_file($this->datadir . $name));
            return $name;
        }
    }

    /**
     * Checks whether folder exists or it should be created
     *
     * @return boolean
     */
    private function checkDistanation(){
        $this->datadir = rtrim($this->datadir, "/\\");

        if (!is_dir($this->datadir)) {
            if (!mkdir($this->datadir, 777))
                return false;
            }

        $this->datadir .=DIRECTORY_SEPARATOR;

        return true;
    }

    /**
     * Method allows you to determine file's extension.
     *
     * @param string $name file name
     * @param string $path path to file
     * @return string extention of file or FALSE
     */
    private function getExtention($name, $path){
        if (function_exists("exif_imagetype")) {
            //This function more reliable, but it is in library which is optional
            $mimetype = exif_imagetype($path);
            switch ($mimetype) {
                case IMAGETYPE_GIF:
                    $ext = "gif";
                    break;
                case IMAGETYPE_JPEG:
                    $ext = "jpeg";
                    break;
                case IMAGETYPE_PNG:
                    $ext = "png";
                    break;
                default:
                    return $this->error($this->speacker("Uploaded file has not appropriate extension."));
            }
        } else {
            if (stristr($name, ".")) {
                $ext = htmlspecialchars(end(explode(".", $name)));
                if ($ext == "jpg" || $ext == "jpe") {
                    $ext = "jpeg";
                }

                if (!in_array($ext, $this->valid_extentions)) {
                    return $this->error($this->speacker(""));
                }
            } else {
                return $this->error($this->speacker("Uploaded file has not appropriate extension."));
            }
        }
        return $ext;
    }
    /**
     *
     * @param type $sets
     * @return boolean 
     */
    private function createThumbnails(){
         if(is_array($this->tumbnails) and count($this->tumbnails)) {
            $thumbnails=array();
            foreach ($this->tumbnails as $value) {
                if (count($value)) {
                    $this->initialise($value);
                    list($width, $height, $x, $y) = $this->ration($file);
                    $result = $this->createImage($width, $height, $x, $y);
                    $filename=$this->createNewName();
                    if ($this->saveImageToFile($result)) {
                        $thumbnails[$filename]= array(
                        "folder"    => $this->datadir,
                        "width"     => $width,
                        "height"    => $height);
                    } else {
                        $this->error($this->speacker("Unable to create thumbnail."));
                    }
                }
            }
        }
    
        return null;
    }
    
    private function destroyImages(){
        @imagedestroy($this->source);
      }

}
?>