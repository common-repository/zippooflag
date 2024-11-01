<?php	
	/*
Plugin Name: ZippooFlag 
Plugin URI: http://www.zippoo.com/
Description: A comment country&browser&OS flag plugin for Wordpress,显示留言者国旗以及浏览器操作系统标志
Version: 1.1
Author: Neo
Author URI: http://blog.zippoo.com/
*/
define('flagzippoo_ABS_PATH',dirname(dirname(__FILE__)));
add_filter('get_comment_author_link', 'fs_add_comment_flag', 100);
add_filter('get_comment_author_link', 'fs_add_comment_browser_os', 100);
function fs_add_comment_browser_os($link)
{
	global $comment;
	$ua = $comment->comment_agent;
	if (!$ua) return $link;
		require_once(flagzippoo_ABS_PATH.'/zippooflag/browsniff.php');
	return $link . ' '.fs_pri_browser_images($ua);
}
	
	function fs_add_comment_flag($link)
{
	$ip = get_comment_author_IP();
	$code = fs_ip2c($ip);
	if (!$code) return $link;
	return $link .' '. fs_get_country_flag_url($code);
}
	
		$__fs_ip2c_file = flagzippoo_ABS_PATH.'/zippooflag/ip-to-country.bin';
	if (file_exists($__fs_ip2c_file)) 
	{
	$GLOBALS['fs_ip2c'] = new fs_ip2country($__fs_ip2c_file);
		$GLOBALS['fs_ip2c_country_cache'] = array();
					}
		
	function fs_get_country_flag_url($country_code, $is_int = false)
{
	if ($is_int && $country_code != NULL)
	{
		$c = chr(($country_code >> 8) & 0xFF).chr(($country_code) & 0xFF);
		$country_code = $c;
	}
    if (!$country_code) return "";
    $code = strtolower($country_code);
   $flag_url = "../wp-content/plugins/zippooflag/img/flags/$code.png";
    $name = fs_get_country_name($code);
    
	return fs_get_flag_img_tag($name, $flag_url);
}
function fs_get_country_name($country_code, $is_int = false)
{
	if (isset($GLOBALS['fs_ip2c_country_cache']))
	{
		$cache = $GLOBALS['fs_ip2c_country_cache'];
		if ($is_int)
		{
			$c = chr(($country_code >> 8) & 0xFF).chr(($country_code) & 0xFF);
			$country_code = $c;
		}
		
		$res = isset($cache[$country_code]) ? $cache[$country_code] : false;
		if (!$res)
		{
			$ip2c = $GLOBALS['fs_ip2c'];
			$res = $ip2c->find_country($country_code);
			$cache[$country_code] = $res;
		}
		return $res['name'];
	}
	else
	{
		return '';
	}
}
function fs_get_flag_img_tag($name, $img_url)
{
    return "<img src='$img_url' alt='$name' title ='$name' width='16' height='11' class='fs_flagicon'/>";
}

function fs_echo_country_flag_url($country_code)
{
    echo fs_get_country_flag_url($country_code);
}
	function fs_ip2c($ip, $as_int = false)
{
	if (isset($GLOBALS['fs_ip2c']))
	{
				$ip2c = $GLOBALS['fs_ip2c'];
		$ip2c_res = $ip2c->get_country($ip);
		//echo $ip2c_res;
		if ($ip2c_res != false)
		{
						$ccode = $ip2c_res['id2'];
			if ($ccode == null) return null;
			if ($as_int)
			{
				$c1 = ord($ccode[0]);
				$c2 = ord($ccode[1]);
				$intcode = ($c1 << 8) | $c2;
				return $intcode;
			}
			else
			{
				
				return $ccode;
			}
		}
	
	
	}

	return false;
}
define('IP2C_MAX_INT',0x7fffffff);
class fs_ip2country
{
	var $m_active = false;
	var $m_file;
	var $m_firstTableOffset;
	var $m_numRangesFirstTable;
	var $m_secondTableOffset;
	var $m_numRangesSecondTable;
	var $m_countriesOffset;
	var $m_numCountries;
	var $bin_file = './ip-to-country.bin';		

