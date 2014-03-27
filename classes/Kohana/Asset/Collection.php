<?php defined('SYSPATH') OR die('No direct script access.');
/**
* Collection of assets
*
* @package    Despark/asset-merger
* @author     Ivan Kerin
* @copyright  (c) 2011-2012 Despark Ltd.
* @license    http://creativecommons.org/licenses/by-sa/3.0/legalcode
*/
abstract class Kohana_Asset_Collection implements Iterator, Countable, ArrayAccess {

	/**
	 * @var  array  assets
	 */
	protected $_assets = array();

	/**
	 * @var  string  name
	 */
	protected $_name;

	/**
	 * @var  string  type
	 */
	protected $_type;

	/**
	 * @var  string  type
	 */
	protected $_copy = TRUE;

	/**
	 * @var  string  asset file
	 */
	protected $_destination_file;

	/**
	 * @var   string  web file
	 */
	protected $_destination_web;

	/**
	 * @var  string destination path of the merged asset file
	 */
	protected $_destination_path = NULL;

	/**
	 * @var  int  last modified time
	 */
	protected $_last_modified = NULL;

	public function destination_file()
	{
		return $this->_destination_file;
	}

	public function destination_web()
	{
		return $this->_destination_web;
	}

	public function destination_path()
	{
		return $this->_destination_path;
	}

	public function type()
	{
		return $this->_type;
	}

	public function name()
	{
		return $this->_name;
	}

	public function copy()
	{
		return $this->_copy;
	}
	public function folder()
	{
		return $this->_folder;
	}

	public function assets()
	{
		return $this->_assets;
	}

	/**
	 * Set up environment
	 *
	 * @param  string  $type
	 * @param  string  $name
	 */
	public function __construct($type, $name = 'all', $destination_path = NULL,$copy = TRUE, $folder)
	{
		// Check type
		Assets::require_valid_type($type);

		// Set type and name
		$this->_type = $type;
		$this->_name = $name;
		$this->_copy = $copy;
		$this->_folder = $folder;
		$this->_destination_path = $destination_path;
	}

	/**
	 * Compile asset content
	 *
	 * @param   bool  $process
	 * @return  string
	 */
	public function compile($process = FALSE)
	{
		// Set content
		$content = '';

		foreach ($this->assets() as $asset)
		{
			// Compile content
			$content .= $asset->compile($process)."\n\n";
		}

		return $content;
	}

    protected function hash_content()
    {
        $haystack = '';

        foreach ($this->assets() as $asset)
        {
            /* @var $asset Asset */
            // If is Asset_Block
            if ( ! $asset->source_file())
            {
                $haystack .= $asset->content();
            }
            else
            {
                $haystack .= $asset->source_file();
            }

            $haystack .= $asset->weight();
        }

        return hash('crc32b', $haystack);
    }

    protected function set_destinations()
    {
        $hash = $this->hash_content();
        $this->_destination_file    = Assets::file_path($this->type(), $this->name().'-'.$hash.'.'.$this->type(), $this->destination_path(),$this->folder());
        $this->_destination_web     = Assets::web_path($this->type(), $this->name().'-'.$hash.'.'.$this->type(), $this->destination_path(),$this->folder());
    }

	/**
	 * Render HTML
	 *
	 * @param   bool  $process
	 * @return  string
	 */
	public function render($process = FALSE)
	{
        $this->set_destinations();

		if ($this->needs_recompile() AND $this->copy())
		{
			// Recompile file
			file_put_contents($this->destination_file(), $this->compile($process));
		}

		return Asset::html($this->type(), $this->destination_web());
	}

	/**
	 * Process and return the webpath 
	 *
	 * @param   bool  $process
	 * @return  string
	 */
	public function get_webpath($process = FALSE)
	{
        $this->set_destinations();

		if ($this->needs_recompile() AND $this->copy())
		{
			// Recompile file
			file_put_contents($this->destination_file(), $this->compile($process));
		}

		return $this->destination_web();
	}

	/**
	 * Render inline HTML
	 *
	 * @param   bool  $process
	 * @return  string
	 */
	public function inline($process = FALSE)
	{
		return Asset::html_inline($this->type(), $this->compile($process));
	}

	/**
	 * Determine if recompilation is needed
	 *
	 * @return bool
	 */
	public function needs_recompile()
	{
		return Assets::is_modified_later($this->destination_file(), $this->last_modified());
	}

	/**
	 * Get and set the last modified time
	 *
	 * @return integer
	 */
	public function last_modified()
	{
		if ($this->_last_modified === NULL)
		{
			// Get last modified times
			$last_modified_times = array_filter(self::_invoke($this->assets(), 'last_modified'));

			if ( ! empty($last_modified_times))
			{
				// Set the last modified time
				$this->_last_modified = max($last_modified_times);
			}
		}

		return $this->_last_modified;
	}

	static public function _invoke($arr, $method)
	{
		$new_arr = array();

		foreach ($arr as $id => $item)
		{
			$new_arr[$id] = $item->$method();
		}

		return $new_arr;
	}

	public function offsetSet($offset, $value)
	{
		if (is_null($offset))
		{
			$this->_assets[] = $value;
		}
		else
		{
			$this->_assets[$offset] = $value;
		}
	}

	public function offsetExists($offset)
	{
		return isset($this->_assets[$offset]);
	}

	public function offsetUnset($offset)
	{
		unset($this->_assets[$offset]);
	}

	public function offsetGet($offset)
	{
		return isset($this->_assets[$offset]) ? $this->_assets[$offset] : NULL;
	}

	public function rewind()
	{
		reset($this->_assets);
	}

	public function current()
	{
		return current($this->_assets);
	}

	public function key()
	{
		return key($this->_assets);
	}

	public function next()
	{
		return next($this->_assets);
	}

	public function valid()
	{
		return $this->current() !== FALSE;
	}

	public function count()
	{
		return count($this->_assets);
	}

    public function sort()
    {
        return usort($this->_assets, function(Asset $a, Asset $b) {
            if ($a->weight() === $b->weight())
            {
                return 0;
            }

            return $a->weight() - $b->weight();
        });
    }

} // End Asset_Collection