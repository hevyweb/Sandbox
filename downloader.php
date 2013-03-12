<?
/*
	@Class that allow you to upload pictures
	@author Dima Dzyuba
	@authorEmail joomsend@gmail.com
	@created 21.10.2011
	@version 2.0.1
*/
	class Downloader{

	var $maxwidth=0; //Width of the main file will be not more than this size after resize. If 0 photo will not be resized in width.

	var $maxheight=0;//Height of the main file will be not more than this size after resize. If 0 photo will not be resized in height.

	var $min_width=0;//Min width of the picture.

	var $min_height=0;//Min height of picture.

	var $quality=100;//For jpeg

	var $crop=false; // If this parameter is false, image will be resized, other way - cropped to $maxwidth & $maxheight

	var $watermark = false;//Crete watermark

	var $watermark_file="";//Absolute path to watermark

	var $mark_x_coord=-20;//Watermart x coordinate (If > 0 zero is on the left side of the picture, else zero is on the right side)

	var $mark_y_coord=-20;//Watermart y coordinate (If > 0 zero is on the top of the picture, else zero is on the bottom)

	var $datadir="foto";//Destination folder

	var $error=array();//error array

	var $max_weight=0;//Maximal weight in bytes of the uploaded picture

	var $fileinfo="";//contain information about the provided picture

	var $output_type="";//if its necessary you can define output type of the image, (jpeg,jpg, gif, png)

	var $filename="";//if its necessary you can define output name of the image

	var $tumbnails=array();//an array of the same properties of the class but for thumbnails

	var $valid_extentions= array("gif", "jpg", "png");//valid extentions of the input file


	/**
	 * handle errors
	 * @param string $error
	 * @return bolean
	 */
	private function error($error="")
		{if (!is_array($this->error))
			{
			$this->error=array($error);
			}
		else
			{
			$this->error=array_merge(array($error), $this->error);
			}
		return false;
		}
        
        public function __construct($var)
                {$this->initialise($var);            
                }

	/**
	initialize properties of class
	@param array $vars an array of the parameters : name  of property - > value
	*/
	public function initialise($vars=false, $value=false)
		{if (count($vars)>0)
			{foreach($vars as $key=>$value)
				{
				$this->$key=$value;
				}
			}
		else
			{if ($value && $vars)
				{$this->$vars=$value;
				}
			}
		}

	/**
	generate random filename of the random length
	@return string filename
	*/
	public function new_name()
		{for ($n=48,$chars=array();$n<=122;$n++)
			{if (($n>=48 and $n<=57) or ($n>=65 and $n<=90) or ($n>=97 and $n<=122))
				{$chars[]=chr($n);}
			}
		do
			{for ($cnt=count($chars)-1,$rend=0,$somename="";$rend<=16;$rend++)
				{$result=mt_rand(0,$cnt);
				$somename=$somename.$chars[$result];
				}
			$name=$somename.".".$this->output_type;
			}
		While(is_file($this->datadir.$name));
		return $name;
		}

	/**
	* get file and start uploading
	* @param string $file name of the parametr trasferred from form
	* @param mixed $id if first parametr is array, means the key of this array
	* @return boolean true|false
	*/
	public function upload($file, $id=null)
		{if (is_array($file) && $id!==null)
			{$realfilename=$_FILES[$file]['name'][$id];
			$tmpfilepath=$_FILES[$file]['tmp_name'][$id];
			}
		else
			{$realfilename=$_FILES[$file]['name'];
			$tmpfilepath=$_FILES[$ftmpfilepathile]['tmp_name'];
			}

		if (!empty($realfilename) && !empty($tmpfilepath))
			{$this->datadir=rtrim($this->datadir, "/\\");

			//Create directory
			if (!is_dir($this->datadir))
				{if (!mkdir($this->datadir,777))
					return $this->error($this->speacker("Destination folder is not exists and cannot be created."));
				}

			$this->datadir .=DIRECTORY_SEPARATOR;

			//Detect extention
			if (!empty($this->output_type))
				{if (function_exists("exif_imagetype"))
					{$mimetype=exif_imagetype($tmpfilepath);
					switch ($mimetype)
						{case IMAGETYPE_GIF:
							$ext="gif";
							break;
						case IMAGETYPE_JPEG:
							$ext="jpg";
							break;
						case IMAGETYPE_PNG:
							$ext="png";
							break;
						default:
							return $this->error($this->speacker("Uploaded file has not appropriate extension."));
						}
					}
				else
					{if (stristr($up_filename, "."))
						{$ext=htmlspecialchars(end(explode(".", $up_filename)));
						if ($ext=="jpeg" || $ext=="jpe")
							{$ext="jpg";}

						if (!in_array($ext, $this->valid_extentions))
							{return $this->error($this->speacker(""));}
						}
					else
						{return $this->error($this->speacker("Uploaded file has not appropriate extension."));}
					}
				$this->output_type=$ext;
				}

			if (!list($width, $height, $mime, $mix)=getimagesize($tmpfilepath))
				{return $this->error($this->speacker("File is damaged or not uploaded."));}

			if (!$size=filesize($tmpfilepath))
				{return $this->error($this->speacker("File is damaged or not uploaded."));}

			If ($this->max_weight>0 && $size>$this->max_weight)
				{return $this->error(sprintf($this->speacker("File maximum size should be not more than %s bytes."), $this->max_weight));}

			if ($this->min_width>0 && intval($width)<$this->min_width)
				{return $this->error(sprintf($this->speacker("Minimum image width should be more than %s px."), $this->min_width));}

			if ($this->min_height>0 && intval($height)<$this->min_height)
				{return $this->error(sprintf($this->speacker("Minimum image height should be more than %s px."), $this->min_height));}

			$foto=$this->datadir.$this->new_name();

			/*if (!copy($tmpfilepath, $foto))
				{return $this->error($this->speacker("Downloader_file_server_error"));}*/

			$fotoname=$this->resize($tmpfilepath);
			@unlink ($tmpfilepath);
			return $fotoname;
			}
		else
			{$this->error($this->speacker("File is not uploaded."));
			return false;}
		}

	/**
	resize current file
	@param string $file name on the file uploded and copied into image folder
	@return boolean true on success
	*/
	function resize($file)
		{global $first_image;
		list($width, $height, $x, $y) = $this->ration($file, $this->maxwidth, $this->maxheight, $this->crop);
		//if file name is not set yet or its first iteration.
		if ($this->filename=="")
			{
			$this->filename = $this->new_name();
			}
		if (!preg_match('/^[A-Za-z0-9]+(.){1}(jpeg|jpe|jpg|gif|png){1}$/', $this->filename))
						{
			$this->filename .=$this->output_type;
			}

		$destionation=$this->datadir.$this->filename;
		$result=$this->get_image($file, $destionation, $width, $height, $x, $y, 0);
		if (!$result)
			{$this->clean_up();
			return $result;
			}
		elseif(!isset($first_image) && is_array($this->tumbnails) and count($this->tumbnails))
			{$first_image=$this->filename;
			$fileinfo=$this->fileinfo;
			foreach ($this->tumbnails as $key=>$value)
				{if (count($value))
					{$this->initialise($value);
					if (!$this->resize($file))
						{
						$this->clean_up();
						return false;
						}
					}
				}
			return true;
			}
		else
			{return true;}
		}

	/**
	detect width, height of new image and coordinates for cropping or resizing
	@param string $file filename
	@param int $re_width maximal width
	@param int $re_height maximal height
	@param boolean $crop crop or resize (default resize)
	@return array(width, height, x, y) positions of left top cornet (if crop)
	*/
	function ration ($file, $re_width=0, $re_height=0, $crop=false)
		{list($width, $height)=getimagesize($file);
		if ($re_width<=0)
			{$re_width=$width;}

		if ($re_height<=0)
			{$re_height=$height;}

		$ratio_h=$re_height/$height;
		$ratio_w=$re_width/$width;

		if ($crop)
			{$sm_height=$re_height;
			$sm_width=$re_width;
			$x_pos = ceil(($width - $re_width/$ratio_h)/2);
			$y_pos =  ceil(($height - $re_height/$ratio_w)/2);
			}
		else
			{
			$sm_height=intval($ratio_w*$height);
			$sm_width=intval($ratio_h*$width);
			$x_pos = 0;
			$y_pos = 0;
			}
		return array($sm_width, $sm_height, $x_pos, $y_pos);
		}

	/**
	generate new image
	@param string $file source file
	@param string $distination distination file
	@param int $sm_width distination image width
	@param int $sm_height distination image height
	@param int $x destination image top left corner x position
	@param int $y destination image top left corner y position
	@param boolean $copyright set copyright (not exists yet)
	@return boolean trun on success
	*/
	function get_image($file, $distination, $sm_width, $sm_height, $x, $y, $copyright=false)
		{list($width, $height)=getimagesize($file);
		//create source image
		switch ($this->output_type)
			{case "gif": if (!$im=ImageCreateFromGif($file)) return $this->error($this->speacker("During processing the file an error occurred."));break;
			case "jpg": if (!$im=ImageCreateFromJpeg($file)) return $this->error($this->speacker("During processing the file the error occurred."));break;
			case "png": if(!$im=ImageCreateFromPng($file)) return $this->error($this->speacker("During processing the file the error occurred."));
			}
		//create destanation canvas
		$dast	=imagecreatetruecolor($sm_width, $sm_height);
		$tc		=imageColorClosest($dast,0,255,0);
				 imagecolorTransparent($dast, $tc);

		//copy part of the source image into destanation canvas
		if (!imagecopyresampled($dast, $im, 0, 0, $x, $y, $sm_width, $sm_height, $width, $height))
			{
			return $this->error($this->speacker("During processing the file the error occurred."));
			}

		if ($this->watermark && $this->watermark_file!="")
			{$this->add_watermark($dast, $sm_width, $sm_height);}

		//Write destination image into destination file
		switch ($this->output_type)
			{
			case "gif": if (!imagegif ($dast,$distination))return $this->error($this->speacker("Can not create the file."));break;
			case "png": if (!imagepng ($dast,$distination))return $this->error($this->speacker("Can not create the file."));break;
			default: if(!imageJpeg($dast,$distination, $this->quality))return $this->error($this->speacker("Can not create the file."));break;
			}
		//Destroy source image and destination image
		imageDestroy($im);
		imageDestroy($dast);
		$this->add_image_to_cart($distination);
		return true;
		}

		/**
		return error message. You can rebuild it according to your language class
		@param string $text error code
		@return string error message
		*/
		function speacker($text="")
		{$errors=array(
		"Downloader_dir_is_not_exist"=>"Не возможно загрузить файл. Каталог не существует.",
		"Downloader_not_valid"=>"Можно загружать файлы формата Jpg/Jpeg, Png, Gif",
		"Downloader_no_size"=>"Файл не загружен либо поврежден",
		"Downloader_file_limit_error"=>"Максимальный размер файла ".$this->max_weight." Мб",
		"Downloader_file_width_error"=>"Ширина изображения должна быть больше ".$this->min_width." пикселей",
		"Downloader_file_height_error"=>"Высота изображения должна быть больше ".$this->min_height." пикселей",
		"Downloader_file_server_error"=>"Ошибка работы сервера, изображение не загружено",
		"Downloader_file_uploadding_error"=>"Загрузите пожалуйста изображение",
		"Downloader_file_processing"=>"Файл не загружен, так как содержит вредоносные программы либо поврежден");

		return $errors[$text];}

		/**
		 * add all created images into some temporary varitable
		 * @param string $distination full image path
		 * @return boolean
		 */
		function add_image_to_cart($distination)
		{$this->image_cart[]=$distination;
		return true;
		}

		/**
		 * delete already uploaded pictures from server
		 */
		function clean_up()
		{if (isset($this->image_cart) && count($this->image_cart))
			{foreach ($this->image_cart as $key=>$value)
				{@unlink($value);}
			}
		}

		function add_watermark(&$dast, $sm_width, $sm_height)
		{if (is_file($this->watermark_file))
			{list($width, $height)=getimagesize($this->watermark_file);
			if ($width>0 && $height>0)
				{if ($wm=ImageCreateFromGif($this->watermark_file) || $wm=ImageCreateFromJpeg($this->watermark_file) || $wm=ImageCreateFromPng($this->watermark_file))
					{if ($this->mark_x_coord<0)
						{$x=$sm_width - $width + $this->mark_x_coord;
						}
					else
						{$x=$this->mark_x_coord;
						}

					if ($this->mark_y_coord<0)
						{$y=$sm_height - $height + $this->mark_y_coord;
						}
					else
						{$y=$this->mark_y_coord;
						}
					if (!imagecopyresampled($dast, $wm, $x, $y, 0, 0, $width, $height, $width, $height))
						{
						@imageDestroy($wm);
						return $this->error($this->speacker("Watermark is damaged."));
						}
					else
						{@imageDestroy($wm);
						return true;
						}
						}
				else
					{return $this->error($this->speacker("Watermark is damaged."));}
				}
			else
				{return $this->error($this->speacker("Watermark is damaged."));}
			}
		else
			{return $this->error($this->speacker("Watermark is damaged."));}
		}
	}
?>