	/**
	 * ip2country(String $file) will NOT USE caching and specifies the country database $file
	 * ip2country(String $file, true) will USE caching with default file location
	 * ip2country(Boolean true) will USE caching with default file location
	 */
	function fs_ip2country($bin_file = './ip-to-country.bin', $caching = false) 
	{
		if (is_bool($bin_file)) {
			// use $bin_file as caching indicator
			$caching = $bin_file;
			// default the bin file to the class variable
			$bin_file = $this->bin_file;
		}
		
		$this->caching = $caching;
		
		$this->m_file = fopen($bin_file, "rb");
		if (!$this->m_file) 
		{
			trigger_error('Error loading '.$bin_file);
			if (defined('UNIT_TEST')) exit(1);
			return;
		}
		
		if ($this->caching) {
			$this->initCache($bin_file);
		}

		$f = $this->m_file;
		if ($this->caching) {
			$sig = $this->mem[$this->offset++]
					.$this->mem[$this->offset++]
					.$this->mem[$this->offset++]
					.$this->mem[$this->offset++];
		}
		else {
			$sig = fread($f, 4);
		}
		
		if ($sig != 'ip2c')
		{
			trigger_error("file $bin_file has incorrect signature");
			if (defined('UNIT_TEST')) exit(1);
			return;
		}
		$v = $this->readInt();
		if ($v != 2)
		{
			trigger_error("file $bin_file has incorrect format version ($v)");
			if (defined('UNIT_TEST')) exit(1);
			return;
		}

		$this->m_firstTableOffset = $this->readInt();
		$this->m_numRangesFirstTable = $this->readInt();
		$this->m_secondTableOffset = $this->readInt();
		$this->m_numRangesSecondTable = $this->readInt();
		$this->m_countriesOffset = $this->readInt();
		$this->m_numCountries = $this->readInt();
		$this->m_active = true;
	}

	function initCache($fileName) {
		$this->offset = 0;
		$fp = fopen($fileName, "rb");
		$this->mem = fread($fp, filesize($fileName));
		if ($this->mem === FALSE)
			$this->caching = FALSE;
		fclose($fp);
	}

	function get_country($ip)
	{
		if (!$this->m_active) return false;

		$int_ip =  ip2long($ip);

		// happens on 64bit systems	
		if ($int_ip > IP2C_MAX_INT)
		{
			// shift to signed int32 value
			$int_ip -= IP2C_MAX_INT;
			$int_ip -= IP2C_MAX_INT;
			$int_ip -= 2;
		}

		if ($int_ip >= 0)
		{
			$key = $this->find_country_code($int_ip, 0, $this->m_numRangesFirstTable, true);
		}
		else
		{
			$nip = (int)($int_ip + IP2C_MAX_INT + 2); // the + 2 is a bit wierd, but required.
			$key = $this->find_country_code($nip, 0, $this->m_numRangesSecondTable, false);
		}
		if ($key == false || $key == 0)
		{
			return false;
		}
		else
		{
			return $this->find_country_key($key,0, $this->m_numCountries);
		}
	}

	function find_country_code($ip, $startIndex, $endIndex, $firstTable, $d = 0) 
	{
		while(1) {
			$middle = (int)(($startIndex + $endIndex) / 2);
			$mp = $this->getPair($middle, $firstTable);
			$mip = $mp['ip'];
			//echo "#$d find_country_code : [code=$ip, start=$startIndex, middle=$middle, end=$endIndex, mip=$mip]<br/>";
	
			if ($ip < $mip)
			{
				if ($startIndex + 1 == $endIndex) return false; // not found
				$endIndex = $middle;
				continue;
				//return $this->find_country_code($ip, $startIndex, $middle, $firstTable, ++$d);
			}
			else 
				if ($ip > $mip)
				{
					$np = $this->getPair($middle+1, $firstTable);
					if ($ip < $np['ip'])
					{
						return $mp['key'];
					}
					else
					{
						if ($startIndex + 1 == $endIndex) return false; // not found
						$startIndex = $middle;
						continue;
						//return $this->find_country_code($ip, $middle, $endIndex, $firstTable, ++$d);
					}
				}
				else // ip == mip
				{
					return $mp['key'];
				}
		}
	}

	function find_country($code)
	{
		if (!$this->m_active) return false;
		$c = strtoupper($code);
		$c1 = $c[0];
		$c2 = $c[1];
		$key = ord($c1) * 256 + ord($c2);
		return $this->find_country_key($key, 0, $this->m_numCountries);	
	}


