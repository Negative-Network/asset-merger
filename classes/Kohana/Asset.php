<?php defined('SYSPATH') OR die('No direct script access.');
/**
* Combines assets and merges them to single files in production
*
* @package    Despark/asset-merger
* @author     Ivan Kerin
* @copyright  (c) 2011-2012 Despark Ltd.
* @license    http://creativecommons.org/licenses/by-sa/3.0/legalcode
*/
abstract class Kohana_Asset {

	/**
	 * Add conditions to asset
	 *
	 * @param   string  $content
	 * @param   string  $condition
	 * @return  string
	 */
	public static function conditional($content, $condition)
	{
		return "<!--[if ".$condition."]>\n". $content."\n<![endif]-->";
	}

	/**
	 * Add a local fallback
	 *
	 * @param   string  $content
	 * @param   string  $condition
	 * @return  string
	 */
	public static function fallback($content, $check, $fallback)
	{
		return $content."\n".Asset::html_inline(Assets::JAVASCRIPT, "({$check}) || document.write('<script type=\"text/javascript\" src=\"{$fallback}\"><\/script>')");
	}

	/**
	 * Create HTML
	 *
	 * @param   string   $type
	 * @param   string   $file
	 * @param   integer  $cache_buster
	 * @return  string
	 */
	public static function html($type, $file)
	{
		// Set type for the proper HTML
		switch ($type)
		{
			case Assets::JAVASCRIPT:
				$type = 'script';
			break;
			case Assets::STYLESHEET:
				$type = 'style';
			break;
		}

		return Asset::$type($file);
	}

    public static function valid_url($file)
    {
        return (strpos($file, '://') !== FALSE OR substr($file, 0, 2) === '//');
    }

    protected static function script($file)
    {
        if (!Asset::valid_url($file))
        {
            // Add base url
            $file = URL::site($file);
        }

        return '<script'.HTML::attributes(array(
            'src'   => $file,
            'type'  => 'text/javascript'
        )).'></script>';
    }

    protected static function style($file)
    {
        if (!Asset::valid_url($file))
        {
            // Add base url
            $file = URL::site($file);
        }

        return '<link'.HTML::attributes(array(
            'rel'   => 'stylesheet',
            'href'  => $file
        )).'>';
    }

	/**
	 * Create inline HTML
	 *
	 * @param   string   $type
	 * @param   string   $content
	 * @return  string
	 */
	public static function html_inline($type, $content)
	{
		// Set the proper inline HTML
		switch ($type)
		{
			case Assets::JAVASCRIPT:
				$html = "<script type=\"text/javascript\">\n".$content."\n</script>";
			break;
			case Assets::STYLESHEET:
				$html = "<style>\n".$content."\n</style>";
			break;
		}

		return $html;
	}

	/**
	 * @var  string  type
	 */
	protected $_type = NULL;

	/**
	 * @var  string  file
	 */
	protected $_file = NULL;

	/**
	 * @var  string  load paths
	 */
	protected $_load_paths = NULL;

	/**
	 * @var  array  engines
	 */
	protected $_engines = array();

	/**
	 * @var  array processors
	 */
	protected $_processor = array();

	/**
	 * @var  bool copy
	 */
	protected $_copy = TRUE;

	/**
	 * @var  string  folder
	 */
	protected $_folder = NULL;

	/**
	 * @var   string  source file
	 */
	protected $_source_file = NULL;

	/**
	 * @var   string  destination web file
	 */
	protected $_destination_web = NULL;

	/**
	 * @var  string  destination file
	 */
	protected $_destination_file = NULL;

	/**
	 * @var  string condition
	 */
	protected $_condition = NULL;

    /**
     * @var int weight
     */
    protected $_weight = 0;

	/**
	 * @var  int  last modified time
	 */
	protected $_last_modified = NULL;

	/**
	 * Get the source file
	 *
	 * @return string
	 */
	public function source_file()
	{
		return $this->_source_file;
	}

	/**
	 * Get the source type paths
	 *
	 * @return string
	 */
	public function load_paths()
	{
		return $this->_load_paths;
	}

	public function copy()
	{
		return $this->_copy;
	}

	public function folder()
	{
		return $this->_folder;
	}

	/**
	 * Get the web destination file
	 *
	 * @return string
	 */
	public function destination_web()
	{
		return $this->_destination_web;
	}

	/**
	 * Get the destination file
	 *
	 * @return string
	 */
	public function destination_file()
	{
		return $this->_destination_file;
	}

