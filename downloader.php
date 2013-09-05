<?php

/*
  @Class that allow you to upload pictures, make thumbnails and add watermarks on them
  @author Dima Dzyuba
  @authorEmail joomsend@gmail.com
  @created 21.10.2011
  @version 2.0.1
 */

class Downloader {

    //Width of the main file will be not more than this size after resize. 
    //If 0 photo will not be resized in width.
    private $max_width = 0;
    //Height of the main file will be not more than this size after resize. 
    //If 0 photo will not be resized in height.
    private $max_height = 0;
    //Min width of the picture.
    private $min_width = 0;
    //Min height of picture.
    private $min_height = 0; 
    //For jpeg or png
    private $quality = 100; 
    // FALSE - image will be resized, TRUE - cropped
    private $crop = false; 
    //Crete watermark
    private $watermark = false; 
    //Absolute path to watermark
    private $watermark_file = "";
    //Coord of top-left and right-bottom spots from the original picture 
    private $coords = array(-20, -20); 
    //Destination folder
    private $datadir = "foto"; 
    //error array
    private $error = array(); 
    //Maximal weight in bytes of the uploaded picture
    private $max_weight = 0; 
    //contain information about the provided picture
    private $fileinfo = ""; 
    //if its necessary you can define output type of the image, 
    //(jpeg,jpg, gif, png)
    private $output_type = ""; 
    //if its necessary you can define output name of the image. 
    //No Extention allowed
    private $filename = "";
    //valid extentions of the input file
    private $valid_extentions = array("gif", "jpeg", "png"); 
    //to cut into smaller ones.
    private $image_cart = array(); 

    public function __construct($private) {
        $this->initialise($private);
    }

