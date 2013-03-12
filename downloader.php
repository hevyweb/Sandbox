<?
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
    private $filename = ""; //if its necessary you can define output name of the image
    private $tumbnails = array(); //an array of the same properties of the class but for thumbnails
    private $valid_extentions = array("gif", "jpg", "png"); //valid extentions of the input file
    private $coords = array(); //Coord of top-left and right-bottom spots from the original picture to cut into smaller ones.

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

            $foto = $this->datadir . $this->new_name();

            /* if (!copy($tmpfilepath, $foto))
              {return $this->error($this->speacker("Downloader_file_server_error"));} */
            
            // Here comes the hardest thing. Here can be some mistakes.
            $fotoname = $this->resize($filepath);
            @unlink($filepath);
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
    private function resize($file) {
        global $first_image;
        list($width, $height, $x, $y) = $this->ration($file, $this->max_width, $this->max_height, $this->crop);
        //if file name is not set yet or its first iteration.
        if ($this->filename == "") {
            $this->filename = $this->new_name();
        }
        if (!preg_match('/^[A-Za-z0-9]+(.){1}(jpeg|jpe|jpg|gif|png){1}$/', $this->filename)) {
            $this->filename .=$this->output_type;
        }

        $destionation = $this->datadir . $this->filename;
        $result = $this->get_image($file, $destionation, $width, $height, $x, $y, 0);
        if (!$result) {
            $this->clean_up();
            return $result;
        } elseif (!isset($first_image) && is_array($this->tumbnails) and count($this->tumbnails)) {
            $first_image = $this->filename;
            $fileinfo = $this->fileinfo;
            foreach ($this->tumbnails as $key => $value) {
                if (count($value)) {
                    $this->initialise($value);
                    if (!$this->resize($file)) {
                        $this->clean_up();
                        return false;
                    }
                }
            }
            return true;
        } else {
            return true;
        }
    }

    /**
     * detect width, height of new image and coordinates for cropping 
     * or resizing
     * 
     * @param string $file filename
     * @param int $re_width maximal width
     * @param int $re_height maximal height
     * @param boolean $crop crop or resize (default resize)
     * @return array(width, height, x, y) positions of left top cornet (if crop)
     */
    private function ration($file, $re_width = 0, $re_height = 0, $crop = false) {
        list($width, $height) = getimagesize($file);
        if ($re_width <= 0) {
            $re_width = $width;
        }

        if ($re_height <= 0) {
            $re_height = $height;
        }

        $ratio_h = $re_height / $height;
        $ratio_w = $re_width / $width;

        if ($crop) {
            $sm_height = $re_height;
            $sm_width = $re_width;
            $x_pos = ceil(($width - $re_width / $ratio_h) / 2);
            $y_pos = ceil(($height - $re_height / $ratio_w) / 2);
        } else {
            $sm_height = intval($ratio_w * $height);
            $sm_width = intval($ratio_h * $width);
            $x_pos = 0;
            $y_pos = 0;
        }
        return array($sm_width, $sm_height, $x_pos, $y_pos);
    }

    /**
     * generate new image
     * 
     * @param string $file source file
     * @param string $distination distination file
     * @param int $sm_width distination image width
     * @param int $sm_height distination image height
     * @param int $x destination image top left corner x position
     * @param int $y destination image top left corner y position
     * @param boolean $copyright set copyright (not exists yet)
     * @return boolean trun on success
     */
    private function get_image($file, $distination, $sm_width, $sm_height, $x, $y, $copyright = false) {
        list($width, $height) = getimagesize($file);
        //create source image
        switch ($this->output_type) {
            case "gif": if (!$im = ImageCreateFromGif($file))
                    return $this->error($this->speacker("During processing the file an error occurred."));break;
            case "jpg": if (!$im = ImageCreateFromJpeg($file))
                    return $this->error($this->speacker("During processing the file the error occurred."));break;
            case "png": if (!$im = ImageCreateFromPng($file))
                    return $this->error($this->speacker("During processing the file the error occurred."));
        }
        //create destanation canvas
        $dast = imagecreatetruecolor($sm_width, $sm_height);
        $tc = imageColorClosest($dast, 0, 255, 0);
        imagecolorTransparent($dast, $tc);

        //copy part of the source image into destanation canvas
        if (!imagecopyresampled($dast, $im, 0, 0, $x, $y, $sm_width, $sm_height, $width, $height)) {
            return $this->error($this->speacker("During processing the file the error occurred."));
        }

        if ($this->watermark && $this->watermark_file != "") {
            $this->add_watermark($dast, $sm_width, $sm_height);
        }

        //Write destination image into destination file
        switch ($this->output_type) {
            case "gif": if (!imagegif($dast, $distination))
                    return $this->error($this->speacker("Can not create the file."));break;
            case "png": if (!imagepng($dast, $distination))
                    return $this->error($this->speacker("Can not create the file."));break;
            default: if (!imageJpeg($dast, $distination, $this->quality))
                    return $this->error($this->speacker("Can not create the file."));break;
        }
        //Destroy source image and destination image
        imageDestroy($im);
        imageDestroy($dast);
        $this->add_image_to_cart($distination);
        return true;
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
     * add all created images into some temporary privateitable
     * 
     * @param string $distination full image path
     * @return boolean
     */
    private function add_image_to_cart($distination) {
        $this->image_cart[] = $distination;
        return true;
    }

    /**
     * delete already uploaded pictures from server
     */
    private function clean_up() {
        if (isset($this->image_cart) && count($this->image_cart)) {
            foreach ($this->image_cart as $key => $value) {
                @unlink($value);
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
    private function add_watermark(&$dast, $sm_width, $sm_height) {
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
    
    /**
     * generates random filename of the random length
     * 
     * @return string filename
     */
    private function new_name() {
        for ($n = 48, $chars = array(); $n <= 122; $n++) {
            if (($n >= 48 and $n <= 57) or ($n >= 65 and $n <= 90) or ($n >= 97 and $n <= 122)) {
                $chars[] = chr($n);
            }
        }
        do {
            for ($cnt = count($chars) - 1, $rend = 0, $somename = ""; $rend <= 16; $rend++) {
                $result = mt_rand(0, $cnt);
                $somename = $somename . $chars[$result];
            }
            $name = $somename . "." . $this->output_type;
        } While (is_file($this->datadir . $name));
        return $name;
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
                    $ext = "jpg";
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
                if ($ext == "jpeg" || $ext == "jpe") {
                    $ext = "jpg";
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

}
?>