	function find_country_key($code, $startIndex, $endIndex) 
	{	
		$d = 0;
		while(1) {
			if ($d > 20)
			{
				trigger_error("IP2Country : Internal error - endless loop detected, code = $code");
				return false;
			}
			
			$d++;
			$middle = (int)(($startIndex + $endIndex) / 2);
			$mc = $this->get_country_code($middle);
			//echo "#$d find_country : [$startIndex, $endIndex, mc=$mc, code=$code]<br/>";
	
			if ($mc == $code)
			{
				// found.
				return $this->load_country($middle);
			}
			else
				if ($code > $mc)
				{
					if ($middle + 1 == $endIndex)
					{
						$nc = $this->get_country_code($middle);
						if ($nc == $code) return $this->load_country($middle);
						else return false;
					}
					$startIndex = $middle;
					continue;
					//return $this->find_country_key($code, $middle, $endIndex, ++$d);
				}
				else // $code < $mc
				{
					if ($startIndex + 1 == $middle)
					{
						$nc = $this->get_country_code($startIndex);
						if ($nc == $code) return $this->load_country($startIndex);
						else return false;
					}
					$endIndex = $middle;
					continue;
					//return $this->find_country_key($code, $startIndex, $middle, ++$d);
				}
		}
	}


	function load_country($index)
	{
		$offset = $this->m_countriesOffset + $index * 10;
		
		if ($this->caching)
		{
			$this->offset = $offset;
		}
		else 
			fseek($this->m_file, $offset);
		
		$id2c = $this->readCountryKey();
		$id3c = $this->read3cCode();
		$nameOffset = $this->readInt();
		
		if ($this->caching)
		{
			$this->offset = $nameOffset;
		}
		else 
			fseek($this->m_file, $nameOffset);
		
		$len = $this->readShort();
		$name = '';
		if ($len != 0)
		{ 
			if ($this->caching) 
			{
				for($i = 0;$i<$len;$i++)
				{
					$name.=$this->mem[$this->offset++];
				}
			}
			else 
				$name = fread($this->m_file, $len);
		}
		return array("id2"=>$id2c,"id3"=>$id3c,"name"=>$name);
	}

	function get_country_code($index)
	{
		$offset = $this->m_countriesOffset + $index * 10;
		
		if ($this->caching)
		{
			$this->offset = $offset;
			$a = unpack('n', $this->mem[$this->offset++]
						.$this->mem[$this->offset++]);
		}
		else {
			fseek($this->m_file, $offset);
			$a = unpack('n', fread($this->m_file, 2));
		}

		return $a[1];
	}



	function getPair($index, $firstTable) 
	{
		$offset = 0;
		if ($firstTable)
		{
			if ($index > $this->m_numRangesFirstTable) 
			{
				return array('key'=>false,'ip'=>0);
			}
			$offset = $this->m_firstTableOffset + $index * 6;
		}
		else
		{
			if ($index > $this->m_numRangesSecondTable) 
			{
				return array('key'=>false,'ip'=>0);
			}
			$offset = $this->m_secondTableOffset + $index * 6;

		}
		
		if ($this->caching)
		{
			$this->offset = $offset;
			$p = unpack('Nip/nkey', $this->mem[$this->offset++]
									.$this->mem[$this->offset++]
									.$this->mem[$this->offset++]
									.$this->mem[$this->offset++]
									.$this->mem[$this->offset++]
									.$this->mem[$this->offset++]);
		}
		else 
		{
			fseek($this->m_file, $offset);
			$p =unpack('Nip/nkey', fread($this->m_file, 6));
		}

		return $p;

	}

	function readShort() 
	{
		if ($this->caching)
		{
			$a = unpack('n', $this->mem[$this->offset++]
						.$this->mem[$this->offset++]);
		}
		else
			$a = unpack('n', fread($this->m_file, 2));

		return $a[1];
	}

	function read3cCode()
	{
		if ($this->caching)
		{
			$this->offset++;
			$d = $this->mem[$this->offset++]
						.$this->mem[$this->offset++]
						.$this->mem[$this->offset++];
		}
		else
		{
			fread($this->m_file, 1);
			$d = fread($this->m_file, 3);
		}

		return $d != '   ' ? $d : '';
	}

	function readCountryKey() 
	{
		if ($this->caching)
		{
			return $this->mem[$this->offset++].$this->mem[$this->offset++];
		}
		else
		{
			return fread($this->m_file, 2);
		}		
	}

	function readInt() 
	{
		if ($this->caching)
		{
			$a = unpack('N', $this->mem[$this->offset++]
						.$this->mem[$this->offset++]
						.$this->mem[$this->offset++]
						.$this->mem[$this->offset++]);
		}
		else
			$a =unpack('N', fread($this->m_file, 4));

		return $a[1];
	}
}

?>