    /**
     * Initialize properties of class
     *
     * @param array $privates an array of the parameters : name of property - > value
     */
    public function initialise($privates = array()) {
        if (is_array($privates) && count($privates) > 0) {
            if (is_array($privates[0])){
                $config=array_shift($privates);
                $this->initialise($config);
                $this->config=$privates;
            } else {
                foreach ($privates as $key => $value) {
                    $this->$key = $value;
                }
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
    public function getParam($param = null) {
        if (!empty($param)) {
            if (isset($this->$param))
                return $this->$param;
            else
                return null;
        } else {
            return (array) $this;
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
        $files = $this->getFilePath($file, $id);
        if ($files!=false) {
            if ($this->checkSource($files['filename'], $value['filepath'])){
                $fotoname = $this->render($value['filepath']);
                //$this->cleanUp();

                return $fotoname;
            } else {
                return false;
            }
        } else {
            return $this->error("File is not exists.");
        }
    }
    
    
    /**
     * Method that allows to fetch already existed file 
     * 
     * 
     */
    
    public function fetch($filepath){
        if (is_file($filepath)){
            $filepath=str_replace("/", "\\",$filepath);
            $filename=array_pop(explode("\\", $filepath));
            if ($this->checkSource($filename, $filepath)){
                $fotoname = $this->render($filepath);
                //$this->cleanUp();

                return $fotoname;
            } else {
                return false;
            }
        } else {
            return $this->error("file not exists");
        }
    }

    /**
     * Method that starts all transformations
     *
     * @param string $filepath pathway to file
     * @return mixed new filename of FALSE in case of error
     */
    private function checkSource($filename, $filepath) {
        //check folder
        if (!$this->checkDistanation()) {
            return $this->error("Destination folder is not exists and cannot be created.");
        }

        //Detect extention
        if (!$this->output_type = $this->getExtention($filename, $filepath)){
            return false;
        }

        if (!$this->fileinfo = getimagesize($filepath)) {
            return $this->error("File is damaged or not uploaded.");
        }
        list($width, $height)=$this->fileinfo;

        if (!$size = filesize($filepath)) {
            return $this->error("File is damaged or not uploaded.");
        }

        If ($this->max_weight > 0 && $size > $this->max_weight) {
            return $this->error("File is too big");
        }

        if ($this->min_width > 0 && intval($width) < $this->min_width) {
            return $this->error("Image width is too small");
        }

        if ($this->min_height > 0 && intval($height) < $this->min_height) {
            return $this->error("Image height is too small");
        }

        return true;
    }

    /**
     * resize current file
     *
     * @param string $file name on the file uploded and copied into image folder
     * @return boolean true on success
     */
    private function render($file) {
        //detecting region which should be taken
        $ratio = $this->getRatio();
        
        if (!$this->createImage($file, $ratio)){
            return false;
        }
        
        if ($this->watermark && is_file($this->watermark_file)){
            if (!$this->addWatermark($ratio[0],$ratio[1])){
                return false;
            }
        }
        
        $this->createNewName();
        
        if (!$this->saveImageToFile()) {
            return false;
        }
        
        $this->storeImageInfo();

        $this->destroyImages();
        
        if (isset($this->config) && is_array($this->config) && count($this->config)>0){
            $this->initialise($this->config);
            $this->render($file);
        }
        
        return true;
    }

    /**
     * detect width, height of new image and coordinates for cropping
     * or resizing
     *
     * @param string $file filename
     * @return array(width, height, x, y) positions of left top cornet (if crop)
     */
    private function getRatio() {
        list($width, $height) = $this->fileinfo;
        if ($this->max_width <= 0) {
            $this->max_width = $width;
        }

        if ($this->max_height <= 0) {
            $this->max_height = $height;
        }
        
        $x = 0;
        $y = 0;
        $dst_height = $this->max_height;
        $dst_width = $this->max_width;
        $src_height=$height;
        $src_width=$width;

        if ($width > $this->max_width || $height > $this->max_height) {
            $ratio_h = $this->max_height / $height;
            $ratio_w = $this->max_width / $width;
            if ($this->crop) {
                if ($ratio_h >= $ratio_w) {
                    $src_width=$this->max_width*$height/$this->max_height;
                    $x = ceil(($width - $src_width) / 2);
                } else {
                    $src_height=$this->max_height*$width/$this->max_width;
                    $y = ceil(($height - $src_height) / 2);
                }                
            } else {
                if ($ratio_h >= $ratio_w) {
                    $dst_height = intval($ratio_w*$height);
                    $dst_width = $this->max_width;                    
                } else {
                    $dst_width = intval($ratio_h*$width);
                    $dst_height = $this->max_height;                    
                }
            }
        } else {
            $dst_height = $height;
            $dst_width = $width;
        }
        
        return array($dst_width, $dst_height, $src_width, $src_height, $x, $y);
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
    private function createImage($file, $ratio) {
        list($dst_width, $dst_height, $src_width, $src_height, $x, $y)=$ratio;
        //create source image
        $function = "ImageCreateFrom" . ucfirst($this->output_type);

        if (!isset($this->source)){
            if (!function_exists($function) || !$this->source = $function($file)) {
                return $this->error("file processing error");
            }
        }

        $output = imagecreatetruecolor($dst_width, $dst_height); //create empty canvas

        $black = imageColorClosest($output, 0, 0, 0); //defining black color

        imagecolorTransparent($output, $black); //setting transparent color
        //copy part of the source image into empty canvas
        if (!imagecopyresampled($output, $this->source, 0, 0, $x, $y, $dst_width, $dst_height, $src_width, $src_height)) {
            return $this->error("file processing error");
        }
        
        $this->output=$output;
        return true;
    }

    private function saveImageToFile() {
        $function = "image" . $this->output_type;
        if ($this->output_type == "png") {
            $quality = ceil($this->quality / 10) - 1;
        } else {
            $quality = $this->quality;
        }

        if (!$function($this->output, $this->datadir . $this->filename, $quality)) {
            return $this->error("file saving error");
        } else {
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
        return $text;
    }

    /**
     * Store date about created file in to
     *
     * @param string $distination full image path
     * @return boolean
     */
    private function storeImageInfo() {
        list($width, $height)=  getimagesize($this->datadir . $this->filename);
        $this->image_cart[$this->filename] = array(
            "folder" => $this->datadir,
            "width" => $width,
            "height" => $height);

        return true;
    }

    /**
     * delete already uploaded pictures from server in case of error
     */
    private function cleanUp() {
        if (isset($this->image_cart) && count($this->image_cart)) {
            foreach ($this->image_cart as $key => $value) {
                @unlink($value['folder'] . $key);
                if (count($value['thumbnails'])) {
                    foreach ($value['thumbnails'] as $file => $options) {
                        @unlink($options['folder'] . $file);
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
    private function addWatermark($sm_width, $sm_height) {
        list($width, $height, $type) = getimagesize($this->watermark_file);
        list($x, $y)=$this->coords;
        if ($width > 0 && $height > 0) {
            $types=array(1=>"gif", 2=>"jpeg", 3=>"png");
            $fn="ImageCreateFrom".$types[$type];
            if (!function_exists($fn)){
                return $this->error('Watermark file should be jpg, gif or png');
            }
            $wm = call_user_func($fn, $this->watermark_file);
            if ($wn!==false) {
                if ($x < 0) {
                    $x = $sm_width - $width + $x;
                }

                if ($y < 0) {
                    $y = $sm_height - $height + $y;
                }

                if (!imagecopyresampled($this->output, $wm, $x, $y, 0, 0, $width, $height, $width, $height)) {
                    @imageDestroy($wm);
                    return $this->error("Watermark is damaged.");
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
    }

    /**
     * Gets path to the picture on server and it's original name
     *
     * @param mixed $file can contain ether string name of uploaded via form
     * file or path to file on server or an array of them.
     * @param mixed $id id of the file in $_FILES array.
     * Can be string or integer.
     * @return mixed depending on what was set can be just path to file,
     * array of them if there was more then one file of FALSE in case of error
     */
    private function getFilePath($file, $id = null) {
        if (isset($_FILES[$file]['tmp_name'])) {
            $filename = $_FILES[$file]['name'];
            $filepath = $_FILES[$file]['tmp_name'];
        } else {
            return false;
        }

        if ($id!=null) {
            if (isset($filename[$id])){
                $filename =$filename[$id];
                $filepath =$filepath[$id];
            } else {
                return false;
            }
        }

        return array("filename"=>$filename, "filepath"=>$filepath);
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

    /**
     * Generates random unique filename
     * 
     * @return string
     */
    private function createNewName() {
        do {            
            $name = substr(sha1(mt_rand(1, time())), 0, 16) . "." . $this->output_type;
        } While (is_file($this->datadir . $name));
        $this->filename=$name;
        return $name;
    }

    /**
     * Checks whether folder exists or it should be created
     *
     * @return boolean
     */
    private function checkDistanation() {
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
    private function getExtention($name, $path) {
        if (function_exists("exif_imagetype")) {
            //This function more reliable, but it is in library which is optional
            $mimetype = exif_imagetype($path);
            if ($mimetype!=false){
                $mimetype--;
                if (isset($this->valid_extentions[$mimetype])){
                    $ext=$this->valid_extentions[$mimetype];
                }
            } else {
                return $this->error("not appropriate extension");
            }
        } else {
            if (stristr($name, ".")) {
                $ext = htmlspecialchars(end(explode(".", $name)));
                if ($ext == "jpg" || $ext == "jpe") {
                    $ext = "jpeg";
                }

                if (!in_array($ext, $this->valid_extentions)) {
                    return $this->error("not appropriate extension");
                }
            } else {
                return $this->error("not appropriate extension");
            }
        }
        return $ext;
    }

    private function destroyImages() {
        imagedestroy($this->output);
    }

}