	/**
	 * Get the engines that will be used to compile this asset
	 * @return array
	 */
	public function engines()
	{
		return $this->_engines;
	}

	/**
	 * Get the type of this asset
	 * @return string
	 */
	public function type()
	{
		return $this->_type;
	}

	/**
	 * Get the processor of this asset
	 * @return string
	 */
	public function processor()
	{
		return $this->_processor;
	}

	/**
	 * Get the condition
	 *
	 * @return  string
	 */
	public function condition()
	{
		return $this->_condition;
	}

	/**
	 * Set up the environment
	 *
	 * @param  string  $type
	 * @param  string  $file
	 * @param  array   $options
	 */
	function __construct($type, $file, array $options = array(), $destination_path = NULL, $copy = TRUE, $folder = NULL)
	{
		// Set processor to use
		$this->_processor   = Arr::get($options, 'processor', Kohana::$config->load('asset-merger')->get('processor.'.$type));

		// Set condition
		$this->_condition   = Arr::get($options, 'condition');

		$this->_folder = $folder;

        // Set weight
        if ( ! empty($options['weight']))
            $this->_weight  = $options['weight'];

        // Set load paths
        if ( ! empty($options['load_paths']))
		{
            $this->_load_paths  = $options['load_paths'][$type];
		}
		elseif (Kohana::$config->load('asset-merger')->get('load_paths.'.$type))
		{
			$this->_load_paths  = Kohana::$config->load('asset-merger')->get('load_paths.'.$type);
		}

		// Set type and file
		$this->_type = $type;
		$this->_file = $file;
		$this->_copy = $copy;

		// Check if the type is a valid type
		Assets::require_valid_type($type);

		if (Valid::url($file))
		{
			// No remote files allowed
			throw new Kohana_Exception('The asset :file must be local file', array(
				':file' => $file,
			));
		}

		// Look for the specified file in each load path
		foreach ((array) $this->_load_paths as $path) {

				if (is_file($path.DIRECTORY_SEPARATOR.$file))
				{
					// Set the destination and source file
					$this->_destination_file = Assets::file_path($type, $file, $destination_path,$this->_folder);
					$this->_source_file = $path.DIRECTORY_SEPARATOR.$file;	
					
					// Don't continue
					break;
				}
		}
		
		if ( ! $this->source_file())
		{
			// File not found
			throw new Kohana_Exception('Asset :file of type :type not found inside :paths', array(
				':file'  => $file,
				':type'  => $type,
				':paths' => join(', ', (array) Kohana::$config->load('asset-merger')->get('load_paths.'.$type)),
			));
		}

		if ( ! is_dir(dirname($this->destination_file())) and $this->copy())
		{
			// Create directory for destination file
			mkdir(dirname($this->destination_file()), 0777, TRUE);
		}

		// Get the file parts
		$fileparts = explode('.', basename($file));

		// Extension index
		$extension_index = array_search($this->type(), $fileparts);

		// Set engines
		$this->_engines = array_reverse(array_slice($fileparts, $extension_index + 1));

		// Set the web destination
		$this->_destination_web = Assets::web_path($type, $file, $destination_path,$this->_folder);
	}

	/**
	 * Compile files
	 *
	 * @param   bool  $process
	 * @return  string
	 */
	public function compile($process = FALSE)
	{
		// Get file contents
		$content = file_get_contents($this->source_file());

		foreach ($this->engines() as $engine)
		{
			// Process content with each engine
			$content = Asset_Engine::process($engine, $content, $this);
		}

		if ($process AND $this->processor())
		{
			// Process content with processor
			$content = Asset_Processor::process($this->processor(), $content);
		}

		return $content;
	}

	/**
	 * Render HTML
	 *
	 * @param   bool  $process
	 * @return  string
	 */
	public function render($process = FALSE)
	{
		if ($this->needs_recompile() and $this->copy())
		{
			// Recompile file
			file_put_contents($this->destination_file(), $this->compile($process));
		}
		return Asset::html($this->type(), $this->destination_web(), $this->last_modified());
	}
	/**
	 * Process and return the webpath 
	 *
	 * @param   bool  $process
	 * @return  string
	 */

	public function get_webpath($process = FALSE)
	{
		if ($this->needs_recompile() and $this->copy())
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

	public function __toString()
	{
		return $this->render();
	}

    /**
     * Get the weight
     *
     * @return int weight
     */
    public function weight()
    {
        return $this->_weight;
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
			// Set the last modified time
			$this->_last_modified = filemtime($this->source_file());
		}

		return $this->_last_modified;
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

} // End Asset